# Loan Wizard UX/Architecture — Scoping & Plan

> Written so a fresh session can implement with no other context. This is a **planning document — not yet implemented**. Three independent workstreams; can be done in any order or separately.

---

## 1. Move GLAPI step into "Insurance & health"

### Current state (confirmed in code)
- "Insurance & health" sidebar group (`resources/js/components/loan-request/loan-request-step-indicator.tsx`, `STEP_GROUPS`) covers steps 14–15: *Insurance & beneficiaries*, *Health declarations*.
- GREPALIFE's document fields (`app/Services/LoanRequests/LoanRequestDocumentCatalog.php:61-85`, `grepalife` key) already pull from exactly those two sections: `insurance` (beneficiary_primary_*) and `health` (`health_smoker`, `health_hypertension`, `health_diabetes`, `health_recent_hospitalization`).
- GLAPI lives in its own `health_glapi` section but is stranded at step 19, inside the "Declarations & review" group (between Declarations at 18 and Review at 20) — an append-at-the-end artifact from when it was added, not a deliberate placement.
- "Declarations & review" already demonstrates the multi-sub-step-per-group sidebar pattern we want to reuse.

### Target step order (0–20, count unchanged)
```
14 Insurance & beneficiaries
15 Health declarations
16 Generali (GLAPI) health questionnaire   ← moved from 19
17 Bank & payout           (was 16)
18 Barangay information    (was 17)
19 Declarations            (was 18)
20 Review & submit         (index unchanged)
```
Total stays 21 steps (indices 0–20) — **no change needed** to `wizard_step` bounds (`min:0,max:20` in `SaveDraftRequest.php` / `LoanRequestDraftRequest.php`, and the `max(0, min(20, ...))` clamps in `LoanRequestService::getFormData` / `saveDraft`). Only index *assignments* shift.

### Files to change
- `resources/js/components/loan-request/loan-request-step-indicator.tsx`
  - `STEP_GROUPS`: "Insurance & health" → `steps: [14, 15, 16]`, add `'Generali (GLAPI) health questionnaire'` to `stepNames`.
  - "Bank & payout" → `steps: [17, 18]`.
  - "Declarations & review" → `steps: [19, 20]`, `stepNames: ['Declarations', 'Review & submit']` (drop GLAPI name).
- `resources/js/pages/client/loan-request.tsx`
  - Reorder the `steps` array to match.
  - Renumber every `currentStep === N` block: 16→17 (Bank & payout), 17→18 (Barangay), 18→19 (Declarations), 19→16 (GLAPI — move this JSX block to right after Health declarations / before Bank & payout).
  - Update `resolveStepFromErrors`: `health_glapi.` → 16, `banking.` → 17, `barangay.` → 18, `declarations.` → 19.
- **Confirmed no change needed:** `admin-loan-request-correction-dialog.tsx` runs its own separate, shorter `WIZARD_STEPS` with zero GLAPI/health references — untouched by this reindex.

### Risk / verification
- Mechanical reindex, low risk. After implementing, grep the two touched files for any leftover literal `19`/`16`/`17`/`18` step references to make sure nothing was missed, and re-run `tests/Feature/LoanRequestGlapiHealthQuestionnaireTest.php` plus `tests/Feature/DraftAutoSaveTest.php` (both reference `health_glapi`/wizard step behavior).

---

## 2. Yes/No boolean control: Select → toggle

### Current state (confirmed in code)
Boolean-field rendering (`Select` → "Select an option" → Yes/No) is duplicated three times in `resources/js/components/loan-request/loan-request-steps.tsx`, all using a shared `booleanSelectValue()` helper but separate JSX:
- `LoanRequestDataSectionStep` (~line 488) — renders GREPALIFE's `health`/`banking`/`barangay`/`declarations` sections
- `LoanRequestHealthQuestionnaireStep` (~line 674) — renders GLAPI
- `LoanRequestInsuranceBeneficiariesStep` (~line 883) — insurance section

GREPALIFE and GLAPI do **not** share a component today — they're two separate render functions with copy-pasted boolean markup.

No `RadioGroup` exists in this codebase and `@radix-ui/react-radio-group` is not a dependency. However `@radix-ui/react-toggle-group` **is already installed**, and `resources/js/components/ui/toggle-group.tsx` already wraps it (`ToggleGroup` / `ToggleGroupItem`). Radix's `ToggleGroupPrimitive.Root` supports `type="single"` — exactly a two-button Yes/No toggle with correct roving-focus/ARIA behavior. **No new dependency needed.**

### Plan
- Extract one shared component (e.g. `BooleanYesNoField`) built on `ToggleGroup`/`ToggleGroupItem`, `type="single"`, values `'true' | 'false'`, mapped through the existing `booleanSelectValue()`/`onChange` contract.
- Swap it into all three call sites listed above. Pure refactor — no data-model, validation, or field-key changes (value stays a boolean under the hood).

### Risk / verification
- Visual/interaction change only. Check both GREPALIFE (`health` section, step 15) and GLAPI (`health_glapi`, step 16 after change #1) render correctly, including GLAPI's nested reveal-on-Yes children (`detail_of` / `clearDescendants` logic in `LoanRequestHealthQuestionnaireStep`) — the toggle's `onChange` must still fire `clearDescendants` when switching away from "Yes".

---

## 3. Reusable-profile architecture (About you / Bank & payout / Co-makers)

**Health/insurance questionnaires (GREPALIFE, GLAPI) explicitly stay OUT of this — fresh-per-application by design, not touched here.**

### What already exists (confirmed in code)
"About you" already has a full reusable-profile pattern — further along than expected:
- `App\Models\MemberApplicationProfile` (`app/Models/MemberApplicationProfile.php`) — one-to-one with `AppUser` via `user_id`. Stores nickname, birthplace, education, spouse info, employment/business, income, payday, `profile_completed_at`.
- `LoanRequestService::getFormData` (`app/Services/LoanRequests/LoanRequestService.php:80-149`): if no active draft, `buildApplicantSnapshot($user)` (line 1379) merges two sources — canonical read-only `wmaster` (core banking: name, legacy birthplace/address) and editable `MemberApplicationProfile`. `buildApplicantReadOnlyMap($user)` (line 1562) tells the frontend which fields are locked vs. editable (already rendered via the `applicantReadOnly` prop in `loan-request-fields.tsx`).
- `EnsureMemberProfileComplete` middleware (alias `member-profile-complete`, `bootstrap/app.php`) forces onboarding to `profile.edit` if `memberApplicationProfileIsComplete()` is false.
- Editing lives at `routes/settings.php` / `Settings\ProfileController` (`app/Http/Controllers/Settings/ProfileController.php`) — outside the wizard, on the Settings → Profile page.

**Confirmed UX pattern already in use: pre-filled-and-editable-inline** (not read-only-summary-with-edit-link). Recommendation: keep this precedent for any new reusable fields rather than introducing a second pattern.

### Confirmed: no write-back from the wizard today
Checked `LoanRequestService.php` fully — `MemberApplicationProfile` is **only ever read** (line 83 `loadMissing`, line 1382 in `buildApplicantSnapshot`), never written. `saveDraft()` and `submit()` only touch `loan_request_people` (`upsertPeopleSnapshots`) and `LoanRequestDataEntry` (`dataService->syncMemberSections`). The **only** writer anywhere in the codebase is `ProfileController::update` (Settings page).

**Consequence:** if a member corrects e.g. their employer name while filling out the wizard, that correction is saved only to that one loan request. `MemberApplicationProfile` stays stale and the *next* loan request pre-fills from the old data again. **Any reusable-profile write-back (About you retrofit, or new Bank & payout) needs new plumbing — there is no existing pattern to copy for wizard→profile sync.**

### Decision: sync on submit only, not on draft saves
Resolved in discussion; rationale to carry into implementation:
1. **Draft validation is loose.** `SaveDraftRequest`/`LoanRequestDraftRequest` mark applicant fields `sometimes/nullable` (confirmed, e.g. `employment_type`, `employer_business_name`, `gross_monthly_income` at `SaveDraftRequest.php:279-288`). `LoanRequestStoreRequest` requires them (`required`, same fields at `LoanRequestStoreRequest.php:285-294`). Syncing draft saves would let unvalidated/partial values overwrite the canonical profile.
2. **Autosave fires constantly and can be abandoned** (`tests/Feature/DraftAutoSaveTest.php` — saves on timer/step-change, not deliberate intent). A member who starts and abandons a wizard should never mutate their profile.
3. **`memberApplicationProfileIsComplete()` gates onboarding** — a mid-draft autosave could transiently flip profile completeness, corrupting the `member-profile-complete` middleware gate for unrelated flows.
4. **Matches existing precedent** — `ProfileController::update`, the only current writer, fires only on a full, validated Settings-page submission, never on partial/keystroke state.
5. **No real loss** — between steps, the loan request's own draft (`loan_request_people`) already persists in-progress answers for *that* request, so resuming works regardless of profile sync timing.

**Implementation point:** add the sync inside `LoanRequestService::submit()` only, using the already-validated payload that flows through `LoanRequestStoreRequest` (mirror `ProfileController::update`'s `Arr::only($validated, MemberApplicationProfile::fields())` approach). Leave `saveDraft()` untouched.

### Gaps to build
- **Bank & payout**: no analog exists. Currently a plain loan-request-scoped `LoanRequestDataEntry` (`banking` section), asked fresh every time. Proposal: new persistent table (or extend `MemberApplicationProfile`) keyed on `user_id`, pre-filled the same way as About you, editable inline, synced back on submit per the decision above. Given payout failure is a harsher failure mode than a typo elsewhere, add an explicit **"Confirm this bank account is still correct"** checkbox gating Next on this step, rather than relying on silent inline editability alone.
- **Co-makers**: no analog; `LoanRequestPerson` rows are 100% loan-request-scoped (`loan_request_id` FK only, no person/member identity link). Reuse would require a new `member_co_maker_profiles`-style table keyed on the owning member, matched fuzzily (name + birthdate — no shared identity key exists). **Recommend deferring** until real recurrence is confirmed (see below).

### Open questions (unresolved — do not implement past these without answers)
1. **Co-maker recurrence in production is unknown.** Dev DB query (10 `loan_requests`, 26 `loan_request_people`, 4 name+birthdate repeats across requests) is seed data, not signal — sample far too small either way. **Action before building co-maker reuse:** run the same query against production. If recurrence is low there too, don't build it.
2. Should the new "confirm still accurate" gate be Bank & payout only, or retrofitted onto About you too (which today has no explicit confirmation, just silent editability)? Leaning toward Bank & payout only for now (higher failure cost), but open.

### Relative scope estimate
- Bank & payout reuse: **smaller than the GLAPI build** (GLAPI was a 17-item nested questionnaire with `detail_of`/`visible_when` branching logic; this is a new table + prefill + one confirmation checkbox + a submit-time sync call).
- Co-maker reuse: **materially bigger than GLAPI** — new table, fuzzy-matching logic, UI to choose "reuse existing co-maker vs. add new," and real correctness risk if a match is wrong. Value unproven pending the production-data check in open question 1.

---

## Suggested sequencing
1. Step reindex (#1) — mechanical, no dependencies, do first.
2. Toggle control (#2) — independent, can be done in parallel with #1 or after.
3. Reusable profile (#3) — biggest, most open questions; resolve open question 1 (production co-maker data) before starting the co-maker half. Bank & payout half + submit-only sync can start once #1/#2 are settled, since it touches the same step-index territory.
