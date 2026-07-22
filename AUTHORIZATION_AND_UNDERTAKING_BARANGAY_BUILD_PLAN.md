# Authorization (AZ) & Undertaking-Barangay (UB) Build Plan

> Companion to `AFFIDAVIT_OF_UNDERTAKING_REBUILD_PLAN.md` — reuses the same *build technique*
> (Legal 8.5x13in, Calibri, bordered-table TCPDF construction, header `image` field) but **not**
> its coordinate layout. Both documents below already have staff-tuned field-map coordinates
> that must survive this rebuild untouched except where explicitly flagged.

## Hard constraint (read first, applies to every step below)

The x/y positions currently in `UndertakingBarangayPdfFieldMap.php` and
`AuthorizationPdfFieldMap.php` were manually tuned by the user and must be preserved. **Correction
to an earlier draft of this plan:** the base PDFs these coordinates currently overlay are *not*
"real templates" — direct FPDI inspection confirms both
`storage/app/templates/approved-loan-documents/pdf/undertaking-barangay-officials.pdf` (1,585
bytes) and `.../authorization.pdf` (1,580 bytes) are still the same class of bare-title-text A4
placeholder (210.0×297.0mm) that `affidavit-undertaking.pdf` was *before* its rebuild — not real
artwork. This doesn't weaken the constraint: the coordinates are still the fixed target (tuned via
`php artisan loan-documents:calibrate-fields {ub,az}`'s grid overlay against a physical reference
document, not against the placeholder's content), so the new artwork must still be drawn to fit
them, not the other way around. It does mean don't be surprised when opening the current PDF shows
nothing worth preserving — only the field-map coordinates are the asset here, not the file.
`affidavit-undertaking.pdf` itself, for scale, is now 215.9×330.2mm (Legal, 8.5×13in), 181,047
bytes of real artwork — that's the target format for both rebuilds below, since both UB and AZ are
currently still stuck on the old A4 placeholder size.

Only three kinds of coordinate/wiring change are in scope:

1. **The one confirmed bug in UB** — `loan.approved_amount` and `loan.gnthp` collide at the
   identical position `x=107, y=62`. Give one of them a new position; the other stays put.
2. **Genuinely new fields** that have no existing position because they don't exist in either
   field map today (UB's Age/Civil Status/Nationality; AZ's two signature lines; UB's notarial
   block). These get new positions chosen to fit the redrawn artwork.
3. **Two flagged data-source corrections** (analogous to #1, not listed in the original brief
   but required by it — see "Flagged decisions" below) — a value at an *already-correct
   position* is wrong and needs repointing to a different data field, with the coordinate
   itself unchanged.

Every other coordinate in both files stays exactly as dumped below.

---

## 0. Full coordinate dump (fixed target — read before designing anything)

### 0a. `UndertakingBarangayPdfFieldMap.php` (current, 12 entries, 1 page)

| x | y | size | width/line_height | value | Note |
|---|---|------|---|---|---|
| 27 | 42 | 10 | — | `applicant.full_name` | |
| 27 | 50 | 8 | width 160, line_height 4 | `applicant.address` | |
| 27 | 62 | 9 | — | `loan.type` | |
| **107** | **62** | 9 | — | `loan.approved_amount` | **Collides with the row below** |
| 27 | 72 | 9 | — | `loan.approved_date` | |
| 104 | 72 | 9 | — | `organization.company_name` | |
| 27 | 82 | 9 | — | `barangay.name` | `TODO(calibrate-ub)` comment above it — **dead field, remove (§1d)** |
| 27 | 90 | 9 | — | `barangay.clearance_reference` | **dead field, remove (§1d)** |
| 27 | 98 | 9 | — | `barangay.locality` | **dead field, remove (§1d)** |
| 27 | 106 | 9 | — | `barangay.official_designation` | `TODO(calibrate-ub)` — **repoint value to `applicant.position_or_designation`, position unchanged (§1c)** |
| 27 | 114 | 9 | — | `barangay.agency_name` | **repoint value to `applicant.employer_or_business`, position unchanged (§1c)** |
| 27 | 122 | 8 | width 160, line_height 4 | `barangay.agency_address` | **repoint value to `applicant.office_address`, position unchanged (§1c)** |
| **107** | **62** | 9 | — | `loan.gnthp` | **Collides with `loan.approved_amount` above — this is the one entry that moves (§1c)** |

No header image, no signature line, no notarial block exist in this field map today — those are
all net-new additions (category 2), not repositioned fields.

### 0b. `AuthorizationPdfFieldMap.php` (current, 11 entries, 1 page)

**Correction to the brief:** this is not a blank canvas — it has 11 already-wired fields with
real tuned coordinates. All of them are preserved unless explicitly flagged.

| x | y | size | width/line_height | value | Note |
|---|---|------|---|---|---|
| 26 | 38 | 10 | — | `applicant.full_name` | |
| 26 | 46 | 8 | width 162, line_height 4 | `applicant.address` | |
| 26 | 58 | 9 | — | `loan.reference` | |
| 88 | 58 | 9 | — | `loan.approved_amount` | **Repoint value to a new `loan.loan_security_amount` field, position unchanged — flagged decision, see below (§2c/§2d)** |
| 138 | 58 | 9 | — | `loan.approved_date` | |
| 26 | 68 | 9 | — | `organization.company_name` | |
| 26 | 94 | 9 | — | `authorization.release_method` | **CONFIRMED DEAD — remove entirely (§2d)** |
| 26 | 102 | 9 | — | `authorization.payout_bank_name` | **"Enterprise Bank, Inc." is now static artwork text, not a dynamic field — remove this AZ field-map entry (§2d, see flagged decision)** |
| 26 | 110 | 9 | — | `authorization.payout_account_number` | Preserved as-is |
| 26 | 118 | 9 | — | `authorization.payout_bank_branch` | `TODO(calibrate-az)` — preserved as-is, drop the TODO once verified |
| 26 | 126 | 9 | — | `authorization.payout_atm_holder_name` | `TODO(calibrate-az)` — preserved as-is, drop the TODO once verified |

No header image, no signature lines exist today — both are net-new (category 2).

---

## Flagged decisions (need your sign-off before Phase 2 starts — not silently assumed)

These go beyond the two exception categories named in the brief, so I'm calling them out
explicitly rather than picking a side:

1. **`loan.approved_amount` at AZ's `(88, 58)` → `loan.loan_security_amount`.** The real
   reference paragraph says "...credit the **loan security** of my loan in the amount of ___" —
   that's `loan_security_amount_raw` (already computed in `buildDocumentData()`, just never
   formatted for display), not the raw approved loan amount. The existing position looks like
   it was already reserved for "the amount blank" in that sentence, so the fix is a value-source
   correction at the same coordinate — same pattern as UB's barangay.* bug. Confirm this reading
   is correct before I touch it; if the field truly should stay `approved_amount`, say so and
   I'll add `loan_security_amount` at a new position instead.
2. **`authorization.payout_bank_name` at `(26, 102)` goes away as a dynamic field.** The
   underlying data field itself (`payout_bank_name`, fed from the wizard/staff processing screen)
   is **not** deleted anywhere — AU's field map still reads `authorization.payout_bank_name` at
   `(106.5, 141)` and continues to need it. Only AZ's own field-map entry and AZ's catalog
   `required_fields`/`source_fields` drop it, because AZ's redrawn paragraph now hardcodes
   "Enterprise Bank, Inc." as static text instead of printing a dynamic bank name.
3. **`WIBS_DOCUMENT_FIELD_MAP.md` sections 4 (AZ) and 9 (UB) are already stale relative to this
   plan** and will need more than the one documentation note requested — not just an addition:
   - AZ's doc currently claims the hardcoded bank name "was removed" in favor of a dynamic
     `payout_bank_name` field, and describes `release_method` as a live wired field. Both
     claims are being reversed by this build (bank name goes back to static; release_method is
     confirmed dead). The doc also lists a "Witnesses & Notarization" section for AZ that has no
     corresponding field-map entries today and no mention in the confirmed real reference
     content (brief, single-page, no notarial block) — this looks like copy-paste leftover from
     LSA/UB's doc section, not real AZ content. Recommend deleting that subsection rather than
     preserving it.
   - UB's doc currently claims an `applicable()` gate keyed on `barangay_name` OR
     `barangay_clearance_reference` OR `barangay_locality` — this does not exist in code
     (`isApplicable()` is hardcoded `true`, confirmed by the prior investigation and locked in by
     `LoanRequestDocumentApplicabilityTest.php`). This stale claim needs correcting in Phase 3,
     not just supplementing.

   Phase 3 below assumes you want these corrected (not just the new manual-choice note added);
   flag if you'd rather leave the stale text alone.
4. **UB's `required_fields` after dropping `barangay_name`/`barangay_clearance_reference` breaks
   two existing tests, not zero.** `tests/Feature/LoanRequestDocumentApplicabilityTest.php:80-91`
   ("undertaking barangay surfaces incomplete when its required fields are blank") and `:93-110`
   ("...become ready to generate once their required fields are filled") both drive UB's readiness
   status by persisting exactly these two keys via `applicabilityPersistDataEntries()`. Removing
   them from `required_fields` without a replacement makes UB always report ready once applicable
   (which — per `isApplicable()` — is always), silently defeating both tests' intent rather than
   failing loudly. Recommend `required_fields => ['guaranteed_net_take_home_pay']` — the one
   staff-entered figure paragraph 1 genuinely needs before the document means anything, mirroring
   AZ's own pattern of requiring its staff-entered banking figures. Confirm before Phase 1d; if
   declined, the fallback is `required_fields => []` (matches AU's own empty `required_fields`,
   `LoanRequestDocumentCatalog.php:106`), but then update the two tests above to assert
   "always ready once applicable" instead of testing an incomplete state that can no longer occur.

---

## Phase 1 — Undertaking-Barangay (UB)

**Risk profile:** lower — UB is not gated by `isApplicable()` (confirmed always-true, staying
that way per your explicit decision not to add classification/gating logic), so a bad rebuild
here can't accidentally block document generation, only produce a wrong-looking PDF.

### 1a. Coordinate dump
Done above (§0a) — this is the fixed target for 1b.

### 1c doesn't come before 1b in dependency order, so building order is: artwork → field map →
catalog → cleanup. (Renumbered from the brief's ordering only because the field map can't be
finalized without knowing the artwork's header/signature/notarial geometry.)

### 1b. New base artwork (`undertaking-barangay-officials.pdf`)
Design around the preserved positions in §0a — do not move them to fit the artwork; fit the
artwork to them.

- Legal 8.5x13in, Calibri, matching AU's proven build technique (org header reserved space,
  bordered affiant-info table, TCPDF construction — not AU's coordinates).
- Bordered affiant-info table containing: full name (27,42), address (27,50), loan type (27,62),
  loan amount (107,62 — post-collision-fix), approved date (27,72), company name (104,72),
  designation (27,106), agency name (27,114), agency address (27,122). The three dead barangay.*
  rows (82/90/98) are dropped from the table entirely — one fewer row than today.
- 4 numbered paragraphs with the real UB content (fresh text, not reused from AU):
  1. Promise not to execute Authority to Deduct against salary as [Designation], "so as not to
     impair my monthly net take home pay of ___" → `loan.gnthp`.
  2. "...granted a Salary Loan... in the amount of Pesos: (P___)" → `loan.approved_amount`
     (moved off the collision, new position TBD in artwork).
  3. Purpose: not impair monthly net take home pay, continuously pay loan without delay.
  4. Waiver under Rule 39 §12 Rules of Court; MRDINC may file a criminal case for a fraudulent
     undertaking.
- Signature line + notarial block (Doc/Page/Book No. blank space, "for and in the province of
  ___" phrasing) — all net-new, positioned to fit the redrawn page around the preserved table.
- Reserved header space matching AU's header image convention.

### 1c. Field map changes
- Repoint (value only, positions unchanged): `barangay.official_designation` →
  `applicant.position_or_designation`; `barangay.agency_name` → `applicant.employer_or_business`;
  `barangay.agency_address` → `applicant.office_address`.
- Fix the collision: keep `loan.approved_amount` at `(107, 62)`; move `loan.gnthp` to a new
  position chosen during artwork design (inline in paragraph 1, same technique AU uses for its
  GNTHP blank).
- Add Age/Civil Status/Nationality at new positions — source `applicant.age`,
  `applicant.civil_status`, `applicant.nationality`. All three already exist in
  `ApprovedLoanDocumentService::personDocumentData()` (confirmed — no `buildDocumentData()`
  changes needed, this is field-map-only wiring).
- Add notarial block fields (new positions): `notarial.signing_place`, `notarial.series_year` —
  same source as AU (`OrganizationSetting` business address / document date), no
  `buildDocumentData()` changes needed, both keys already exist.
- Add signature-line field (new position): `applicant.full_name` in the signature block, same
  technique as AU's bordered 3-column signature table.

### 1d. Remove dead barangay.* wiring
- `UndertakingBarangayPdfFieldMap.php`: delete the three `barangay.name` /
  `barangay.clearance_reference` / `barangay.locality` entries.
- `LoanRequestDocumentCatalog.php` (`undertaking_barangay` definition, currently at
  `app/Services/LoanRequests/LoanRequestDocumentCatalog.php:312-339`): drop
  `barangay_name`/`barangay_clearance_reference` from `required_fields`, and
  `barangay_name`/`barangay_clearance_reference`/`barangay_locality` from `source_fields`.
  `official_designation`/`agency_name`/`agency_address`/`guaranteed_net_take_home_pay` stay in
  `source_fields` (still feed the document, just from `applicant.*` now).
- `ApprovedLoanDocumentService::buildDocumentData()` (`barangay` block, currently
  `app/Services/LoanRequests/ApprovedLoanDocumentService.php:934-959`): drop the `name`,
  `clearance_reference`, and `locality` keys from the returned array. Keep `official_name`,
  `official_title`, `official_designation`, `agency_name`, `agency_address` — even though the
  field map no longer sources designation/agency from these `barangay.*` keys, confirm nothing
  else reads them before deleting further (`official_name`/`official_title` in particular —
  grep before removing, they may still back the staff processing UI's barangay data-entry
  section independent of this document).
- `requires_financials => false` already correct — no change.
- **Update `required_fields`'s replacement per flagged decision #4** (`guaranteed_net_take_home_pay`
  recommended) and rewrite `tests/Feature/LoanRequestDocumentApplicabilityTest.php:80-91` and
  `:93-110` to persist/assert against the new required field instead of the deleted
  `barangay_name`/`barangay_clearance_reference` keys — do this in the same commit as the
  `required_fields` change so the suite never sits red between them.

### 1e. Catalog version bump
Bump `template_version` from `undertaking-barangay-v2` to `-v3` and update `template_files`
description if the filename changes.

---

## Phase 2 — Authorization (AZ)

**Risk profile:** higher — AZ is generated for every approved loan and has more flagged
decisions (§ above) than UB. Do not start until the flagged decisions are confirmed.

### 2a. Coordinate dump
Done above (§0b) — 11 existing fields, all preserved except the two flagged removals and one
flagged value-source change.

### 2b. New base artwork (`authorization.pdf`)
- 8.5x13in, Calibri, header space matching AU's convention.
- Single-paragraph authorization text: "This is to authorize MRDIC to credit the loan security
  of my loan in the amount of ___ (Php. ___), to the account of ___, under Account Number ___
  with **Enterprise Bank, Inc.** (static text), Branch ___ (___)." Design the paragraph so the
  `(26,94)` gap left by removing `release_method` and the `(26,102)` gap left by removing the
  dynamic bank name are absorbed into paragraph flow/line spacing — **do not** shift
  `payout_account_number (26,110)`, `payout_bank_branch (26,118)`, or
  `payout_atm_holder_name (26,126)` to close the gap; they stay exactly where they are.
- Borrower signature line (new position) — printed name + blank line, wet-ink pattern, same as
  every other document in this family (no signature capture).
- "With my conformity:" + ATM Card Holder signature line (new position) — printed name sourced
  from `authorization.payout_atm_holder_name` (same value already used for the inline "account
  of ___" blank).
- No witness or notarial block — the confirmed real reference content doesn't have one; this
  also means Phase 3 should drop the stale "Witnesses & Notarization" section from
  `WIBS_DOCUMENT_FIELD_MAP.md`'s AZ entry (flagged decision #3 above).

### 2c. `buildDocumentData()` — add formatted loan security amount
In `ApprovedLoanDocumentService.php`, near where `gnthp`/`gnthp_raw` are built
(`ApprovedLoanDocumentService.php:803-807`), add a formatted display string mirroring that exact
pattern:
```php
$loanSecurityAmountFormatted = $this->formatCurrencyValue($loanSecurityAmountRaw);
$loanSecurityAmount = $loanSecurityAmountFormatted !== null ? '₱'.$loanSecurityAmountFormatted : null;
```
and add `'loan_security_amount' => $loanSecurityAmount` next to the existing
`'loan_security_amount_raw' => $loanSecurityAmountRaw` at `ApprovedLoanDocumentService.php:893`.
No other `buildDocumentData()` changes needed for AZ — `payout_atm_holder_name`,
`payout_account_number`, `payout_bank_branch` already exist in the `authorization` block.

### 2d. Field map changes
- Repoint (pending sign-off on flagged decision #1): `loan.approved_amount` at `(88, 58)` →
  `loan.loan_security_amount`, position unchanged.
- Remove `authorization.release_method` entry at `(26, 94)` entirely.
- Remove `authorization.payout_bank_name` entry at `(26, 102)` entirely (pending sign-off on
  flagged decision #2) — bank name becomes static artwork text.
- Keep `payout_account_number (26,110)`, `payout_bank_branch (26,118)`,
  `payout_atm_holder_name (26,126)` exactly as-is; drop their `TODO(calibrate-az)` comments once
  visually verified against the new artwork.
- Add two new signature-line fields (new positions): Borrower's `applicant.full_name`, and ATM
  Card Holder's `authorization.payout_atm_holder_name`.

### 2e. Catalog changes
`LoanRequestDocumentCatalog.php` (`authorization` definition, currently at
`LoanRequestDocumentCatalog.php:127-152`):
- `required_fields`: drop `payout_bank_name` (no longer printed dynamically); keep
  `payout_account_number`. Consider adding `payout_atm_holder_name` if it should be mandatory for
  this document now that it drives a signature line — flag if unsure, defaulting to leaving
  required_fields' strictness unchanged beyond the explicit drop.
- `source_fields`: drop `payout_bank_name` and `release_method`; add `loan_security_amount`
  (the new formatted field from §2c).
- Bump `template_version` from `authorization-v2` to `-v3`, update `template_files` description
  if filename changes.

---

## Phase 3 — Documentation (`WIBS_DOCUMENT_FIELD_MAP.md`)

1. **Manual-choice note (as originally requested).** Add a note near the AU (§3) and UB (§9)
   sections recording: the Affidavit-of-Undertaking-vs-Undertaking-Barangay choice is
   intentionally manual — `LoanRequestDocumentCatalog::isApplicable()` is hardcoded to always
   return `true` for every document, confirmed deliberate and locked in by
   `LoanRequestDocumentApplicabilityTest.php` — plus the real-world rule: ATM/private-bank
   salary-deduction members get Affidavit of Undertaking; barangay and hospital workers
   (institutional/government-payroll generally) get Undertaking-Barangay. No classification
   field or gating logic is being added — explicitly declined.
2. **Correct the stale UB `applicable()` claim** (§9, currently lines 554-556) — replace the
   "gated on barangay_name OR clearance_reference OR locality" text with the manual-choice note
   from step 1, since that gate doesn't exist in code.
3. **Correct the stale AZ claims** (§4, currently lines 306-315): remove the "hardcoded Enterprise
   Bank, Inc. was removed" note (reversed by this build) and the `release_method` "✅ wired" row
   (now dead); update the Bank/Payout table to reflect: bank name is static artwork text (not a
   data source row at all), `payout_account_number`/`payout_bank_branch`/
   `payout_atm_holder_name` remain wired, `release_method` row removed with a 🗑️ note.
4. **Drop or correct AZ's "Witnesses & Notarization" section** (§4, currently lines 338-344) —
   no such block exists in the confirmed real reference content or in the rebuilt field map;
   replace with a note that AZ has no witness/notarial block, only Borrower + ATM Card Holder
   signature lines.
5. **Update UB's affiant/barangay tables** (§9, currently lines 558-591) to match the rebuilt
   field map: Age/Civil Status/Nationality now ✅ (derived same as AU), the three
   `barangay.name/clearance_reference/locality` rows become 🗑️ removed, designation/agency
   rows' source updates from `barangay.*` to `applicant.*`.

---

## Verification & rollback (both phases)

- **Live visual verification at every artwork-build and field-map step** — real rendered
  screenshots via `php artisan loan-documents:calibrate-fields {ub|az}`, not text-only
  assertions. This document family's history (see memory: Loan Information footer re-export was
  claimed-fixed but wasn't — glyph corruption only caught by rendering, not by hash/text checks)
  has repeatedly shown text-only verification misses real defects, and that risk is higher here
  since artwork is being built around fixed coordinates rather than the usual design-first flow.
- **Pest coverage**: content assertions (correct paragraph text, correct field values pulled)
  plus coordinate-pinning tests analogous to `LoanRequestDocumentApplicabilityTest.php`'s pattern
  — assert the collision fix actually separates `approved_amount`/`gnthp` in UB, assert AZ's
  `release_method`/`payout_bank_name` field-map entries are gone, assert new fields resolve to
  the expected `applicant.*`/`loan.*` keys. New test files:
  `tests/Feature/UndertakingBarangayDocumentTest.php`,
  `tests/Feature/AuthorizationDocumentTest.php` (neither exists today — confirmed via glob).
- **Full Pest suite at the end of each phase** — per the standing memory on this codebase, the
  full suite has previously died/corrupted template files under certain conditions; run it and
  confirm the real template PDFs are untouched afterward (diff or checksum before/after), same
  verification already established for the Pest storage-isolation fix.
- **Separate commits per phase, each with a rollback path** — Phase 1 (UB artwork + field map +
  catalog + cleanup) and Phase 2 (AZ artwork + field map + catalog) land independently, so a
  defect in one doesn't force reverting the other. Documentation (Phase 3) lands as its own
  commit after both are visually verified.

## Explicit non-goals

- No change to the digital-signature-removal work already completed — both documents keep the
  wet-ink (printed name + blank line) pattern, no signature capture.
- No applicability-gating logic added anywhere — `isApplicable()` stays hardcoded `true`,
  per your explicit decision.
- No change to `wmaster.occupation` matching or any new employment-classification field — out of
  scope, confirmed in the prior investigation.
