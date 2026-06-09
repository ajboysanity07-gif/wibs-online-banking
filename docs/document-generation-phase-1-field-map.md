# Document Generation Phase 1 Field Map

This is a Phase 1 audit of the current repo state on 2026-06-09. It is documentation only. No workflow UI, migration, route, controller, PDF layout, or Docker changes were made as part of this phase.

Primary files reviewed:

- `app/Http/Controllers/Client/LoanRequestController.php`
- `app/Http/Controllers/Admin/LoanRequestController.php`
- `app/Http/Requests/Client/LoanRequestStoreRequest.php`
- `app/Http/Requests/Client/LoanRequestDraftRequest.php`
- `app/Http/Requests/Admin/LoanRequestApproveRequest.php`
- `app/Models/LoanRequest.php`
- `app/Models/LoanRequestPerson.php`
- `app/Models/MemberApplicationProfile.php`
- `app/Models/Wmaster.php`
- `app/Services/LoanRequests/LoanRequestService.php`
- `app/Services/LoanRequests/LoanRequestPayloadSerializer.php`
- `app/Services/LoanRequests/LoanRequestPdfService.php`
- `app/Services/LoanRequests/ApprovedLoanDocumentService.php`
- `app/Services/LoanRequests/LoanSecurityAgreementPdfService.php`
- `app/Services/LoanRequests/PdfFieldMaps/*.php`
- `app/Services/LoanRequests/ExcelCellMaps/PlanOfPaymentDisclosurePromissoryNoteExcelCellMap.php`
- `resources/views/reports/*.blade.php`
- `resources/js/pages/client/loan-request-show.tsx`
- `resources/js/pages/admin/loan-request-show.tsx`
- `resources/js/components/loan-request/loan-request-detail-page.tsx`
- `database/migrations/2026_03_20_043806_create_member_application_profiles_table.php`
- `database/migrations/2026_03_21_143417_create_loan_requests_table.php`
- `database/migrations/2026_03_21_143417_create_loan_request_people_table.php`
- `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`

## 1. Current Existing Flow

### Current Application Form PDF flow

1. The client and admin loan-request detail pages build `pdfHref` and `printHref` and pass them into the shared `LoanRequestDetailPage` component.
2. The current PDF endpoints are the existing loan-request routes:
   - client: `client.loan-requests.pdf`
   - admin: `admin.requests.pdf`
3. Both controllers validate access and status, then call `LoanRequestPdfService::render()`.
4. `LoanRequestPdfService` loads:
   - `loan_requests`
   - applicant and co-makers from `loan_request_people`
   - the related member user
   - organization branding
5. The PDF is rendered from the existing Application Form view:
   - `resources/views/reports/loan-request.blade.php`
   - `resources/views/reports/partials/loan-request-document.blade.php`
   - `resources/views/reports/partials/loan-request-styles.blade.php`
6. Rendering uses Chromium/Browsershot when `config('reports.pdf_driver') === 'chromium'`, otherwise it falls back to DomPDF.
7. The current Application Form PDF is view-only output. There is no generated-document table, no document record, and no document audit row written today.

### Current Print Application flow

1. The current print endpoints are:
   - client: `client.loan-requests.print`
   - admin: `admin.requests.print`
2. Both controllers call `LoanRequestPdfService::renderPrintView()`.
3. That method returns `resources/views/reports/loan-request-print.blade.php`, which includes the same Application Form partial used by the PDF view.
4. The print view triggers `window.print()` after fonts finish loading.
5. Current print behavior is browser print of the existing Application Form HTML. It is not a separate document workflow and does not create a stored artifact.

### Important current repo detail

The current checkout already contains approved-document download routes and generation services for:

- Application Form
- GREPALIFE
- Loan Security Agreement
- Plan of Payment
- Undertaking - Barangay
- Affidavit of Undertaking
- Authorization
- ZIP packaging of all approved documents

Those endpoints are currently wired from the admin/client loan-request detail pages for approved requests. For Phase 1, they are useful as implementation references only. They should not be treated as approval to add new workflow UI, document records, or new storage behavior.

### Current data prefill and snapshot flow

1. Applicant prefill comes from legacy member data plus the supplemental member profile:
   - `wmaster`
   - `member_application_profiles`
   - `appusers`
2. On draft save or submit, the request data is snapped into:
   - `loan_requests`
   - `loan_request_people`
3. After submission, the request snapshot is the practical source for document rendering because it freezes the request-time applicant and co-maker values.

## 2. Source of Truth Rules

These rules fit the current repo structure and should guide Phase 2.

1. Member-owned data should originate from member-owned sources, then be snapshotted onto the loan request at submit/correction time.
   - Personal data
   - Work data
   - Finance data
   - Health declarations
   - Beneficiaries
   - ATM and bank details
   - Other form-specific member declarations
2. In the current repo, the best existing pattern is:
   - member master/profile as the editable source
   - `loan_request_people` as the immutable per-request snapshot
3. Co-maker data is request-specific member input today. It does not have a separate master profile source in the current repo.
4. Admin input should stay limited to decision and approval data:
   - approved amount
   - approved term
   - reviewer / approved by
   - status
   - decision notes
   - cancellation reason
   - final approval action
5. System-owned data should be generated by the system, not typed by admins:
   - request reference number
   - timestamps
   - generated document entries
   - file paths
   - template version
   - audit logs
   - regeneration history
6. Admins should not manually retype member-owned data into document forms. If member-owned data is wrong after submission, the correct path should be a governed correction flow, then a regenerated document.
7. For future document generation, the safest rendering source is:
   - member master/profile for prefill before submission
   - request snapshot for final generated documents after submission or correction

## 3. Field Ownership Table

Document keys used below:

- `AF` = Application Form
- `GL` = GREPALIFE
- `LSA` = Loan Security Agreement
- `POP` = Plan of Payment
- `UB` = Undertaking - Barangay
- `AU` = Affidavit of Undertaking
- `AUTH` = Authorization

| Field / Data | Example | Source of Truth | Who Inputs It | Who Reviews It | Used By Document | Existing in DB? | Missing / Needs New Field? | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Applicant name | `Juan Q Dela Cruz` | `wmaster` name parts before submit, then `loan_request_people` applicant snapshot | Member / system prefill | Member before submit, admin during review | AF, GL, LSA, UB, AU, AUTH, POP | Yes | No | Current repo already snapshots applicant name onto the request. |
| Birthdate | `1990-01-01` | `wmaster.birthday` before submit, then applicant snapshot | Member / system prefill | Member, admin | AF, GL | Yes | No | Rendered from request snapshot in documents. |
| Address | `123 Loan Street, Lianga, Surigao del Sur` | `wmaster.address/address2/address3/address4` before submit, then applicant snapshot | Member / system prefill | Member, admin | AF, GL, LSA, UB, AU, AUTH, POP | Yes | No | Current request snapshot already supports structured address parts. |
| Civil status | `Married` | `wmaster.civilstat` before submit, then applicant snapshot | Member / system prefill | Member, admin | AF, GL | Yes | No | GREPALIFE checkbox logic depends on normalized civil status. |
| Spouse details | `Maria Dela Cruz, 34, 0917...` | `wmaster.spouse` plus `member_application_profiles` spouse fields, then applicant snapshot | Member / system prefill | Member, admin | AF | Yes | No | Name may come from `wmaster`; age and cell are supplemental profile fields. |
| Number of children | `2` | `wmaster.dependent` when present, else `member_application_profiles.number_of_children`, then applicant snapshot | Member / system prefill | Member, admin | AF | Yes | No | Current service already supports both legacy and supplemental sources. |
| Employment type | `Employed` | `member_application_profiles`, then applicant/co-maker snapshot | Member | Member, admin | AF | Yes | No | Also feeds future document employment context. |
| Employer / business | `Sample Enterprise` | `member_application_profiles` or request snapshot | Member | Member, admin | AF, GL, POP | Yes | No | Current GREPALIFE and POP reuse this value. |
| Gross monthly income | `25000.00` | `member_application_profiles`, then snapshot | Member | Member, admin | AF | Yes | No | Not currently used by the other approved docs. |
| Payday | `15th & 30th` | `member_application_profiles`, then snapshot | Member | Member, admin | AF, POP | Yes | No | Current POP derives workbook payment mode from this. |
| Loan type | `SALARY LOAN` | `loan_requests.typecode` plus `loan_type_label_snapshot` | Member chooses type, system snapshots label | Admin | AF, GL, LSA, UB, AU, POP | Yes | No | Use the snapshot label for generated docs, not live `wlntype` lookup. |
| Requested amount | `25000.00` | `loan_requests.requested_amount` | Member | Admin | AF | Yes | No | Current approved-doc package mostly uses approved amount instead. |
| Requested term | `12` | `loan_requests.requested_term` | Member | Admin | AF | Yes | No | Current approved-doc package mostly uses approved term instead. |
| Loan purpose | `Business capital` | `loan_requests.loan_purpose` | Member | Admin | AF | Yes | No | Current Application Form displays it directly. |
| Availment status | `New` | `loan_requests.availment_status` | Member | Admin | AF | Yes | No | Current AF checkbox row uses this. |
| Approved amount | `25000.00` | `loan_requests.approved_amount` | Admin | Admin / final approver | AF, GL, UB, AU, AUTH, POP | Yes | No | Core approval-owned field. |
| Approved term | `12` | `loan_requests.approved_term` | Admin | Admin / final approver | AF, GL, POP | Yes | No | Also drives maturity-date and amortization derivations. |
| Approved by / loan manager | `Helario B. Tejero` | `loan_requests.reviewed_by` -> related `appusers/admin_profiles` | Admin action | Admin / audit | AF, LSA, AU, POP | Partial | Partial | Name exists. Dedicated reviewer title/position and signature image do not exist in the current path. |
| Co-maker names | `Ana MakerOne`, `Ben MakerTwo` | `loan_request_people` snapshots | Member | Member, admin | AF, POP | Yes | No | Request-specific today; no separate co-maker master profile. |
| Co-maker addresses | `1 CoMaker Street, Loan City, Loan Province` | `loan_request_people` snapshots | Member | Member, admin | AF, POP | Yes | No | Structured address already supported. |
| Co-maker contact numbers | `0917...`, `02-...` | `loan_request_people.cell_no/telephone_no` | Member | Member, admin | AF | Yes | No | Not currently used in other approved docs. |
| Co-maker work / business | `Employed`, `ABC Trading` | `loan_request_people` snapshots | Member | Member, admin | AF | Yes | No | Captured for both co-makers today. |
| GREPALIFE health questions | `Any prior illness? No` | No current source | Member | Admin if required | GL | No | Yes | No health declaration model, fields, or validation were found in the current repo. |
| GREPALIFE beneficiary details | `Beneficiary One, 02/03/2001` | `wmaster.beneficiary1..3`, `ben*_bday`, or linked `ben*_acctno` members | Member master data today | Member maintenance, admin review if needed | GL | Partial | Partial | Names and birthdates exist. Relationship is currently always blank. No request-level beneficiary snapshot exists. |
| ATM number | `1234-5678-9012-3456` | No current source | Member | Admin if used for release | AUTH | No | Yes | No ATM field or payout-channel field exists in the current request/member profile path. |
| Bank name | `LandBank` | No current source | Member | Admin if used for release | AUTH | No | Yes | Not stored in current loan request, member profile, or document data builder. |
| Bank account number | `1234567890` | No current source | Member | Admin if used for release | AUTH | No | Yes | Not stored today. |
| Payment schedule fields | `SEMI-MONTHLY, 24 amortizations, 06/09/2027 maturity` | Mixed: applicant payday snapshot, admin approved term, system date derivations | Member + admin + system | Admin | POP | Partial | Yes | Existing derivations: payment mode, amortization count, maturity date, term days. Missing: interest rate, service charge rate, insurance rate, installment amounts, first due date, full schedule rows owned by data. |
| Collateral / security fields | `Compensating deposit terms` | Currently hard-coded legal template text | System today | Admin/legal during template approval | LSA | No dedicated data field | Yes | Current LSA view uses generic agreement language. There is no dedicated collateral/security metadata on the request. |
| Barangay undertaking fields | `Barangay name, issuing official, locality` | No dedicated source today beyond applicant/loan/org data | Likely member plus admin verification | Admin | UB | Partial | Yes | Current template map only injects applicant name/address, loan type, approved amount/date, organization name, and applicant signature. |
| Authorization fields | `Authorized release recipient / relationship / payout destination` | No current source beyond applicant + loan metadata | Member, possibly admin verification | Admin | AUTH | Partial | Yes | Current template map only injects applicant name/address, loan reference, approved amount/date, organization name, and applicant signature. |
| Signature printed names | `JUAN Q DELA CRUZ` | Computed from applicant/co-maker/reviewer names | System from existing names | Admin visually | AF | Partial | No separate field needed unless overrides are required | Current AF prints names from existing name fields. They are not stored as separate printable-name overrides. |
| Signature image / drawn signature | `loan-requests/signatures/<uuid>.png` | `loan_request_people.signature_path` for applicant and co-makers | Member | Admin visually | GL, LSA, UB, AU, AUTH | Partial | Partial | Applicant/co-maker signature images exist. Admin/reviewer signature image is not part of the current active generation path. |

## 4. Per-Document Data Requirement

### 4.1 Application Form

**Required fields**

- loan status
- submitted date
- approved amount
- approved term
- loan type
- loan purpose
- availment status
- applicant personal data
- applicant work and finance data
- co-maker 1 personal/work data
- co-maker 2 personal/work data
- approved by name
- printed signature names

**Existing fields**

- All core request fields in `loan_requests`
- All applicant/co-maker fields in `loan_request_people`
- reviewer name from `reviewed_by`
- printed signature names computed from person/admin names

**Missing fields**

- `Recommended By` is blank in the current template
- no generated-document record
- no document generation audit log

**Member-input fields**

- applicant details
- co-maker details
- requested amount/term/purpose/availment

**Admin-input fields**

- approved amount
- approved term
- decision notes
- final decision

**System-generated fields**

- request reference
- submission/review timestamps
- PDF/print output
- printed signature names

### 4.2 GREPALIFE

**Required fields**

- applicant full name
- civil status
- birthdate
- nationality
- place of birth
- home address
- office/business address
- employer/business
- nature of business
- position/designation
- years in work/business
- home/work/mobile/email contact details
- organization/company name
- approved term
- approved amount
- approved date
- loan type
- beneficiary names
- beneficiary birthdates
- beneficiary relationships
- applicant signature
- health declarations if the final business form requires them

**Existing fields**

- applicant identity and contact data from request snapshot
- organization name
- approved amount/term/date
- loan type
- beneficiary names and birthdates from `wmaster`
- applicant signature image

**Missing fields**

- health declaration questions and answers
- beneficiary relationship
- address ZIP/country
- office ZIP/country
- home phone

**Member-input fields**

- applicant personal/work/contact data
- future health declarations
- future beneficiary relationship if required

**Admin-input fields**

- approved amount
- approved term
- approval reviewer

**System-generated fields**

- approved date
- nationality default
- formatted dates
- document field placement

### 4.3 Loan Security Agreement

**Required fields used by the active generator today**

- borrower name
- borrower address
- loan type
- approved date
- reviewer/lender name
- reviewer title if available
- place of signing
- applicant signature image

**Existing fields**

- borrower name and address from applicant snapshot
- loan type from request snapshot
- approved date from `reviewed_at`
- reviewer name from `reviewed_by`
- place of signing from applicant city/province
- applicant signature image

**Missing fields**

- dedicated collateral/security metadata
- reviewer position/title
- admin/reviewer signature image in the active path
- explicit co-maker signatory handling in the active generator

**Member-input fields**

- borrower identity and address
- applicant signature

**Admin-input fields**

- approval action
- reviewer identity

**System-generated fields**

- signing date decomposition
- place-of-signing fallback from applicant address

**Repo note**

`LoanSecurityAgreementPdfFieldMap.php` exists, but the active generation path uses `LoanSecurityAgreementPdfService` with `resources/views/reports/loan-security-agreement.blade.php`. Phase 2 should follow the active generator, not the unused field-map stub.

### 4.4 Plan of Payment

**Required fields**

- applicant full name
- employer/business
- applicant address
- approved amount
- interest rate
- approved term
- service charge rate
- reviewer name
- reviewer position
- loan type
- payment mode
- amortization count
- insurance term
- insurance rate
- co-maker names
- co-maker addresses
- term in days
- approved amount in words
- interest wording
- approved date
- maturity date
- reference number

**Existing fields**

- applicant full name
- employer/business
- address
- approved amount
- approved term
- reviewer name
- loan type
- payment mode derived from payday
- amortization count derived from payday plus approved term
- co-maker names and addresses
- term days derived from approved term
- approved amount in words
- approved date
- maturity date
- reference number

**Missing fields**

- interest rate
- service charge rate
- insurance rate
- interest wording
- reviewer position
- any explicit persisted installment schedule data

**Member-input fields**

- applicant payday
- applicant/co-maker identity and address

**Admin-input fields**

- approved amount
- approved term
- reviewer

**System-generated fields**

- payment mode workbook label
- amortization count
- maturity date
- amount in words

### 4.5 Undertaking - Barangay

**Required fields used by the current field map**

- applicant full name
- applicant address
- loan type
- approved amount
- approved date
- organization/company name
- applicant signature image

**Existing fields**

- all of the above except no special barangay metadata

**Missing fields**

- barangay name
- issuing official name
- issuing official title
- barangay locality details if required by the final form
- witness/admin signature if required

**Member-input fields**

- applicant identity and address
- applicant signature

**Admin-input fields**

- approved amount
- approved date through approval action

**System-generated fields**

- organization/company name

### 4.6 Affidavit of Undertaking

**Required fields used by the current field map**

- applicant full name
- applicant address
- approved amount
- loan type
- approved date
- reviewer name
- applicant signature image

**Existing fields**

- all of the above

**Missing fields**

- notarization data
- venue/place fields if the final affidavit requires them
- reviewer signature image
- witness/notary metadata

**Member-input fields**

- applicant identity and address
- applicant signature

**Admin-input fields**

- approval data
- reviewer identity

**System-generated fields**

- formatted dates

### 4.7 Authorization

**Required fields used by the current field map**

- applicant full name
- applicant address
- loan reference
- approved amount
- approved date
- organization/company name
- applicant signature image

**Existing fields**

- all of the above

**Missing fields**

- authorized recipient/payee
- relationship to member
- reason for authorization if required
- bank name
- bank account number
- ATM number
- payout/release method

**Member-input fields**

- applicant identity and signature
- future authorization destination and bank/ATM details

**Admin-input fields**

- approval data

**System-generated fields**

- request reference
- approved date
- organization/company name

## 5. Recommended Future Database Changes

Documentation only. Do not implement in Phase 1.

### Design principle

Do not push member-owned data entry into admin-only document forms. If a data set is truly member-owned and reused across requests, the cleaner long-term design is:

1. member-owned editable source
2. request-time snapshot
3. generated-document record

If Phase 2 must stay scoped to loan-request storage first, the tables below should behave as request snapshots and not as admin-only retyping surfaces.

### Recommended tables / fields

#### `loan_request_document_inputs`

Purpose:

- hold document-specific, non-core inputs that do not belong on `loan_requests`
- avoid stuffing GREPALIFE/bank/authorization-only fields onto the main request table

Suggested columns:

- `id`
- `loan_request_id`
- `document_key`
- `data_json`
- `captured_from` (`member`, `admin_correction`, `system`)
- `locked_at`
- `created_by`
- `updated_by`
- timestamps

Use cases:

- authorization recipient details
- barangay form extras
- future document-specific overrides approved through correction flow

#### `loan_request_beneficiaries`

Purpose:

- give GREPALIFE and future insurance forms a stable request-level beneficiary snapshot

Suggested columns:

- `id`
- `loan_request_id`
- `slot`
- `full_name`
- `birthdate`
- `relationship`
- `source_member_acctno` nullable
- timestamps

Current gap addressed:

- beneficiary relationship is missing today
- current generation depends on live `wmaster` fields instead of a request snapshot

#### `loan_request_health_declarations`

Purpose:

- capture GREPALIFE or insurance health-question answers cleanly

Suggested columns:

- `id`
- `loan_request_id`
- `document_key`
- `question_key`
- `question_text_snapshot`
- `answer_boolean`
- `answer_text`
- `declared_at`
- timestamps

Current gap addressed:

- no health declaration model or fields exist today

#### `loan_request_bank_details`

Purpose:

- support authorization and release/disbursement forms

Suggested columns:

- `id`
- `loan_request_id`
- `account_name`
- `bank_name`
- `bank_account_number`
- `atm_number`
- `release_channel`
- `authorized_recipient_name`
- `authorized_recipient_relationship`
- timestamps

Current gap addressed:

- no bank/ATM/release destination fields exist today

#### `loan_request_generated_documents`

Purpose:

- record every generated file and make regeneration auditable

Suggested columns:

- `id`
- `loan_request_id`
- `document_key`
- `template_version`
- `storage_disk`
- `storage_path`
- `mime_type`
- `checksum`
- `generated_by`
- `generated_at`
- `source_snapshot_json`
- `superseded_by_id` nullable
- timestamps

Current gap addressed:

- current repo generates files on demand only
- no document history exists

#### `loan_request_document_audit_logs`

Purpose:

- keep a human/audit trail for document input changes and regeneration events

Suggested columns:

- `id`
- `loan_request_id`
- `generated_document_id` nullable
- `document_key`
- `action`
- `actor_user_id`
- `reason`
- `before_json`
- `after_json`
- timestamps

Current gap addressed:

- there is no dedicated document-generation audit trail today

## 6. Phase 2 Readiness Checklist

Before implementation starts, confirm all of the following.

1. Confirm the rendering source for final generated documents:
   - member profile prefill before submit
   - request snapshot after submit/correction
2. Confirm that Phase 2 will not change the current Application Form PDF and print behavior.
3. Confirm whether the existing approved-document routes/services stay as the technical base, or whether some should be simplified before building document records.
4. Confirm the exact required fields for each target document from the real business templates, especially:
   - GREPALIFE health questions
   - beneficiary relationship
   - authorization recipient and bank/ATM fields
   - barangay-specific metadata
   - notarization / witness requirements
5. Confirm whether beneficiary, bank, and health data are:
   - member-owned reusable profile data
   - request-only snapshot data
   - or both
6. Confirm whether reviewer title and reviewer signature image are required in future outputs.
7. Confirm whether co-makers must sign only the Application Form, or also specific approved documents in the active Phase 2 workflow.
8. Confirm the exact ownership rules for corrections:
   - member correction request
   - admin-approved correction
   - regeneration policy
9. Confirm the target generated-document persistence model:
   - on-demand only
   - stored file per generation
   - versioned regeneration history
10. Confirm the minimum migration set for Phase 2 before any schema work begins.
11. Confirm the minimum UI scope for Phase 2 before adding any approved-documents panel or workflow controls.
12. Confirm whether the current live `wmaster` beneficiary lookup should be replaced by request-level beneficiary snapshots before production rollout.

## Summary of Phase 1 Findings

- The current Application Form PDF and Print Application already exist and both rely on the same report partial and the same request snapshot data.
- The repo already has individual approved-document generators and ZIP packaging for approved requests, but there is still no document record table or document audit trail.
- The current schema is strongest around:
  - applicant snapshot data
  - co-maker snapshot data
  - request/approval metadata
  - applicant/co-maker signature image paths
- The largest current gaps for future documents are:
  - health declarations
  - beneficiary relationship and request-level beneficiary snapshots
  - bank/ATM/account details
  - authorization recipient details
  - collateral/security metadata
  - reviewer position/signature metadata
  - payment schedule rate fields
- The best Phase 2 direction is to keep member-owned data member-owned, keep request-time snapshots authoritative for generated documents, and add document-specific storage only for data the current request model does not already own well.
