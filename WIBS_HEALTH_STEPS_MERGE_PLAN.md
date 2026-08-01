# Health Declarations + GLAPI Questionnaire Merge Plan

**Status: SUPERSEDED.** This document describes an earlier plan (keep `health_diabetes` separate, only merge smoking + item 2e). The user subsequently chose a different, simpler design ("Path B — presentation-only merge"), actually implemented on `feature/rbac-loan-workflow`:

- `health_diabetes` and `health_declaration_notes` are **retired entirely** (not kept separate as §3 below recommends).
- `health_recent_hospitalization` moved into the `health_glapi` FIELD_DEFINITIONS section and renders as a genuine GLAPI item — last in the list, no numeric badge — instead of getting a redesigned/expanded treatment.
- `health_smoking_status`/`health_hypertension` stayed in their existing `health` FIELD_DEFINITIONS section (no data-migration/step-count risk); only the wizard's *presentation* layer merges "Health declarations" into the "Health Insurance Questionnaire" (renamed from "Generali (GLAPI) health questionnaire").
- No Artisan backfill command, no item-2e 4-checkbox split, and no step-count-bound Form Request changes were needed — this plan's §2–§4 do not reflect what shipped.

The rest of this document is kept for historical context only; do not use it as a guide to current field names or file line numbers.

---

**Status:** Planning document only. No application code, migrations, or tests have been modified as part of producing this document.

## Background

Today the client loan-request wizard has 24 steps (indices 0-23, defined in `resources/js/components/loan-request/loan-request-wizard-steps.ts`). Two adjacent steps cover health-related data:

- Step 16, id `health` — the plain "Health declarations" step: `health_smoker`, `health_hypertension`, `health_diabetes`, `health_recent_hospitalization`, `health_declaration_notes` (all defined in `app/Services/LoanRequests/LoanRequestDataService.php::FIELD_DEFINITIONS`, section `health`).
- Steps 17-20, ids `health-glapi-1..4` — the Generali (GLAPI) health questionnaire, already split 4 ways (not a single step) by a prior-session redesign (`WIBS_LOAN_WIZARD_UX_ARCHITECTURE_PLAN.md`) specifically to reduce per-step density. Items are grouped 4-ish per step via `chunkGlapiItemGroups()`/`getGlapiItemGroups()` in `resources/js/components/loan-request/loan-request-steps.tsx:759-800`.

Of the plain-health fields, only `health_hypertension` duplicated a GLAPI item (item 3) and was already merged: there is one boolean (`health.health_hypertension`), reused directly by GLAPI item 3's PDF row, plus a GLAPI-only satellite text field `health_glapi.health_hypertension_details` for the "details of yes answer" GLAPI asks for. `health_recent_hospitalization` and (until this plan) `health_diabetes` were deliberately kept distinct from their GLAPI-adjacent counterparts (items 5 and 2e) due to real scope/time-window differences. `health_recent_hospitalization` stays out of scope for this plan entirely.

This plan covers redesigning the two remaining overlapping questions — smoking and diabetes/kidney/liver/urinary — and merging what remains of the two steps into one.

---

## 1. Step structure

**Recommendation: merge step 16 (`health`) with step 17 (`health-glapi-1`) only — not all 5 old steps into one monolithic step.**

Reasoning: the 4-way GLAPI split was a deliberate, already-shipped fix for step density. Collapsing all 5 steps (`health` + all 4 GLAPI sub-steps) into a single step would reintroduce exactly the problem that split solved — one step would end up rendering the surviving plain-health fields, the new combined smoking question, the new diabetes/kidney/liver/urinary checkbox group, plus items 1, 2a-d, 2f-j (whatever else GLAPI-1 currently holds), all at once. `health-glapi-1` is also where GLAPI item 2e already lives, so merging there keeps every field touched by this redesign (smoking, diabetes/kidney/liver/urinary, plus the untouched `health_hypertension` and `health_recent_hospitalization`) co-located in one place, while `health-glapi-2/3/4` keep the rest of the GLAPI items exactly as they render today (minus item 11, which is absorbed into the combined step since it no longer exists as a separate question).

Net result: wizard step count goes from 24 to 23. The new combined step's field order: `health_recent_hospitalization` → `health_hypertension` (+ its GLAPI-details reveal) → new smoking radio group → new diabetes/kidney/liver/urinary checkbox group → remaining GLAPI-1 items.

## 2. Smoking field migration

**New field:** `health_smoking_status`, section `health`, type `enum` (values: `none`, `light`, `heavy`), replacing both `health_smoker` (boolean) and `gl_health_q11_smoker` (boolean). A companion `health_smoking_status_details` text field is kept for the free-text "smoking details" GLAPI collects today (`detail_of: health_smoking_status`), reusing the exact `detail_of` pattern already used for `health_hypertension_details`. `required_on_submit: true` (matches the stricter of the two predecessors, `health_smoker`).

Radio option → stored value mapping:
- "No, I don't smoke" → `none`
- "Yes, less than 10 cigarettes per day" → `light`
- "Yes, more than 10 cigarettes per day" → `heavy`

**Consumer re-derivation** (every current reader of `health_smoker` / `gl_health_q11_smoker` was traced; all are fully satisfiable by the 3-option field — no consumer needs information the new field can't provide):

| Consumer | File | Old read | New derivation |
|---|---|---|---|
| GREPALIFE PDF checkbox | `app/Services/LoanRequests/PdfFieldMaps/GrepalifePdfFieldMap.php:632-643` | `healthChecked('health_smoker')` | `healthSmokingAnswer()` → `status !== 'none'` (checks the Q1 Yes box) |
| Document workflow field flatten | `app/Services/LoanRequests/LoanRequestDocumentWorkflowService.php:654` | `$flatValues['health_smoker']` | `$flatValues['health_smoking_status'] !== 'none'` |
| Approved-doc override/flatten | `app/Services/LoanRequests/ApprovedLoanDocumentService.php:968` | same pattern | same derivation |
| Generali/GLAPI item 11 PDF row | `app/Services/LoanRequests/PdfFieldMaps/GeneraliPdfFieldMap.php:138` | `healthRow(2, 90.4, 'gl_health_q11_smoker')` | `status === 'heavy'` (preserves item 11's original ">10 cigarettes/day" semantics exactly) |
| Document catalogs (field-presence lists) | `app/Services/LoanRequests/LoanRequestDocumentCatalog.php:68,80,382-383` | lists both keys | replace both entries with `health_smoking_status` (+ `_details`) |

**Existing-data migration (explicit, not deferred):** a one-time Artisan command, following the project's existing `loan-workflow:repair`-style convention, backfills `health_smoking_status` for every `LoanRequest` (draft or submitted) that has a `health_smoker` and/or `gl_health_q11_smoker` EAV entry:

1. If `gl_health_q11_smoker === true` → `heavy`.
2. Else if `health_smoker === true` → `light` (documented as a conservative default: the old boolean pair can't distinguish light/heavy when GLAPI's item 11 was left blank, which is common since it was `required_on_submit: false`; defaulting to the lower bucket avoids overstating risk).
3. Else → `none`.

Old EAV rows (`health_smoker`, `gl_health_q11_smoker`, `gl_health_q11_smoker_details`) are **not deleted** — they're left in place for audit-trail integrity (`LoanRequestChange` history already references them), and the application simply stops writing to them going forward. The backfill runs uniformly across drafts and submitted requests, so in-progress applicants don't lose their previously-saved answer when they next open the wizard.

## 3. Item 2e field migration

**`health_diabetes` recommendation: keep it separate. Do not retire it.**

Reasoning:
- `health_diabetes` is `required_on_submit: true` and feeds GREPALIFE specifically; GLAPI item 2e is `required_on_submit: false` and feeds the separate Generali document. Different compliance requirements attach to each — collapsing them would make a currently-mandatory GREPALIFE field dependent on an optional GLAPI answer.
- This mirrors the same scope/time-window distinction already established (and left untouched by this plan) for `health_recent_hospitalization` vs. GLAPI item 5 — the precedent in this codebase is that plain-health and GLAPI fields covering similar-sounding ground are kept distinct unless they are provably identical questions (as hypertension/item 3 were).
- Blast radius of retiring it would be large for no clear benefit: `health_diabetes` is validated in 5 Form Requests and referenced in 3 services, and is seeded as a fixture in 12+ existing Feature tests. Retiring it would force all of those to re-derive a mandatory value from an optional checkbox group.

**Item 2e itself is still redesigned** as planned: `gl_health_q02e_diabetes_renal` (single boolean) becomes 4 independent boolean fields — `gl_health_q02e_diabetes`, `gl_health_q02e_kidney`, `gl_health_q02e_liver`, `gl_health_q02e_urinary` — each rendered as a checkbox, sharing one `gl_health_q02e_diabetes_renal_details` free-text field (kept as-is, `detail_of` updated to point at the group rather than a single boolean) for "Other"/details entry.

**Existing-data migration for item 2e (explicit):** unlike the smoking field, this is a genuinely lossy split — a single `true` doesn't record which of the four conditions applied. Proposed approach, mirroring the human-review pattern already used for the release_method dropdown-normalization cleanup earlier this session: the same backfill command produces a review report (one row per loan request where `gl_health_q02e_diabetes_renal === true`, showing the old boolean plus the existing free-text `_details` value) and does **not** auto-check any of the 4 new boxes. Staff review each flagged row and correct it manually via the existing admin correction dialog (`admin-loan-request-correction-dialog.tsx`), which already supports editing GLAPI-section fields. Rows where the old boolean was `false`/`null` migrate straightforwardly to all-four-unchecked, no review needed.

## 4. Step-index impact

The "single source of truth" wizard-steps array is `resources/js/components/loan-request/loan-request-wizard-steps.ts`, exporting `loanRequestWizardSteps`. Correcting one assumption from the original request: only **2** Form Requests currently validate `wizard_step` numeric bounds (not 3) — plus one service-layer clamp that must be kept in sync. Full list of files needing updates for the 24→23 step-count change and the field redesigns:

- `resources/js/components/loan-request/loan-request-wizard-steps.ts` — remove the `health-glapi-1` entry (or repurpose `health` as the merged step and remove `health-glapi-1`); `STEP_INDEX` auto-derives from the array, no manual change needed there.
- `resources/js/pages/client/loan-request.tsx` — `GLAPI_STEP_START` constant (depends on `health-glapi-1`'s id existing), `resolveStepFromErrors()` (`health.` and `health_glapi.` branches plus `glapiItemNumberToStepOffset`, since item 11 and item 2e's location/shape change), and the JSX blocks currently rendering the `health` step (lines ~1065-1082) and the `glapiChunks.map(...)` block (lines ~1084-1114) — these need restructuring into one combined block.
- `resources/js/components/loan-request/loan-request-steps.tsx` — `chunkGlapiItemGroups`/`getGlapiItemGroups` (item numbering changes since item 11 is removed and item 2e expands from 1 to 4 sub-items), `LoanRequestHealthQuestionnaireStep`.
- `app/Http/Requests/Client/SaveDraftRequest.php:358` and `LoanRequestDraftRequest.php:357` — `'wizard_step' => [...,'max:24']` → `max:23`.
- `app/Services/LoanRequests/LoanRequestService.php:452-453` — the persist-time clamp `max(0, min(24, ...))` → `min(23, ...)`. This clamp was already flagged as a "silent trap" from a prior 10→15 step migration in `WIBS_WIZARD_STEP_SPLIT_PLAN.md:250-253` — it's easy to update the two Form Requests and forget this one.
- `app/Services/LoanRequests/LoanRequestDataService.php` — `FIELD_DEFINITIONS`: remove `health_smoker`, `gl_health_q11_smoker`, `gl_health_q11_smoker_details`, `gl_health_q02e_diabetes_renal`, `gl_health_q02e_diabetes_renal_details` (or repoint the details field); add `health_smoking_status`, `health_smoking_status_details`, `gl_health_q02e_diabetes`, `gl_health_q02e_kidney`, `gl_health_q02e_liver`, `gl_health_q02e_urinary`.
- `app/Services/LoanRequests/PdfFieldMaps/GeneraliPdfFieldMap.php` — replace the single item-11 `healthRow()` call and the single item-2e `healthRow()` call with the new field keys/coordinates (4 checkboxes for 2e need new x/y layout on page 1).
- `app/Services/LoanRequests/PdfFieldMaps/GrepalifePdfFieldMap.php` — update the `health_smoker` checkbox to read the derived boolean from `health_smoking_status`.
- `app/Services/LoanRequests/LoanRequestDocumentCatalog.php` — update the `grepalife` and Generali/GLAPI catalog field lists (lines ~68-90, ~340-390).
- `app/Services/LoanRequests/ApprovedLoanDocumentService.php` — field-flattening arrays (lines ~968-971, ~1185-1229) updated to new keys.
- **No change needed:** `resources/js/components/loan-request/admin-loan-request-correction-dialog.tsx` — its own `WIZARD_STEPS` (lines 91-122) is a separate, unrelated 5-step flow (`loan`, `applicant`, `co_maker_1`, `co_maker_2`, `review`) with no health/GLAPI step, already confirmed out-of-scope by two prior plans (`WIBS_WIZARD_STEP_SPLIT_PLAN.md:283`, `WIBS_LOAN_WIZARD_UX_ARCHITECTURE_PLAN.md:36`). Its own `resolveStepFromErrors` implementation (line 791) is independent and untouched.

## 5. Risk assessment + verification plan

**Risk:** medium-high. This touches validated user-submitted data across 5 Form Requests, 2 generated PDF documents (GREPALIFE and Generali), and a wizard step count that 2 Form Requests plus 1 service clamp must all agree on simultaneously. The item 2e migration is explicitly lossy and requires a human-review step rather than a fully-automated backfill — this is the highest-risk single element of the plan.

**Verification plan:**
1. Full Pest suite must pass, including updates to the 12+ existing Feature tests that currently seed `health_smoker`/`health_diabetes`/`gl_health_q02e_diabetes_renal`/`gl_health_q11_smoker` fixtures (`LoanRequestPrerequisiteTest.php`, `DraftAutoSaveTest.php`, `ApprovedLoanDocumentPackageDownloadTest.php`, `LoanRequestGlapiHealthQuestionnaireTest.php`, `LoanRequestDocumentCatalogGeneraliFieldsTest.php`, `LoanWorkflowAcceptanceTest.php`, `LoanRequestPhaseFiveWorkflowTest.php`, the profile-sync tests, etc.) — all need their fixture payloads updated to the new field shapes, plus `DraftAutoSaveTest.php`'s `wizard_step` boundary assertions updated from `max:24` to `max:23`.
2. New tests: the backfill command's three-way smoking derivation logic, the item-2e review-report generation (confirming flagged rows are never auto-checked), and validation rules for the two redesigned fields (radio enum values, checkbox group shape).
3. Live browser walkthrough (desktop viewport and a mobile viewport, e.g. 390px width) of the merged step, confirming: the new radio-button smoking question renders and its "details" text field reveals only when a "Yes" option is selected; the new diabetes/kidney/liver/urinary checkbox group renders, allows multiple simultaneous selections, and its details field reveals when any box is checked; `health_hypertension`'s existing conditional reveal still works unchanged; the wizard's step count/progress indicator reflects 23 steps; and `resolveStepFromErrors` correctly routes a validation error on any of the merged step's fields back to that step (test by submitting with a required field missing).
4. Confirm via `graphify update .` (or direct grep) that no other file still references the retired field keys or the old 24-step bound after the change lands.

## 6. Rough scope estimate

Substantially larger than either prior task from this session. For reference: the release_method investigation was a read-only, single-session data audit; the GNTHP auto-compute fix was a single-file, 8-line prop-wiring change verified in one pass. This health-steps merge touches roughly 15 files across three layers — 1 wizard-step config file and 2 React components on the frontend; 5 Form Requests, 1 field-definition catalog, 2 PDF field maps, 1 document catalog, and 2 document-flattening services on the backend — plus a new data-migration Artisan command with a human-review path (not fire-and-forget), plus updates to 12+ existing Pest tests and new tests for both redesigned fields. This is a multi-day feature-level effort, not a same-day fix, and the item 2e migration's manual-review step means it can't be considered "done" until staff have actually worked through the flagged-rows report.
