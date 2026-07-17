# WIBS Portal — Document Field Map
> **Purpose:** Per-document breakdown of every field that prints on each of the 10 MRDINC
> loan documents, who enters it (Member wizard vs. Staff processing), its current wiring
> status, and which data source in the app provides it.
>
> **Use this file to:** guide PDF/Blade template wiring, spot gaps before WIBS meeting,
> and drive Claude Code tasks.

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Wired — collected AND printed correctly |
| ⚠️ | **Wiring gap** — collected by the app but NOT yet printed on the document |
| ❌ | **Missing** — document needs it but the app doesn't collect it yet |
| 🗑️ | Removed — confirmed deleted from app and absent from all real MRDINC documents |
| ❓ | Pending confirmation before wiring or adding |

**Who enters:**
- **M** = Member (11-step loan wizard, `loan_requests` / `loan_request_people` / `loan_request_data_entries`)
- **S** = Staff (processing screen, `loan_request_data_entries owner=staff` / `approved_*` / `recommended_*`)
- **SYS** = System-derived (computed from existing data — no new field needed)

---

## Document Index

| # | Code | Document | Template type | Current format |
|---|------|----------|---------------|----------------|
| 1 | AF | Application Form | Blade template | PDF (Browsershot) |
| 2 | GL | Grepalife / Sun Life | PDF field map | PDF |
| 3 | AU | Affidavit of Undertaking | PDF field map | PDF |
| 4 | AZ | Authorization | PDF field map | PDF |
| 5 | LI | Loan Information | Blade/PDF service | PDF |
| 6 | PP | Plan of Payment | PDF service class | PDF |
| 7 | DS | Disclosure Statement | Blade template | PDF (Browsershot/DomPDF) |
| 8 | PN | Promissory Note | Blade template | PDF (Browsershot) ⚠️ not yet visually verified |
| 9 | UB | Undertaking-Barangay | PDF field map | PDF |
| 10 | LSA | Loan Security Agreement | Blade template | PDF (Browsershot) |

---

---

## 1 — Application Form (AF)

**Blade template · Staff reviews, borrower signs**

### Loan Selection

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Loan type | M | ✅ | `loan_requests.loan_type` |
| Loan purpose | M | ✅ | `loan_requests.loan_purpose` |
| Availment status (New / Re-loan / Re-structured) | M | ✅ | `loan_requests.availment_status` |

### Personal Data — Borrower

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| First / Middle / Last name | M | ✅ | `loan_request_people` (borrower) |
| Nickname | M | ✅ | `loan_request_data_entries` |
| Sex | M | ✅ | `loan_request_data_entries` |
| Nationality | M | ✅ | `loan_request_data_entries` |
| Birthdate | M | ✅ | `loan_request_people.birthdate` |
| Birth place | M | ✅ | `loan_request_data_entries` |
| Civil status | M | ✅ | `loan_request_data_entries` |
| No. of children | M | ✅ | `loan_request_data_entries` |
| Educational attainment | M | ✅ | `loan_request_data_entries` |
| Residence address | M | ✅ | `loan_request_data_entries` |
| City / Province / Country / ZIP | M | ✅ | `loan_request_data_entries` |
| Length of stay at residence | M | ✅ | `loan_request_data_entries` |
| Housing status (Owned / Rent) | M | ✅ | `loan_request_data_entries` |
| Cell number | M | ✅ | `loan_request_data_entries` |
| Home phone | M | ✅ | `loan_request_data_entries` |
| Email address | M | ✅ | `loan_request_data_entries` |
| Spouse name | M | ✅ | `loan_request_data_entries` (if married) |
| Spouse age | M | ✅ | `loan_request_data_entries` (if married) |
| Spouse cell number | M | ✅ | `loan_request_data_entries` (if married) |

### Work & Finances — Borrower

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Employment type (Private / Government / Self-employed / Retired / Pensioner / OFW) | M | ✅ | `loan_request_data_entries` |
| Employer / Business name | M | ✅ | `loan_request_data_entries` |
| Office address | M | ✅ | `loan_request_data_entries` |
| Office city / province / country / ZIP | M | ✅ | `loan_request_data_entries` |
| Current position | M | ✅ | `loan_request_data_entries` |
| Office telephone number | M | ✅ | `loan_request_data_entries` |
| Nature of business | M | ✅ | `loan_request_data_entries` |
| Years in work / business | M | ✅ | `loan_request_data_entries` |
| Gross monthly income | M | ✅ | `loan_request_data_entries` |
| Payday | M | ✅ | `loan_request_data_entries` |

> **Employment type rules (resolved):** Private / Government / Self-employed / Retired / Pensioner / OFW.
> Pensioner hides employer and office fields in the wizard (implemented). OFW keeps all employer fields.

### Co-Maker 1

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Name | M | ✅ | `loan_request_people` (co_maker_1) |
| Nickname | M | ✅ | `loan_request_data_entries` |
| Birthdate | M | ✅ | `loan_request_people.birthdate` |
| Birth place | M | ✅ | `loan_request_data_entries` |
| Address | M | ✅ | `loan_request_data_entries` |
| Length of stay | M | ✅ | `loan_request_data_entries` |
| Cell number | M | ✅ | `loan_request_data_entries` |
| Educational attainment | M | ✅ | `loan_request_data_entries` |
| Employment type | M | ✅ | `loan_request_data_entries` |
| Employer / business name + address | M | ✅ | `loan_request_data_entries` |
| Position | M | ✅ | `loan_request_data_entries` |
| Nature of business | M | ✅ | `loan_request_data_entries` |
| Years in work / business | M | ✅ | `loan_request_data_entries` |
| Gross monthly income | M | ✅ | `loan_request_data_entries` |
| Payday | M | ✅ | `loan_request_data_entries` |
| Civil status | 🗑️ | 🗑️ | **Removed** — AF has no co-maker civil status field |
| Housing status | 🗑️ | 🗑️ | **Removed** — AF has no co-maker housing field |

### Co-Maker 2

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| *(same fields as Co-Maker 1 except:)* | | | |
| Civil status | 🗑️ | 🗑️ | **Removed** |
| Housing status | 🗑️ | 🗑️ | **Removed** |

### Beneficiaries

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Beneficiary 1: Name | M | ✅ | `loan_request_data_entries` |
| Beneficiary 1: Birthdate | M | ✅ | `loan_request_data_entries` |
| Beneficiary 1: Relationship | M | ✅ | `loan_request_data_entries` |
| Beneficiary 2: Name | M | ✅ | `loan_request_data_entries` |
| Beneficiary 2: Birthdate | M | ✅ | `loan_request_data_entries` |
| Beneficiary 2: Relationship | M | ✅ | `loan_request_data_entries` |

### Staff / Approval Section

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Recommended by + date | S | ✅ | `loan_requests.recommended_by` + `recommended_at` |
| Approved by + date | S | ✅ | `loan_requests.approved_by` + `approved_at` |
| Application status | S | ✅ | `loan_requests.status` |

---

---

## 2 — Grepalife / Sun Life (GL)

**PDF field map · "Debtor's Creditor Group Life" variant · Insurance form for borrower coverage**

> Health answer checkboxes (Q1–Q4) are wired via `healthChecked()` factory. The x/y coordinates
> carry a `TODO(calibrate-gl)` tag and must be verified against the physical form overlay before
> printing. Note: field key is `health_recent_hospitalization` (not `health_hospitalization`) in
> the GL map.

### Personal Data

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Residence address | M | ✅ | `loan_request_data_entries` |
| City / Province | M | ✅ | `loan_request_data_entries` |
| Country | M | ✅ | `applicant.address_country` (defaults to "Philippines" if null) |
| ZIP | M | ✅ | `applicant.address_zip` |
| Birthdate | M | ✅ | `loan_request_people.birthdate` |
| Civil status | M | ✅ | `loan_request_data_entries` |
| Nationality | M | ✅ | `loan_request_data_entries` |
| Home phone | M | ✅ | `applicant.home_phone` |
| Email address | M | ✅ | `applicant.email` |

### Beneficiaries

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Beneficiary 1: Name | M | ✅ | `loan_request_data_entries` |
| Beneficiary 1: Birthdate | M | ✅ | `loan_request_data_entries` |
| Beneficiary 1: Relationship | M | ✅ | `loan_request_data_entries` |
| Beneficiary 2: Name | M | ✅ | `loan_request_data_entries` |
| Beneficiary 2: Birthdate | M | ✅ | `loan_request_data_entries` |
| Beneficiary 2: Relationship | M | ✅ | `loan_request_data_entries` |

### Section 2 — Health Questionnaire

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Q1: Smoker (Yes/No) | M | ✅ | `health.health_smoker` via `healthChecked()` ⚠️ coordinates need calibration |
| Q2: Hypertension (Yes/No) | M | ✅ | `health.health_hypertension` via `healthChecked()` ⚠️ coordinates need calibration |
| Q3: Diabetes (Yes/No) | M | ✅ | `health.health_diabetes` via `healthChecked()` ⚠️ coordinates need calibration |
| Q4: Recent hospitalization (Yes/No) | M | ✅ | `health.health_recent_hospitalization` via `healthChecked()` ⚠️ coordinates need calibration |
| Physician name | M | ❌ | **MISSING** — not in GL field map; not in FIELD_DEFINITIONS (app does not collect) |
| Physician address | M | ❌ | **MISSING** — same as above |
| Date seen | M | ❌ | **MISSING** — same as above |
| Treatment received | M | ❌ | **MISSING** — same as above |

> Physician fields appear on the GL form when any health answer is Yes. Neither the wizard nor
> `FIELD_DEFINITIONS` currently collects them, and they are absent from `GrepalifePdfFieldMap`.
> Must add to both the wizard (conditional health section) and the field map.

### Insurance Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Insurance rate | S | ✅ | `loan_request_data_entries` (staff) |
| Insurance term (max 12 months) | S | ✅ | `loan_request_data_entries` (staff) |

---

---

## 3 — Affidavit of Undertaking (AU)

**PDF field map · Borrower swears not to interfere with the salary-deduction/ATM authority securing the loan — single affiant signature, no co-maker/witness role**

**Rebuilt from scratch against the real `AFFIDAVIT OF UNDERTAKING (1).docx` reference** (a
prior pass had inspected a different, misleadingly-named file and produced several inaccurate
rows below — corrected here). The base PDF template, field-map coordinates, and the 8 new
notarization fields all shipped together; see git history on
`app/Services/LoanRequests/PdfFieldMaps/AffidavitUndertakingPdfFieldMap.php` for the 3-commit
rebuild (artwork → applicant fields → notarization fields), followed by a correction commit that
fixed a scope error in the notarization phase — see the Notarization sub-table below.

### Borrower Identification

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `applicant.full_name` |
| Age | M | ✅ | `applicant.age` — derived from `birthdate` at render time |
| Civil status | M | ✅ | `applicant.civil_status` |
| Nationality | M | ✅ | `applicant.nationality` — always `FILIPINO` |
| Residence address | M | ✅ | `applicant.address` |
| Designation | M | ✅ | `applicant.position_or_designation` |
| Agency name | M | ✅ | `applicant.employer_or_business` |
| Agency address | M | ✅ | `applicant.office_address` |

### Bank / Payout Details

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Bank name | S | ✅ | `authorization.payout_bank_name` |
| Account number | S | ✅ | `authorization.payout_account_number` |
| Account name | S | ❌ | Not present on the real AU form (the deposit account is already the affiant's own, named in the header table) — **not wired**, and should not be |
| ATM account number | S | ✅ | `authorization.payout_atm_number` |
| Bank branch | S | ✅ | `authorization.payout_bank_branch` — calibrated coordinates, no longer a placeholder |

### Loan Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Approved loan amount | S | ❌ | Not present on the real AU form (the undertaking references GNTHP, not a specific peso loan amount) — **not wired**, and should not be |
| Guaranteed Net Take-Home Pay (GNTHP) | S | ✅ | `loan.gnthp` — staff-entered, wired to AU |

### Notarization

**Corrected twice — the Phase 3 commit shipped this section against the wrong design (all 8
fields as per-loan staff UI inputs), and a follow-up correction dropped `valid_id_number` as
well. No field in this section has a staff UI input; the table below reflects that end state.**

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Place of signing | — | ✅ | Not staff data — the notary's own fixed office fact. `OrganizationSetting.business_address2` (city) → `notarial.signing_place` |
| Notarial province | — | ✅ | Not staff data — fixed office fact. `OrganizationSetting.business_address3` (province) → `notarial.province` |
| Valid ID number | — | ❌ | **Not wired, intentionally.** Left blank on the artwork for the notary to fill by hand |
| Valid ID issued at | — | ✅ | Not staff data — fixed office fact, same source as place of signing. `OrganizationSetting.business_address2` (city) → `notarial.valid_id_issued_at` |
| Document number | — | ❌ | **Not wired, intentionally.** The notary's own private register counter — unknowable to WIBS staff. Phase 1's blank space on the artwork is reserved for the notary to fill by hand |
| Page number | — | ❌ | Same as Document number — notary fills by hand |
| Book number | — | ❌ | Same as Document number — notary fills by hand |
| Series year | — | ✅ | Computed, not staff-entered — the calendar year the document is notarized is always derivable, unlike Doc/Page/Book No. Derived from the document date (`approved_at`/`reviewed_at` fallback chain) → `notarial.series_year` |

> **Follow-up (flagged, not yet built):** `signing_place` / `notarial_province` / `valid_id_issued_at`
> currently read `OrganizationSetting.business_address2/3` directly — the same columns that already
> back the report header address elsewhere in the app. There is no dedicated admin-only "notarial
> office address" setting; if the org's notarial venue should ever differ from its general business
> address, that would need its own settings field. Not built here — out of scope for this correction.

**Correction to the original Phase 3 commit:** the original Phase 3 shipped all 8 fields as
per-loan staff UI inputs — a scope error. `doc_number`/`page_number`/`book_number` are the
notary's own private register counters (not app data at all — left blank on the artwork),
`series_year` is computed from the document date, and `signing_place`/`notarial_province`/
`valid_id_issued_at` are fixed org facts read from `OrganizationSetting`, not per-loan input.
`valid_id_number` was initially kept as genuine per-loan staff data, but a follow-up correction
dropped it too — no `FIELD_DEFINITIONS` entry, no validation rule, no staff UI, no
`buildDocumentData()` wiring, no AU field-map entry. It is now left blank on the artwork for the
notary to fill by hand, same as Doc/Page/Book No.

Witnesses are **not part of this document** — the real AU form has a single affiant/borrower
signature line, not a witness block. A prior pass claimed `witness_one_name`/`witness_two_name`
were wired to the AU field map; they never were (confirmed by direct inspection of
`AffidavitUndertakingPdfFieldMap.php`) and the row has been removed rather than corrected in
place, since it does not belong on this document at all.

---

---

## 4 — Authorization (AZ)

**PDF field map · Authorises release of loan proceeds to the borrower (borrower's own proceeds only)**

> **Why the Authorized Recipient section was removed:** Third-party loan release is always
> handled physically via a separate authorization letter prepared on release day — it is never
> sourced from app data. The wizard's "Authorization & release" step (formerly step 12) has
> been removed entirely; the wizard is now 11 steps.
>
> **`applicable()` gate:** AZ is generated when `payout_bank_name` OR `payout_account_number`
> is non-empty. No longer gated on recipient name, relationship, or release method.
>
> **Bank name:** AZ now uses the member's entered `payout_bank_name` — the hardcoded
> `"Enterprise Bank, Inc."` was removed.

### Borrower

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Residence address | M | ✅ | `loan_request_data_entries` |

### Bank / Payout Details

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Release method | M | ✅ | `authorization.release_method` — describes how member receives own proceeds (e.g. "Bank transfer", "ATM"); relocated from banking wizard step |
| Bank name | M | ✅ | `authorization.payout_bank_name` — member's entered bank |
| Account number | M | ✅ | `authorization.payout_account_number` |
| Bank branch | M | ✅ | `authorization.payout_bank_branch` — wired to AZ field map (coordinates placeholder, `TODO(calibrate-az)`) |
| ATM card holder name | M | ✅ | `authorization.payout_atm_holder_name` — wired to AZ field map (coordinates placeholder, `TODO(calibrate-az)`); nullable — field is skipped when empty (borrower uses their own card) |
| Authorized recipient name | 🗑️ | 🗑️ | **Removed** — third-party release handled via separate physical letter |
| Authorized recipient relationship | 🗑️ | 🗑️ | **Removed** — same reason |
| Authorized recipient contact | 🗑️ | 🗑️ | **Removed** — not on the Authorization document |
| Authorization reason | 🗑️ | 🗑️ | **Removed** — not on any document, gated nothing |

### Witnesses & Notarization

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Witness 1 name | S | ✅ | `loan_request_data_entries.witness_one_name` |
| Witness 2 name | S | ✅ | `loan_request_data_entries.witness_two_name` |
| Notarization fields (doc/page/book/series/place) | S | ✅ | `loan_request_data_entries` |

---

---

## 5 — Loan Information (LI)

**Blade/PDF service · Converted to PDF**

### Borrower Header

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Address | M | ✅ | `loan_request_data_entries` |
| Loan purpose | M | ✅ | `loan_requests.loan_purpose` |

### Financial Terms (Staff-entered)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Approved loan amount | S | ✅ | `loan_request_data_entries` (staff) |
| Interest rate (per annum) | S | ✅ | `loan_request_data_entries` (staff) |
| Term (months) | S | ✅ | `loan_request_data_entries` (staff) |
| Service charge rate | S | ✅ | `loan_request_data_entries` (staff) |
| Insurance rate | S | ✅ | `loan_request_data_entries` (staff) |
| Insurance term (max 12) | S | ✅ | `loan_request_data_entries` (staff) |
| Penalty per month | S | ✅ | `loan_request_data_entries` (staff) |
| Notarial fee | S | ✅ | `loan_request_data_entries` (staff) |
| Mode of payment | S | ✅ | `loan_request_data_entries` (staff) |
| Kind of loan | S | ✅ | `loan_request_data_entries` (staff) |

### Approval Details

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Recommended by + date | S | ✅ | `loan_requests.recommended_by` + `recommended_at` |
| Approved by + date | S | ✅ | `loan_requests.approved_by` → `adminProfile→fullname` (fixed Phase 1) |
| Application status | S | ✅ | `loan_requests.status` |

---

---

## 6 — Plan of Payment (PP)

**PDF service class · Converted to PDF**

### Header

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Borrower full name | M | ✅ | `loan_request_people` (borrower) |
| Address | M | ✅ | `loan_request_data_entries` |
| Loan amount | S | ✅ | `loan_request_data_entries` (staff) |
| Interest rate | S | ✅ | `loan_request_data_entries` (staff) |
| Term (months) | S | ✅ | `loan_request_data_entries` (staff) |
| Mode of payment | S | ✅ | `loan_request_data_entries` (staff) |

### Amortization Schedule (System-generated)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Payment no. | SYS | ✅ | Computed from term |
| Due date | SYS | ✅ | Computed from approval date + mode |
| Principal | SYS | ✅ | Computed |
| Interest | SYS | ✅ | Computed |
| Amortization amount | SYS | ✅ | Computed |
| Balance | SYS | ✅ | Computed |

---

---

## 7 — Disclosure Statement (DS)

**Blade template · Converted to PDF · Governed by R.A. 3765 (Truth in Lending Act) — exact layout required**

> **Open item — EIR rows:** Items 6 (percentage of finance charges) and 7 (effective interest
> rate) are left **blank** in the blade template. `TODO(EIR)` comments in
> `resources/views/reports/disclosure-statement.blade.php` confirm the formula has not been
> confirmed with WIBS and the source workbook also had these blank. Do not compute or wire
> until WIBS confirms the R.A. 3765 formula to use.

### Borrower

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Address | M | ✅ | `loan_request_data_entries` |

### Finance Charges (Staff-entered)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Loan principal | S | ✅ | `loan_request_data_entries` (staff) |
| Interest rate (per annum) | S | ✅ | `loan_request_data_entries` (staff) |
| Interest amount | SYS | ✅ | Computed |
| Service charge | SYS | ✅ | Computed from rate × principal |
| Insurance premium | SYS | ✅ | Computed from rate × principal |
| Notarial fee | S | ✅ | `loan_request_data_entries` (staff) |
| Total finance charge | SYS | ✅ | Sum of above |
| Net loan proceeds (amount released to borrower) | SYS | ✅ | Principal − charges |
| Percentage of finance charges (item 6) | SYS | ❌ | **PENDING** — blank in blade template, `TODO(EIR)`; confirm R.A. 3765 formula with WIBS |
| Effective interest rate (item 7) | SYS | ❌ | **PENDING** — blank in blade template, `TODO(EIR)`; confirm R.A. 3765 formula with WIBS |
| Term (months) | S | ✅ | `loan_request_data_entries` (staff) |
| Mode of payment | S | ✅ | `loan_request_data_entries` (staff) |

### Certified Correct

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Printed name | FIXED | ✅ | Hardcoded `"VELINA P. GAMUTAN"` in `disclosure-statement.blade.php` — not staff-entered or database-derived. Substitute Bookkeeper (Jozel Uriarte) selection is out of scope unless MRDINC requests it. |
| Position | FIXED | ✅ | Hardcoded `"BOOKKEEPER"` in `disclosure-statement.blade.php` |

---

---

## 8 — Promissory Note (PN)

**Blade template · Converted to PDF · ⚠️ NOT yet visually verified against original Excel sheet**

> **ACTION REQUIRED:** Generate a real PN PDF and compare side-by-side against the original
> Excel Promissory Note sheet. No git commit or session-summary file confirming this
> comparison was found. Do not mark as verified until that comparison is done.

### Borrower

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Address | M | ✅ | `loan_request_data_entries` |
| Birthdate | M | ✅ | `loan_request_people.birthdate` |

### Loan Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Loan amount (in figures) | S | ✅ | `loan_request_data_entries` (staff) |
| Loan amount (in words) | SYS | ✅ | Computed from amount |
| Interest rate | S | ✅ | `loan_request_data_entries` (staff) |
| Term (months) | S | ✅ | `loan_request_data_entries` (staff) |
| Approval date | S | ✅ | `loan_requests.approved_at` |

### Signatories

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Loan manager name | S | ✅ | `loan_requests.approved_by→adminProfile→fullname` (fixed Phase 1) |
| Witness 1 name | S | ✅ | `loan_request_data_entries.witness_one_name` (fixed Phase 1) |
| Witness 2 name | S | ✅ | `loan_request_data_entries.witness_two_name` (fixed Phase 1) |

### Notarization

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Document number | S | ✅ | `loan_request_data_entries.doc_number` |
| Page number | S | ✅ | `loan_request_data_entries.page_number` |
| Book number | S | ✅ | `loan_request_data_entries.book_number` |
| Series year | S | ✅ | `loan_request_data_entries.series_year` |
| Place of signing | S | ✅ | `loan_request_data_entries.signing_place` |

---

---

## 9 — Undertaking-Barangay (UB)

**PDF field map · Conditional — applies to any member who supplies barangay data**

> `applicable()` is gated on `barangay_name` OR `barangay_clearance_reference` OR
> `barangay_locality` being non-empty. UB is **not** limited to barangay officials — any
> member may trigger it by providing barangay information in the wizard.

### Affiant (Borrower)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Age | M | ❌ | **DERIVE** from `birthdate` — compute at render time, no new field |
| Civil status | M | ❌ | **REUSE** `loan_request_data_entries.civil_status` — not yet wired to UB |
| Residence address | M | ✅ | `loan_request_data_entries` |

### Barangay Info (conditional step in wizard)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Barangay name | M | ✅ | `barangay.name` |
| Barangay clearance reference | M | ✅ | `barangay.clearance_reference` |
| Barangay locality | M | ✅ | `barangay.locality` |
| Official's designation / position | M | ✅ | `barangay.official_designation` — added and wired |
| Agency name | M | ✅ | `barangay.agency_name` — added and wired |
| Agency address | M | ✅ | `barangay.agency_address` — added and wired |

### Loan & Salary Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Guaranteed Net Take-Home Pay | S | ✅ | `loan.gnthp` — staff-entered, wired to UB |
| Approved loan amount | S | ✅ | `loan_request_data_entries` (staff) |

### Witnesses & Notarization

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Witness 1 name | S | ✅ | `loan_request_data_entries.witness_one_name` |
| Witness 2 name | S | ✅ | `loan_request_data_entries.witness_two_name` |
| Notarization fields (doc/page/book/series/place) | S | ✅ | `loan_request_data_entries` |

---

---

## 10 — Loan Security Agreement (LSA)

**Blade template · Reference pattern for all new PDF services**

### Borrower

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Residence address | M | ✅ | `loan_request_data_entries` |

### Co-Makers

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Co-maker 1 full name | M | ✅ | `loan_request_people` (co_maker_1) |
| Co-maker 1 address | M | ✅ | `loan_request_data_entries` |
| Co-maker 2 full name | M | ✅ | `loan_request_people` (co_maker_2) |
| Co-maker 2 address | M | ✅ | `loan_request_data_entries` |

### Loan Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Loan amount | S | ✅ | `loan_request_data_entries` (staff) |
| Interest rate | S | ✅ | `loan_request_data_entries` (staff) |
| Term (months) | S | ✅ | `loan_request_data_entries` (staff) |
| Mode of payment | S | ✅ | `loan_request_data_entries` (staff) |

### Signatories

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Loan manager name | S | ✅ | `loan_requests.approved_by→adminProfile→fullname` (fixed Phase 1) |
| Certified-correct name + position | S | ✅ | Approver's profile |
| Witness 1 name | S | ✅ | `loan_request_data_entries.witness_one_name` |
| Witness 2 name | S | ✅ | `loan_request_data_entries.witness_two_name` |
| Notarization fields (doc/page/book/series/place) | S | ✅ | `loan_request_data_entries` |

---

---

## Summary: Action Items by Type

### 🗑️ Removed Fields (confirmed deleted — 8 fields total)

| Field | Location | Status |
|-------|----------|--------|
| `co_maker_1.civil_status` | Wizard step, data_entries | ✅ Done |
| `co_maker_1.housing_status` | Wizard step, data_entries | ✅ Done |
| `co_maker_2.civil_status` | Wizard step, data_entries | ✅ Done |
| `co_maker_2.housing_status` | Wizard step, data_entries | ✅ Done |
| `authorization_reason` | Banking wizard step, data_entries | ✅ Done |
| `authorized_recipient_contact` | Banking wizard step, data_entries | ✅ Done |
| `authorized_recipient_name` | Authorization wizard step (removed entirely), data_entries | ✅ Done |
| `authorized_recipient_relationship` | Authorization wizard step (removed entirely), data_entries | ✅ Done |

---

### ⚠️ Wire Up (collected but not printing)

| Field key | Document(s) | Notes |
|-----------|-------------|-------|
*(No open wiring gaps — all collected fields are now mapped)*

---

### ❌ Add to App (missing — need new fields or derivations)

| Field | Where to add | Document(s) |
|-------|-------------|-------------|
| Age (derived) | **No new field** — compute from `birthdate` at render time | UB |
| Civil status → UB | **No new field** — reuse `civil_status` from data_entries | UB |
| Physician name / address / date / treatment | Add to FIELD_DEFINITIONS + conditional health wizard step | GL |
| Percentage of finance charges + EIR (items 6 & 7) | Confirm R.A. 3765 formula with WIBS first, then wire into DS blade | DS |

---

### ❓ Pending WIBS Answers

| Q# | Question | Answer |
|----|----------|--------|
| Q1 | Bank name on AZ hardcoded "Enterprise Bank, Inc." — use member's bank? | ✅ **Resolved** — AZ now uses member's entered `payout_bank_name`; hardcode removed |
| Q2 | Which Grepalife variant? Should health answers print on it? | ✅ **Resolved** — "Debtor's Creditor Group Life"; health booleans wired (coordinates need calibration) |
| Q3 | UB applies only to barangay officials? Add wizard step for designation/agency? | ✅ **Resolved** — UB applies broadly (not officials-only); conditional wizard step added for all members |
| Q4 | Guaranteed Net Take-Home Pay — member or staff entered? | ✅ **Resolved** — Staff-entered; wired to both AU and UB as `loan.gnthp` |
| Q5 | Confirm safe removal of 6 fields | ✅ **Resolved** — 8 fields total removed (original 6 plus `authorized_recipient_name` and `authorized_recipient_relationship`) |
| Q6 | Full list of employment types; required fields per type (pensioner edge case) | ✅ **Resolved** — Private, Government, Self-employed, Retired, Pensioner, OFW. Pensioner hides employer/office fields; OFW keeps all. |
| Q7 | Confirm converting LI / PP / DS from Excel to PDF for direct printing | ✅ **Resolved** — All three converted to PDF. DS EIR rows still blank pending formula (see DS open item). |
| Q8 | Can senior staff (loan managers / superadmin) hold their own loans? | ✅ **Resolved** — Allowed; existing permissions cover this case (no dedicated RBAC change documented in git log) |

---

### 📋 Document Conversion Status

| Document | Format today | Target | Status |
|----------|-------------|--------|--------|
| AF | PDF (Blade) | PDF | ✅ Done |
| GL | PDF (field map) | PDF | ✅ Done |
| AU | PDF (field map) | PDF | ✅ Done — rebuilt from the real reference doc, no known wiring gaps |
| AZ | PDF (field map) | PDF | ✅ Done (wiring gaps remain) |
| LI | PDF (Blade/service) | PDF | ✅ Done |
| PP | PDF (service class) | PDF | ✅ Done |
| DS | PDF (Blade) | PDF | ✅ Done (EIR rows blank — open item) |
| PN | PDF (Blade) | PDF | ⚠️ Code done — **visual verification required** |
| UB | PDF (field map) | PDF | ✅ Done (wiring gaps remain) |
| LSA | PDF (Blade) | PDF | ✅ Done (reference pattern) |

---

*Last updated: 2026-07-14 — feature branch `feature/rbac-loan-workflow`*
