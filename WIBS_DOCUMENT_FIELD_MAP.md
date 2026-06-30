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
| 🗑️ | Safe to remove — confirmed absent from all real MRDINC documents |
| 🔒 | Do not remove — gates document `applicable()` logic |
| ❓ | Pending WIBS confirmation before wiring or adding |

**Who enters:**
- **M** = Member (12-step loan wizard, `loan_requests` / `loan_request_people` / `loan_request_data_entries`)
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
| 5 | LI | Loan Information | Excel → PDF (pending) | XLSX today |
| 6 | PP | Plan of Payment | Excel → PDF (pending) | XLSX today |
| 7 | DS | Disclosure Statement | Excel → PDF (pending) | XLSX today |
| 8 | PN | Promissory Note | Blade template | PDF (converted) ⚠️ not yet visually verified |
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
| Employment type (Private/Government/Self-employed/Retired/Pensioner) | M | ✅ | `loan_request_data_entries` |
| Employer / Business name | M | ✅ | `loan_request_data_entries` |
| Office address | M | ✅ | `loan_request_data_entries` |
| Office city / province / country / ZIP | M | ✅ | `loan_request_data_entries` |
| Current position | M | ✅ | `loan_request_data_entries` |
| Office telephone number | M | ✅ | `loan_request_data_entries` |
| Nature of business | M | ✅ | `loan_request_data_entries` |
| Years in work / business | M | ✅ | `loan_request_data_entries` |
| Gross monthly income | M | ✅ | `loan_request_data_entries` |
| Payday | M | ✅ | `loan_request_data_entries` |

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
| Civil status | M | 🗑️ | **REMOVE** — AF has no co-maker civil status field |
| Housing status | M | 🗑️ | **REMOVE** — AF has no co-maker housing field |

### Co-Maker 2

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| *(same fields as Co-Maker 1 except:)* | | | |
| Civil status | M | 🗑️ | **REMOVE** |
| Housing status | M | 🗑️ | **REMOVE** |

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

> **Pending WIBS (Q6):** Confirm full list of employment types. Pensioner confirmed.
> OFW? Confirm which fields are required per type (pensioner has no employer).

---

---

## 2 — Grepalife / Sun Life (GL)

**PDF field map · Insurance form for borrower coverage**

> **Pending WIBS (Q2):** Which variant does MRDINC use — "Group Insurance" or
> "Debtor's Creditor Group Life"? This affects which fields are on the form.

### Personal Data

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Residence address | M | ✅ | `loan_request_data_entries` |
| City / Province | M | ✅ | `loan_request_data_entries` |
| Country / ZIP | M | ❌ | **ADD** — app collects these but Grepalife mapping missing country/zip |
| Birthdate | M | ✅ | `loan_request_people.birthdate` |
| Civil status | M | ✅ | `loan_request_data_entries` |
| Nationality | M | ✅ | `loan_request_data_entries` |
| Home phone | M | ❌ | **ADD to GL mapping** — collected but not mapped to Grepalife |
| Email address | M | ❌ | **ADD to GL mapping** — collected but not mapped to Grepalife |

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
| Q1: Smoker (Yes/No) | M | ⚠️ | `loan_request_data_entries.health_smoker` — **collected, not printed** |
| Q2: Hypertension (Yes/No) | M | ⚠️ | `loan_request_data_entries.health_hypertension` — **collected, not printed** |
| Q3: Diabetes (Yes/No) | M | ⚠️ | `loan_request_data_entries.health_diabetes` — **collected, not printed** |
| Q4: Hospitalization (Yes/No) | M | ⚠️ | `loan_request_data_entries.health_hospitalization` — **collected, not printed** |
| Physician name | M | ❓ | Collected if any Yes — confirm wiring to GL |
| Physician address | M | ❓ | Collected if any Yes — confirm wiring to GL |
| Date seen | M | ❓ | Collected if any Yes — confirm wiring to GL |
| Treatment received | M | ❓ | Collected if any Yes — confirm wiring to GL |

> **Note:** Health fields currently gate `GL applicable()` — they are NOT yet printed
> in the GL PDF field map. Wire up after WIBS confirms Q2.

### Insurance Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Insurance rate | S | ✅ | `loan_request_data_entries` (staff) |
| Insurance term (max 12 months) | S | ✅ | `loan_request_data_entries` (staff) |

---

---

## 3 — Affidavit of Undertaking (AU)

**PDF field map · Borrower swears to repay; co-makers witness**

### Borrower Identification

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Age | M | ❌ | **DERIVE** from `birthdate` — do not add a new field; compute at render time |
| Civil status | M | ❌ | **REUSE** `loan_request_data_entries.civil_status` — not yet wired to AU |
| Residence address | M | ✅ | `loan_request_data_entries` |

### Bank / Payout Details

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Bank name | M | ⚠️ | `loan_request_data_entries.payout_bank_name` — **collected, not printed** |
| Account number | M | ⚠️ | `loan_request_data_entries.payout_account_number` — **collected, not printed** |
| Account name | M | ⚠️ | `loan_request_data_entries.account_name` — **collected, not printed** |
| ATM account number | M | ⚠️ | `loan_request_data_entries.atm_number` — **collected, not printed** |
| Bank branch | M | ❌ | **ADD** — Affidavit needs branch; app does not collect it yet |

### Loan Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Approved loan amount | S | ✅ | `loan_request_data_entries` (staff) |
| Guaranteed Net Take-Home Pay (GNTHP) | ❓ | ❌ | **CONFIRM WITH WIBS (Q4)** — member or staff? |

### Notarization

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Document number | S | ✅ | `loan_request_data_entries.doc_number` |
| Page number | S | ✅ | `loan_request_data_entries.page_number` |
| Book number | S | ✅ | `loan_request_data_entries.book_number` |
| Series year | S | ✅ | `loan_request_data_entries.series_year` |
| Place of signing | S | ✅ | `loan_request_data_entries.signing_place` |

### Witnesses

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Witness 1 name | S | ✅ | `loan_request_data_entries.witness_one_name` (fixed Phase 1) |
| Witness 2 name | S | ✅ | `loan_request_data_entries.witness_two_name` (fixed Phase 1) |

---

---

## 4 — Authorization (AZ)

**PDF field map · Authorises release of loan proceeds to borrower or alternate recipient**

### Borrower

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Residence address | M | ✅ | `loan_request_data_entries` |

### Authorized Recipient (conditional — if release to 3rd party)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Authorized recipient name | M | 🔒 | `loan_request_data_entries.authorized_recipient_name` — gates `AZ applicable()` |
| Authorized recipient relationship | M | 🔒 | `loan_request_data_entries.authorized_recipient_relationship` |
| Release method | M | 🔒 | `loan_request_data_entries.release_method` |
| Authorized recipient contact number | M | 🗑️ | **REMOVE** — not on the Authorization document |
| Authorization reason | M | 🗑️ | **REMOVE** — not on any document, gates nothing |

### Bank / Payout Details

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Bank name | M | ⚠️ | `loan_request_data_entries.payout_bank_name` — **collected, not printed** |
| Account number | M | ⚠️ | `loan_request_data_entries.payout_account_number` — **collected, not printed** |
| Bank branch | M | ❌ | **ADD** — same new field as AU above |
| ATM card holder name | M | ❌ | **ADD** — only if different from borrower |

> **Pending WIBS (Q1):** Bank name on Authorization is currently hardcoded
> `"Enterprise Bank, Inc."`. Confirm: always that bank, or use member's entered bank?

### Witnesses & Notarization

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Witness 1 name | S | ✅ | `loan_request_data_entries.witness_one_name` |
| Witness 2 name | S | ✅ | `loan_request_data_entries.witness_two_name` |
| Notarization fields (doc/page/book/series/place) | S | ✅ | `loan_request_data_entries` |

---

---

## 5 — Loan Information (LI)

**Excel sheet 0 → PDF (pending conversion)**

> Conversion order: Plan of Payment first, then this sheet.

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

> **Pending WIBS (Q7):** Confirm converting this sheet to PDF for direct printing.

---

---

## 6 — Plan of Payment (PP)

**Excel sheet 1 → PDF (next to convert after PN visual verification)**

> ⚠️ **Do not start converting this sheet until the Promissory Note PDF has been
> visually verified against the original Excel sheet.**

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

> **Pending WIBS (Q7):** Confirm converting this to PDF for direct printing.

---

---

## 7 — Disclosure Statement (DS)

**Excel sheet 2 → PDF (hardest — convert last)**
**Governed by R.A. 3765 (Truth in Lending Act) — exact layout required**

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
| Effective interest rate | SYS | ✅ | Computed per R.A. 3765 formula |
| Term (months) | S | ✅ | `loan_request_data_entries` (staff) |
| Mode of payment | S | ✅ | `loan_request_data_entries` (staff) |

> **Pending WIBS (Q7):** Confirm converting this to PDF. Layout must match the
> statutory R.A. 3765 disclosure format exactly.

---

---

## 8 — Promissory Note (PN)

**Blade template · Converted to PDF this session · ⚠️ NOT yet visually verified**

> **ACTION REQUIRED before any other conversion:** Generate a real PN PDF, open it,
> and compare side-by-side against the original Excel Promissory Note sheet.

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

**PDF field map · Conditional — only for barangay official borrowers**

> `applicable()` is currently gated on: `barangay_name` OR `barangay_clearance_reference`
> OR `barangay_locality` being non-empty. Fields gate correctly but do NOT yet print.

### Affiant (Borrower / Official)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Full name | M | ✅ | `loan_request_people` (borrower) |
| Age | M | ❌ | **DERIVE** from `birthdate` — compute at render time, no new field |
| Civil status | M | ❌ | **REUSE** `loan_request_data_entries.civil_status` — not yet wired to UB |
| Residence address | M | ✅ | `loan_request_data_entries` |

### Barangay Info (conditional step in wizard)

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Barangay name | M | ⚠️ | `loan_request_data_entries.barangay_name` — **collected, gates only, not printed** |
| Barangay clearance reference | M | ⚠️ | `loan_request_data_entries.barangay_clearance_reference` — **collected, not printed** |
| Barangay locality | M | ⚠️ | `loan_request_data_entries.barangay_locality` — **collected, not printed** |
| Official's designation / position | M | ❌ | **ADD** — UB needs this; wizard collects barangay_name but NOT designation |
| Agency name | M | ❌ | **ADD** — different from barangay_name; UB needs the LGU/agency |
| Agency address | M | ❌ | **ADD** — UB needs the agency address |

### Loan & Salary Terms

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Guaranteed Net Take-Home Pay | ❓ | ❌ | **CONFIRM WITH WIBS (Q4)** — member or staff? |
| Approved loan amount | S | ✅ | `loan_request_data_entries` (staff) |

### Witnesses & Notarization

| Field | Who | Status | App source |
|-------|-----|--------|------------|
| Witness 1 name | S | ✅ | `loan_request_data_entries.witness_one_name` |
| Witness 2 name | S | ✅ | `loan_request_data_entries.witness_two_name` |
| Notarization fields (doc/page/book/series/place) | S | ✅ | `loan_request_data_entries` |

> **Pending WIBS (Q3):** Confirm UB applies only to barangay officials. Should the
> wizard add a conditional step for designation/agency/address for those members?

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

### 🗑️ Remove These Fields (confirmed safe — 6 fields)

| Field | Location |
|-------|----------|
| `co_maker_1.civil_status` | Wizard step, data_entries |
| `co_maker_1.housing_status` | Wizard step, data_entries |
| `co_maker_2.civil_status` | Wizard step, data_entries |
| `co_maker_2.housing_status` | Wizard step, data_entries |
| `authorization_reason` | Bank/payout step, data_entries |
| `authorized_recipient_contact` | Bank/payout step, data_entries |

> ⚠️ Do not remove until WIBS confirms (Q5).

---

### ⚠️ Wire Up (collected but not printing — 7 fields)

| Field key(s) | Document(s) that need it |
|--------------|--------------------------|
| `payout_bank_name` | AU (bank name), AZ (bank name) |
| `payout_account_number` | AU (account no.), AZ (account no.) |
| `account_name` | AU (account name) |
| `atm_number` | AU (ATM account no.) |
| `health_smoker` | GL (Q1) |
| `health_hypertension` | GL (Q2) |
| `health_diabetes` | GL (Q3) |
| `health_hospitalization` | GL (Q4) |
| `barangay_name` | UB (barangay name line) |
| `barangay_clearance_reference` | UB (clearance ref) |
| `barangay_locality` | UB (locality) |
| `authorized_recipient_name` | AZ (recipient name) |
| `authorized_recipient_relationship` | AZ (relationship) |
| `release_method` | AZ (release method) |

---

### ❌ Add to App (missing — need new fields or derivations)

| Field | Where to add | Document(s) |
|-------|-------------|-------------|
| Bank branch | Bank/payout wizard step | AU, AZ |
| ATM card holder name (if ≠ borrower) | Bank/payout wizard step | AZ |
| Barangay official's designation | Conditional barangay step | UB |
| Agency name | Conditional barangay step | UB |
| Agency address | Conditional barangay step | UB |
| Guaranteed Net Take-Home Pay | ❓ member or staff (Q4) | AU, UB |
| Age (derived) | **No new field** — compute from `birthdate` at render | AU, UB |
| Civil status → AU | **No new field** — reuse `civil_status` from data_entries | AU |
| Civil status → UB | **No new field** — reuse `civil_status` from data_entries | UB |
| Home phone → GL | **No new field** — reuse `home_phone` from data_entries | GL |
| Email → GL | **No new field** — reuse `email` from data_entries | GL |
| Country/ZIP → GL | **No new field** — reuse address fields from data_entries | GL |
| Physician name/address/date/treatment | Conditional health step (if any Yes) | GL |

---

### ❓ Pending WIBS Answers (block work until confirmed)

| Q# | Question | Blocks |
|----|----------|--------|
| Q1 | Bank name on AZ hardcoded "Enterprise Bank, Inc." — use member's bank? | AZ wiring |
| Q2 | Which Grepalife variant? Should health answers print on it? | GL wiring |
| Q3 | UB applies only to barangay officials? Add wizard step for designation/agency? | UB fields |
| Q4 | Guaranteed Net Take-Home Pay — member or staff entered? | AU, UB fields |
| Q5 | Confirm safe removal of 6 fields | Field removal |
| Q6 | Full list of employment types; required fields per type (pensioner edge case) | AF/wizard |
| Q7 | Confirm converting LI/PP/DS from Excel to PDF for direct printing | LI, PP, DS |
| Q8 | Can senior staff (loan managers/superadmin) hold their own loans? | RBAC |

---

### 📋 Document Conversion Status

| Document | Format today | Target | Status |
|----------|-------------|--------|--------|
| AF | PDF (Blade) | PDF | ✅ Done |
| GL | PDF (field map) | PDF | ✅ Done |
| AU | PDF (field map) | PDF | ✅ Done (wiring gaps remain) |
| AZ | PDF (field map) | PDF | ✅ Done (wiring gaps remain) |
| LI | XLSX | PDF | ⏳ Pending (convert after PP) |
| PP | XLSX | PDF | ⏳ Pending (next after PN verified) |
| DS | XLSX | PDF | ⏳ Pending (hardest — do last) |
| PN | PDF (Blade) | PDF | ⚠️ Code done — **visual verification required** |
| UB | PDF (field map) | PDF | ✅ Done (wiring gaps remain) |
| LSA | PDF (Blade) | PDF | ✅ Done (reference pattern) |

---

*Last updated: based on WIBS_SESSION_SUMMARY.md — feature branch `feature/rbac-loan-workflow`, 709 passing tests*
