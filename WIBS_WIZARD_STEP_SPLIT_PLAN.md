# WIBS Wizard Step Split — Implementation Plan

> Written so a fresh session can implement the change with no other context.

---

## Context

The client loan-application wizard (`resources/js/pages/client/loan-request.tsx`) currently has **11 flat steps (indices 0–10)**. Four of those steps render long forms that require vertical scrolling inside the fixed-height content area (`h-[calc(100vh-330px)]`). The goal is to split those four long steps into shorter sub-steps so **no step requires scrolling**.

The grouped sidebar stepper (`loan-request-step-indicator.tsx`) already supports a variable number of sub-steps per group — adding sub-steps just means more items in a group's `steps`/`stepNames` arrays.

**Net result: 11 steps → 16 steps (indices 0–15).** (An earlier guess of "18" was wrong; the correct recount is **16**.)

### Splits (confirmed)
- **Personal data** (old idx 1) → 3 sub-steps (basic / address & contact / family & spouse)
- **Work & finances** (old idx 2) → 2 sub-steps (employment / income & details)
- **Co-maker 1** (old idx 3) → 2 sub-steps (personal / work & income)
- **Co-maker 2** (old idx 4) → 2 sub-steps (personal / work & income)

### Decisions locked with product owner
1. **Order is preserved.** Co-makers stay *before* insurance/health (do **not** reorder).
2. **Applicant sub-step 3 = "Family & spouse"** — contains `civil_status`, `educational_attainment`, `number_of_children`, `spouse_name`, `spouse_age`, `spouse_cell_no` (the component's existing third separator block, rendered whole).
3. **Applicant Work splits by component block:** step A (employment) = `employment_type` + employer name/address; step B (income & details) = `telephone_no`, `current_position`, `nature_of_business`, `years_in_work_business`, `gross_monthly_income`, `payday`.

### Reconciliation note — business context vs. real code
Some paper-form field names (sex, nationality, country, ZIP, home phone, email) **do not exist** in `LoanRequestPersonFormData` / the field components. This plan maps only to the **real** fields present in `resources/js/components/loan-request/loan-request-fields.tsx`.

---

## 1. Authoritative Flat Index Map (0–15)

| New idx | Display name | sectionKey (form) | Fields rendered | Was idx |
|---|---|---|---|---|
| 0 | Loan details | (loan) | `typecode`, `requested_amount`, `requested_term`, `availment_status`, `loan_purpose` | 0 |
| 1 | Personal: basic info | `applicant` | `first_name`, `last_name`, `middle_name`, `nickname`, `birthdate`, `birthplace_city`, `birthplace_province` | 1a |
| 2 | Personal: address & contact | `applicant` | `address1`, `address2`, `address3`, `length_of_stay`, `housing_status`, `cell_no` | 1b |
| 3 | Personal: family & spouse | `applicant` | `civil_status`, `educational_attainment`, `number_of_children`, `spouse_name`, `spouse_age`, `spouse_cell_no` | 1c |
| 4 | Work: employment | `applicant` | `employment_type`, `employer_business_name`, `employer_business_address1/2/3` (all employer fields hidden when Pensioner) | 2a |
| 5 | Work: income & details | `applicant` | `telephone_no`, `current_position`, `nature_of_business`, `years_in_work_business` (these 4 hidden when Pensioner), `gross_monthly_income`, `payday` | 2b |
| 6 | Co-maker 1: personal | `co_maker_1` | Full `LoanRequestPersonalFields` (no spouse/children/civil-housing): names, birth, address block, `length_of_stay`, `cell_no`, `educational_attainment` | 3a |
| 7 | Co-maker 1: work & income | `co_maker_1` | Full `LoanRequestWorkFields` + "Physical signatures" alert | 3b |
| 8 | Co-maker 2: personal | `co_maker_2` | Full `LoanRequestPersonalFields` | 4a |
| 9 | Co-maker 2: work & income | `co_maker_2` | Full `LoanRequestWorkFields` + "Physical signatures" alert | 4b |
| 10 | Insurance & beneficiaries | `insurance` | (unchanged) | 5 |
| 11 | Health declarations | `health` | (unchanged) | 6 |
| 12 | Bank & payout | `banking` | (unchanged) | 7 |
| 13 | Barangay information | `barangay` | (unchanged) | 8 |
| 14 | Declarations | `declarations` | (unchanged) | 9 |
| 15 | Review | (review) | Review + `undertaking_accepted` | 10 |

**Old → New index shift (for anything referencing old indices):** 0→0; 1→(1,2,3); 2→(4,5); 3→(6,7); 4→(8,9); 5→10; 6→11; 7→12; 8→13; 9→14; 10→15.

---

## 2. Step Components — approach

**Chosen: Option (b) — add a `section` prop to the existing field components and step wrappers.** No new files.

Rationale: `LoanRequestPersonalFields` and `LoanRequestWorkFields` are **single components with internal `<Separator>`-delimited blocks** that already match the desired sub-steps. They also own React hooks at the top (`useLocationSearch`, `useMemo`, `useState`). Splitting into separate components would duplicate those hooks and the location-autocomplete wiring. Adding a `section` prop that gates which block(s) render keeps all hooks unconditional (no hooks-order violation) and reuses everything. Lowest risk.

### 2a. `resources/js/components/loan-request/loan-request-fields.tsx`

**`LoanRequestPersonalFields`** — add prop:
```ts
section?: 'all' | 'basic' | 'contact' | 'family'; // default 'all'
```
The component has exactly three separator-delimited blocks. Gate them:
- **basic** block (names/nickname/birthdate/birthplace) → render when `section === 'all' || section === 'basic'`
- **contact** block (address1/2/3, `length_of_stay`, `housing_status`, `cell_no`) → `'all' || 'contact'`
- **family** block (`civil_status`, `educational_attainment`, `number_of_children`, spouse group) → `'all' || 'family'`
- Render the two `<Separator>`s **only when `section === 'all'`** (so single-block sub-steps have no dangling separators).
- Leave all hooks and `include*` props unchanged — they still run; irrelevant fields simply aren't rendered.

**`LoanRequestWorkFields`** — add prop:
```ts
section?: 'all' | 'employment' | 'income'; // default 'all'
```
Three blocks:
- **block 1** = `employment_type` + employer name/address (already `!isPensioner`-gated per field) → render when `'all' || 'employment'`
- **block 2** = `telephone_no`, `current_position`, `nature_of_business`, `years_in_work_business` (wrapped in `!isPensioner`) → render when `'all' || 'income'`
- **block 3** = `gross_monthly_income`, `payday` → render when `'all' || 'income'`
- Leading `<Separator>` before block 3 (and block 2's separator) render only when the preceding block is also visible — i.e. gate separators by section so `income` doesn't start with a stray separator. Keep the `isPensioner` conditionals exactly as-is inside each block.

### 2b. `resources/js/components/loan-request/loan-request-steps.tsx`

**`LoanRequestApplicantPersonalStep`** — add `section: 'basic' | 'contact' | 'family'` prop. Derive card title/description from a small internal map, pass `section` through to `LoanRequestPersonalFields`. Keep `includeSpouse includeChildren includeCivilHousing` all set (component now gates by section). Keep passing `readOnly`.
Suggested titles: basic → "My personal data" / "Confirm your basic personal details."; contact → "Address & contact" / "Confirm your address and contact details."; family → "Family & background" / "Confirm civil status, education, and family details."

**`LoanRequestApplicantWorkStep`** — add `section: 'employment' | 'income'` prop; internal title/desc map; pass `section` to `LoanRequestWorkFields`. **Render the "Physical signatures" `<Alert>` only when `section === 'income'`** (last work sub-step) to avoid duplication.

**`LoanRequestCoMakerStep`** — add `section: 'personal' | 'work'` prop (keep existing `title`/`description`/`prefix`/`values`/`errors`/`onChange`). When `'personal'` render `LoanRequestPersonalFields` (default `section='all'`, no includes — co-maker). When `'work'` render `LoanRequestWorkFields` (`section='all'`) **plus** the "Physical signatures" `<Alert>`. Drop the internal separators that previously joined personal+work in one card.

---

## 3. `steps` array in `loan-request.tsx` (lines 63–119)

Replace the 11-entry array with 16 entries, in this exact order:

| idx | id | title | description |
|---|---|---|---|
| 0 | `loan-details` | Loan details | Set the loan type, amount, term, and purpose. |
| 1 | `personal-basic` | Personal: basic info | Confirm your basic personal information. |
| 2 | `personal-contact` | Personal: address & contact | Confirm your address and contact details. |
| 3 | `personal-family` | Personal: family & spouse | Confirm civil status, education, and family details. |
| 4 | `work-employment` | Work: employment | Share your employment and employer details. |
| 5 | `work-income` | Work: income & details | Share your income, position, and business details. |
| 6 | `co-maker-1-personal` | Co-maker 1: personal | Personal details for your first co-maker. |
| 7 | `co-maker-1-work` | Co-maker 1: work & income | Work and income details for your first co-maker. |
| 8 | `co-maker-2-personal` | Co-maker 2: personal | Personal details for your second co-maker. |
| 9 | `co-maker-2-work` | Co-maker 2: work & income | Work and income details for your second co-maker. |
| 10 | `insurance` | Insurance & beneficiaries | Provide beneficiary details required for document generation. |
| 11 | `health` | Health declarations | Complete the required health declarations for the request. |
| 12 | `banking` | Bank & payout | Provide the payout bank and account information. |
| 13 | `barangay` | Barangay information | Provide the barangay details required for the forms. |
| 14 | `declarations` | Declarations | Review the required declarations and consent statements. |
| 15 | `review` | Review | Review and confirm the undertaking. |

**No other change needed to step math:** `isLastStep`, `handleNextStep`, `handlePreviousStep`, and `highestStepReached` are all derived from `steps.length` (no hardcoded max). They automatically become 0–15.

---

## 4. AnimatedStep blocks in `loan-request.tsx` (lines 598–783)

Rebuild the block list to **16 blocks**. Split/duplicated blocks flagged:

| `show={currentStep === N}` | Component | Key props |
|---|---|---|
| 0 | `LoanRequestLoanDetailsStep` | (unchanged) |
| 1 | `LoanRequestApplicantPersonalStep` | `section="basic"`, pass `readOnly` |
| 2 | `LoanRequestApplicantPersonalStep` | `section="contact"`, pass `readOnly` |
| 3 | `LoanRequestApplicantPersonalStep` | `section="family"`, pass `readOnly` |
| 4 | `LoanRequestApplicantWorkStep` | `section="employment"` |
| 5 | `LoanRequestApplicantWorkStep` | `section="income"` |
| 6 | `LoanRequestCoMakerStep` | `prefix="co_maker_1"` `section="personal"` title "Co-maker 1 — personal" |
| 7 | `LoanRequestCoMakerStep` | `prefix="co_maker_1"` `section="work"` title "Co-maker 1 — work & income" |
| 8 | `LoanRequestCoMakerStep` | `prefix="co_maker_2"` `section="personal"` title "Co-maker 2 — personal" |
| 9 | `LoanRequestCoMakerStep` | `prefix="co_maker_2"` `section="work"` title "Co-maker 2 — work & income" |
| 10 | `LoanRequestInsuranceBeneficiariesStep` | (was `=== 5`) |
| 11 | `LoanRequestDataSectionStep` health | (was `=== 6`) |
| 12 | `LoanRequestDataSectionStep` banking | (was `=== 7`) |
| 13 | `LoanRequestDataSectionStep` barangay | (was `=== 8`) |
| 14 | `LoanRequestDataSectionStep` declarations | (was `=== 9`) |
| 15 | `LoanRequestReviewStep` | (was `=== 10`) |

> **Duplicated blocks:** steps 1–3 all render `LoanRequestApplicantPersonalStep` (same `values={form.data.applicant}`, `onChange={updatePersonField('applicant')}`) differing only by `section`. Steps 4–5 both render `LoanRequestApplicantWorkStep` (applicant). Steps 6/7 and 8/9 each render `LoanRequestCoMakerStep` for the same prefix, differing by `section`. Because they share `values`/`onChange`, edits persist across sub-steps automatically (single `form.data` source).

---

## 5. `resolveStepFromErrors` in `loan-request.tsx` (lines 259–333)

Replace the single `applicantPersonalFields`/`applicantWorkFields` sets (lines 128–162) with **five partition sets** matching the sub-steps, then remap branches.

```ts
const applicantBasicFields = new Set([
  'first_name','last_name','middle_name','nickname',
  'birthdate','birthplace_city','birthplace_province',
]);
const applicantContactFields = new Set([
  'address1','address2','address3','length_of_stay','housing_status','cell_no',
]);
const applicantFamilyFields = new Set([
  'civil_status','educational_attainment','number_of_children',
  'spouse_name','spouse_age','spouse_cell_no',
]);
const personWorkFields = new Set([ // shared by applicant + co-makers
  'employment_type','employer_business_name',
  'employer_business_address1','employer_business_address2','employer_business_address3',
  'telephone_no','current_position','nature_of_business',
  'years_in_work_business','gross_monthly_income','payday',
]);
const applicantEmploymentFields = new Set([
  'employment_type','employer_business_name',
  'employer_business_address1','employer_business_address2','employer_business_address3',
]);
```

Branch remap (old push → new push):

| Key prefix / key | Old step | New step logic |
|---|---|---|
| loan detail fields (`typecode`… `availment_status`) | 0 | **0** (unchanged) |
| `applicant.<field>` | 1 or 2 | basic→**1**, contact→**2**, family→**3**, employment→**4**, else (income work fields)→**5**; fallback→**1** |
| `co_maker_1.<field>` | 3 | `personWorkFields.has(field)` ? **7** : **6** |
| `co_maker_2.<field>` | 4 | `personWorkFields.has(field)` ? **9** : **8** |
| `insurance.*` / `document_data` | 5 | **10** |
| `health.*` | 6 | **11** |
| `banking.*` | 7 | **12** |
| `barangay.*` | 8 | **13** |
| `declarations.*` | 9 | **14** |
| `undertaking_accepted` | 10 | **15** |

Applicant resolution helper:
```ts
if (key.startsWith('applicant.')) {
  const field = key.replace('applicant.', '');
  stepMatches.push(
    applicantBasicFields.has(field) ? 1
    : applicantContactFields.has(field) ? 2
    : applicantFamilyFields.has(field) ? 3
    : applicantEmploymentFields.has(field) ? 4
    : personWorkFields.has(field) ? 5
    : 1,
  );
  return;
}
```
(The existing `Math.min(...stepMatches)` behavior — jump to the earliest erroring step — is preserved.)

---

## 6. `STEP_GROUPS` + `TOTAL_STEPS` in `loan-request-step-indicator.tsx`

Replace `STEP_GROUPS` (lines 20–57) and `TOTAL_STEPS` (line 59):

```ts
const STEP_GROUPS: StepGroup[] = [
  { label: 'Loan details', icon: FileText, steps: [0], stepNames: ['Loan details'] },
  { label: 'About you', icon: User,
    steps: [1, 2, 3, 4, 5],
    stepNames: ['Personal: basic info', 'Personal: address & contact',
      'Personal: family & spouse', 'Work: employment', 'Work: income & details'] },
  { label: 'Co-makers', icon: Users,
    steps: [6, 7, 8, 9],
    stepNames: ['Co-maker 1: personal', 'Co-maker 1: work & income',
      'Co-maker 2: personal', 'Co-maker 2: work & income'] },
  { label: 'Insurance & health', icon: HeartPulse,
    steps: [10, 11],
    stepNames: ['Insurance & beneficiaries', 'Health declarations'] },
  { label: 'Bank & payout', icon: Building2,
    steps: [12, 13],
    stepNames: ['Bank & payout', 'Barangay information'] },
  { label: 'Declarations & review', icon: ClipboardCheck,
    steps: [14, 15],
    stepNames: ['Declarations', 'Review & submit'] },
];

const TOTAL_STEPS = 16;
```
The group render logic (`isDone`/`isActive`/`isSubDone`) is index-driven and needs no other change. `stepNames[i]` aligns to `steps[i]` — keep both arrays the same length per group.

---

## 7. Validation & service clamps — **`max:10` → `max:15`**  ⚠️ includes an easily-missed file

| File | Line | Change |
|---|---|---|
| `app/Http/Requests/Client/SaveDraftRequest.php` | 92 | `'wizard_step' => ['sometimes','nullable','integer','min:0','max:15']` |
| `app/Http/Requests/Client/LoanRequestDraftRequest.php` | 87 | same → `max:15` |
| **`app/Services/LoanRequests/LoanRequestService.php`** | **123** | `$initialStep = max(0, min(15, (int) $stepValue));` (resume clamp) |
| **`app/Services/LoanRequests/LoanRequestService.php`** | **179** | `$stepValue = max(0, min(15, (int) $payload['wizard_step']));` (persist clamp) |

> **CRITICAL — silent trap.** `LoanRequestService.php` hard-clamps `wizard_current_step` to `min(10, …)` on **both** save (line 179) and resume (line 123). If left at 10, any draft on new steps 11–15 saves/resumes as step 10 — with **no** TypeScript error and **no** test failure. Both must become 15.

---

## 8. Tests

**Existing — `tests/Feature/DraftAutoSaveTest.php`:** the hardcoded `wizard_step => 5` (line 190) and `initialStep => 5` (lines 208–222) remain **valid** (5 ≤ 15) — no change required. The `max:10`-boundary was never asserted, so nothing breaks.

**Add (CLAUDE.md requires a Pest test per change) — new cases in `DraftAutoSaveTest.php`:**
1. `save draft with wizard_step 15 persists 15` — PATCH `save-draft` with `wizard_step => 15`, assert the `wizard_current_step` data entry `value_json['value'] === 15`. Guards the service persist-clamp bump (179) and the request `max:15` rule.
2. `save draft clamps wizard_step above max to 15` — send `wizard_step => 20`; because `max:15` validation rejects >15, assert 422 **or** (if sent to the `draft()` endpoint which is lenient) assert the stored value is 15. Pick per which endpoint enforces the rule.
3. `create page resumes initialStep 15` — seed a `wizard_current_step` data entry `['value' => 15]`, GET `create`, assert Inertia `initialStep === 15`. Guards the resume-clamp bump (123).

**Frontend:** no component/unit test harness exists for the wizard today; rely on `npm run build`/`tsc` + the manual verification checklist below. (If Vitest exists, a small `resolveStepFromErrors` unit test mapping representative keys → new indices is a good add, but not required.)

---

## 9. Risk Assessment

**Highest risk — off-by-one renumbering (precedent: a prior session's step removal caused a silent AnimatedStep off-by-one crash). This change adds 5 steps → risk is higher.**

Silent-failure hotspots (no TS error, no test failure, just wrong navigation/data):
1. **`LoanRequestService.php` `min(10,…)` clamps (lines 123, 179).** Not TS, not covered by an assertion today. The single most likely miss. → covered by the new tests in §8.
2. **`AnimatedStep` `show={currentStep === N}` values (§4).** A wrong `N` renders a blank step or the wrong form silently. Verify every block against §4; the sidebar "Step N of 16" must match the visible form.
3. **`resolveStepFromErrors` field→sub-step sets (§5).** If a field lands in the wrong set, submit-validation jumps the user to the wrong sub-step (error appears "on a step with no such field"). Every person field must be in exactly one applicant set; work fields shared with co-makers via `personWorkFields`.
4. **`STEP_GROUPS.steps` vs `stepNames` length mismatch (§6).** Off-by-one here mislabels or drops a sub-step name in the sidebar (`stepNames[i]` undefined). Keep arrays equal length; indices must be contiguous 0–15 across all groups with none repeated/missing.
5. **`TOTAL_STEPS` (§6).** If left at 11, the progress bar and "Step x of 11" are wrong even though navigation works.
6. **Separator gating in field components (§2a).** A stray `<Separator>` at the top/bottom of a single-block sub-step is cosmetic but signals the block gating is off — check basic/contact/family and employment/income render cleanly with no leading/trailing rule.
7. **Duplicated "Physical signatures" alert.** Ensure it renders once (applicant: only on step 5; co-maker: only on the `work` sub-step).

**Not in scope (do not touch):** `resources/js/components/loan-request/admin-loan-request-correction-dialog.tsx` has its **own** independent 5-step (`WIZARD_STEPS`, 0–4) admin-correction wizard. It is unrelated to the client wizard and must remain unchanged.

**Cross-checks to run:** `npx tsc --noEmit` (or `npm run build`), `vendor/bin/pint --dirty`, `php artisan test --filter=DraftAutoSave`.

---

## 10. Manual Verification Checklist (0–15)

Open the client loan-request form (new draft). Sidebar footer must read **"Step 1 of 16"** at start. Navigate with Next and confirm each step shows exactly the fields below, **fits without vertical scrolling**, and the sidebar sub-item highlights correctly.

| Step | Sidebar group ▸ sub-item | Must see (and only these fields) |
|---|---|---|
| 0 | Loan details ▸ Loan details | Loan type, Requested amount, Loan term, Availment status, Loan purpose |
| 1 | About you ▸ Personal: basic info | First/Last/Middle name, Nickname, Birthdate, Birthplace city, Birthplace province |
| 2 | About you ▸ Personal: address & contact | Address (street), City/Municipality, Province, Length of stay, Housing status, Cell no. |
| 3 | About you ▸ Personal: family & spouse | Civil status, Educational attainment, No. of children, Spouse name, Spouse age, Spouse cell no. |
| 4 | About you ▸ Work: employment | Employment type + (if not Pensioner) Employer/Business name, Employer address street/city/province. **Set Employment = Pensioner → employer fields disappear, only Employment type remains.** |
| 5 | About you ▸ Work: income & details | (if not Pensioner) Tel. no., Current position, Nature of business, Total years in work/business; always Gross monthly income, Payday; "Physical signatures" note once |
| 6 | Co-makers ▸ Co-maker 1: personal | Co-maker 1 names, nickname, birthdate, birthplace, address block, length of stay, cell no., educational attainment (no spouse/children/civil/housing) |
| 7 | Co-makers ▸ Co-maker 1: work & income | Co-maker 1 employment + income fields + "Physical signatures" note |
| 8 | Co-makers ▸ Co-maker 2: personal | Same field set as step 6, for co-maker 2 |
| 9 | Co-makers ▸ Co-maker 2: work & income | Same field set as step 7, for co-maker 2 |
| 10 | Insurance & health ▸ Insurance & beneficiaries | Primary + secondary beneficiary fields |
| 11 | Insurance & health ▸ Health declarations | Smoker, hypertension, diabetes, recent hospitalization, notes |
| 12 | Bank & payout ▸ Bank & payout | Payout bank/account fields, release method, ATM, branch, holder name |
| 13 | Bank & payout ▸ Barangay information | Barangay name/reference/locality/designation/agency fields |
| 14 | Declarations & review ▸ Declarations | Existing loans, pending cases, truth confirmation, data-privacy consent |
| 15 | Declarations & review ▸ Review & submit | Full read-only summary of steps 0–14 + Undertaking checkbox; footer shows **"Step 16 of 16"**; primary button = Submit |

**Cross-cutting checks:**
- Type data on step 1, go to step 3 and back to 1 → values persist (shared `form.data.applicant`).
- On step 15, submit with a deliberately blank required field belonging to step 2 (e.g. clear `cell_no`) → wizard jumps to **step 2** and shows the error (validates `resolveStepFromErrors`).
- Save a draft on step 12, reload the page → resumes at step 12 (validates the `min(15,…)` service clamps in §7).
- Sidebar "Step N of 16" number always equals the visible step index + 1.

---

## Files touched (summary)
- `resources/js/pages/client/loan-request.tsx` — steps array (§3), AnimatedStep blocks (§4), `resolveStepFromErrors` + field sets (§5)
- `resources/js/components/loan-request/loan-request-steps.tsx` — `section` props on applicant personal/work + co-maker step wrappers (§2b)
- `resources/js/components/loan-request/loan-request-fields.tsx` — `section` prop gating on `LoanRequestPersonalFields` + `LoanRequestWorkFields` (§2a)
- `resources/js/components/loan-request/loan-request-step-indicator.tsx` — `STEP_GROUPS` + `TOTAL_STEPS=16` (§6)
- `app/Http/Requests/Client/SaveDraftRequest.php` — `max:15` (§7)
- `app/Http/Requests/Client/LoanRequestDraftRequest.php` — `max:15` (§7)
- `app/Services/LoanRequests/LoanRequestService.php` — `min(15,…)` ×2 (§7) ⚠️ *easily missed*
- `tests/Feature/DraftAutoSaveTest.php` — new max-boundary/resume tests (§8)

Finish with `vendor/bin/pint --dirty`, `npx tsc --noEmit`, `php artisan test --filter=DraftAutoSave`.
