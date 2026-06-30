# Plan — Remove Third-Party Authorization Recipient Fields & Relocate `release_method`

> **Status:** Plan only. No implementation code has been written.
> **Author context:** Investigation completed against branch `feature/rbac-loan-workflow`.
> **Implementer:** A separate session (Sonnet) should be able to execute this without the
> investigating conversation. Every file path, line reference, method name, and decision is explicit.

---

## 0. Business context (confirmed by WIBS/Ariz — do not re-litigate)

Loan proceeds are **always released directly to the member**, never to a third party through this
portal. Third-party authorization (someone else claiming the release on the member's behalf) is
handled **entirely outside the system**: a physical authorization letter signed in person on the day
of release. Collecting `authorized_recipient_name` and `authorized_recipient_relationship` weeks
earlier, digitally, adds friction for a rare scenario that is never sourced from app data anyway.

Therefore `authorized_recipient_name` and `authorized_recipient_relationship` are confirmed for
**unconditional removal** — same bucket as `authorization_reason` / `authorized_recipient_contact`
removed in the prior field-removal task (migration `2026_06_29_203233_purge_removed_loan_request_fields.php`).

---

## 1. INVESTIGATION RESULT — what is `release_method`? → **RELOCATE, do not delete**

### Evidence found in code

| Question | Finding | Source |
|---|---|---|
| Validation type | **Free-text string**, `'type' => 'string'`. No enum / `Rule::in()` anywhere. | `LoanRequestDataService.php:126-133`; `LoanRequestStoreRequest.php:102` (`['required','string','max:255']`) |
| UI control | Rendered as a **plain text `<Input>`** by the generic `LoanRequestDataSectionStep` (no `<Select>`/option list). | `resources/js/components/loan-request/loan-request-steps.tsx:506-606` |
| Actual values used | `"Bank transfer"`, `"ATM"` — disbursement **channels by which the member receives their own proceeds**. | `LoanRequestTest.php:206`, `LoanWorkflowAcceptanceTest.php:380`, `ApprovedLoanDocumentPackageDownloadTest.php:1412` (`'ATM'`), `:2512` (`'Bank transfer'`) |
| How it is grouped at render time | Bundled with `payout_bank_name` / `payout_account_*` in the document render payload. | `ApprovedLoanDocumentService.php:906-917`; `LoanRequestDocumentWorkflowService.php:618-623` |

### Conclusion

`release_method` describes **how the member themselves receives their own loan** (Bank transfer /
ATM / Cash / Check) — it is **NOT** a "how a third party claims" descriptor. Per the decision rule,
it must **NOT be deleted**. It is a real operational fact independent of the now-removed third-party
scenario, so it is **relocated** from the `authorization` wizard section to the `banking`
(Bank & payout) section, alongside `payout_bank_name` / `payout_account_number`.

**Net effect on the `authorization` data section:** after removing the two recipient fields and
relocating `release_method`, the `authorization` section has **zero fields** and is removed entirely.
The wizard drops from **12 steps → 11 steps**.

> Note on terminology: there are **two** distinct "authorization" concepts in this codebase. Only one
> is being removed.
> - **`authorization` data SECTION / wizard step** (`LoanRequestDataService` `SECTION_LABELS`,
>   `LoanRequestFormData.authorization`) → **REMOVED**.
> - **`authorization` / `Authorization` DOCUMENT (AZ PDF)** (`LoanRequestDocumentKey::Authorization`,
>   the `admin.requests.documents.authorization` route, the "Authorization PDF" download link) →
>   **KEPT, unchanged.** The AZ document still generates; it is only fed less data.

---

## 2. FIELD REGISTRY — `app/Services/LoanRequests/LoanRequestDataService.php`

**`FIELD_DEFINITIONS` (const, starts line 21):**

1. **Delete** the `authorized_recipient_name` entry (lines **110-117**).
2. **Delete** the `authorized_recipient_relationship` entry (lines **118-125**).
3. **Relocate** the `release_method` entry (lines **126-133**):
   - Change `'section' => 'authorization'` → `'section' => 'banking'`.
   - Physically **move** the whole array entry down so it sits within the `banking` group
     (immediately after `payout_account_type` at line 165, before `payout_atm_number`, is a good
     spot). This controls render order in the wizard (sections are bucketed in array order by
     `sectionDefinitions()` at lines 443-450).
   - Keep `'required_on_submit' => true` and `'sensitive' => true` unchanged (preserves current
     behavior — member must still choose a release method; it now lives on the Bank & payout step).
     *(If WIBS later wants it optional, flip to `false` and adjust `LoanRequestStoreRequest` to
     `nullable`; out of scope here.)*

**`SECTION_LABELS` (const, line 419):**

4. **Delete** the line `'authorization' => 'Authorization and release',` (line **422**).

> No other changes needed in this file. `sectionOwner()`, `serializeSections()`,
> `emptySections()`, `missingRequiredMemberFields()` all derive dynamically from the two consts
> above — removing the section + fields propagates automatically. The two removed fields drop out of
> the `missingRequiredMemberFields` required-on-submit check naturally; `release_method` stays
> required but is now reported under the banking section.

---

## 3. WIZARD STEP & STEPPER RENUMBER (12 → 11)

The "Authorization & release" step is **registry-driven** (rendered by the generic
`LoanRequestDataSectionStep`), but the **step list itself is a hardcoded array** in the page
component — it does NOT auto-shrink. The step indicator count is derived dynamically
(`loan-request-step-indicator.tsx:24` `totalSteps = steps.length`) so **no change is needed there**.
Every hardcoded reference below must be updated.

### 3a. `resources/js/pages/client/loan-request.tsx`

- **`steps` array (lines 62-123):** delete the `'authorization'` step object (lines **98-102**).
  Result: 11 steps.
- **`initialFormData` (lines 367-398):** delete the `authorization: { ...dataSections.authorization }`
  block (lines **384-386**).
- **`updateDataSection` Pick type (lines 482-499):** remove `| 'authorization'` from the union
  (line **487**).
- **Animated step blocks:** delete the authorization `<LoanRequestAnimatedStep show={currentStep === 7}>`
  block (lines **723-736**, the one wired to `dataSectionDefinitions.authorization`). Then renumber
  the `show={currentStep === N}` props on the following steps:
  - banking `8 → 7` (lines 738-751)
  - barangay `9 → 8` (lines 753-766)
  - declarations `10 → 9` (lines 768-781)
  - review `11 → 10` (lines 783-797)
- **`resolveStepFromErrors` (lines 263-342):**
  - delete the `if (key.startsWith('authorization.')) { stepMatches.push(7); ... }` branch
    (lines **304-307**).
  - renumber the remaining `push()` indices to match the new ordering:
    `banking. → 7` (was 8), `barangay. → 8` (was 9), `declarations. → 9` (was 10),
    `undertaking_accepted → 10` (was 11). Leave `insurance. → 5`, `health. → 6`,
    `co_maker_1. → 3`, `co_maker_2. → 4`, loan-detail keys `→ 0`, applicant `→ 1/2` unchanged.

### 3b. `resources/js/components/loan-request/loan-request-steps.tsx`

- **`DataSectionStepProps.sectionKey` Pick type (lines 442-461):** remove `| 'authorization'`
  (line **446**).
- **`LoanRequestReviewStep` → `dataSectionSummaries` (lines 881-902):** remove `'authorization'`
  from the section-key array (line **885**). This drops the Authorization summary card from the
  Review step.

### 3c. `resources/js/types/loan-requests.ts`

- **`LoanRequestFormData` interface:** remove `authorization: LoanRequestDataSectionValues;`
  (line **453**).
- **DO NOT** touch `LoanRequestDocumentKey` union member `| 'authorization'` (line **253**) — that
  is the AZ **document** key, still required.
- `LoanRequestCorrectionPayload` (line 459, `Omit<LoanRequestFormData, ...>`) inherits the change;
  no edit needed.
- `LoanRequestDataSections` is `Record<string, ...>` (line 244) — no edit needed.

### 3d. `resources/js/pages/client/loan-request-show.tsx` (awaiting-member-information edit flow)

- **`submitMemberInformation` payload (lines 388-397):** remove the line
  `authorization: currentDataSections.authorization,` (line **393**).
- The editable data-section UI iterates `dataSectionDefinitions` dynamically
  (`Object.entries(dataSectionDefinitions).forEach(...)`, line 299) and reads
  `currentDataSections[...]` by section key (line 327), so the authorization section disappears
  automatically once the backend stops returning it, and `release_method` appears under the banking
  section automatically. No further edits here.
- **DO NOT** touch: import alias `authorization as loanRequestAuthorizationDocument` (line 47),
  document label `authorization: 'Authorization'` (line 72), or the Wayfinder route call
  `loanRequestAuthorizationDocument(...)` (line 254) — all are the AZ **document download**.

> **Other pages reference only the AZ document, not the data section — leave unchanged:**
> `resources/js/components/loan-request/loan-request-detail-page.tsx:73,543-544`;
> `resources/js/pages/admin/loan-request-show.tsx:43,262`;
> `resources/js/pages/staff/loan-request-show.tsx:48,412`. Verified: these are the "Authorization PDF"
> download links / route imports.

### 3e. Correction dialog — `admin-loan-request-correction-dialog.tsx`

**No change required.** Verified: this dialog only edits Loan details + Applicant + Co-maker 1 +
Co-maker 2 (its `WIZARD_STEPS` array, lines 91-122, has no data-section step). It never references
`authorization`, `authorized_recipient_*`, `release_method`, or `banking`. (Point 9 / Point 4 of the
brief: confirmed not applicable.)

---

## 4. FORMREQUEST VALIDATION

The `authorization` array block must be removed everywhere, and `release_method` must be re-homed
under `banking`. Six FormRequest files reference these keys (one more than the brief's list of five —
the Workflow request was found via grep).

### 4a. `app/Http/Requests/Client/LoanRequestStoreRequest.php`
- **Delete** lines **99-102** (`'authorization' => [...]` array rule + the 3 child rules).
- **Edit** the `banking` array rule (line **103**) to append `release_method` to the `array:` list:
  `'banking' => ['required', 'array:payout_bank_name,payout_account_name,payout_account_number,payout_account_type,payout_atm_number,payout_bank_branch,payout_atm_holder_name,release_method'],`
- **Add** child rule (group it with the other `banking.*` rules, ~line 110):
  `'banking.release_method' => ['required', 'string', 'max:255'],` (keeps current required behavior).

### 4b. `app/Http/Requests/Client/LoanRequestDraftRequest.php`
- **Delete** lines **99-102**.
- **Edit** `banking` array rule (line **103**) to append `release_method` to the `array:` list.
- **Add** `'banking.release_method' => ['sometimes', 'nullable', 'string', 'max:255'],`.

### 4c. `app/Http/Requests/Client/SaveDraftRequest.php`
- **Delete** lines **105-108** (`'authorization' => ['sometimes','nullable','array']` + 3 child rules).
- `banking` rule here is the generic `['sometimes','nullable','array']` (line 109, no key list) — no
  array-list edit needed.
- **Add** `'banking.release_method' => ['sometimes', 'nullable', 'string', 'max:255'],`.

### 4d. `app/Http/Requests/Client/LoanRequestResolveActionRequest.php`
- **Delete** lines **44-46** (`'authorization' => ['sometimes','array:authorized_recipient_name,authorized_recipient_relationship']` + 2 child rules). *(Note: this request never had `release_method`.)*
- **Edit** the `banking` array rule (line **47**) to append `release_method`:
  `'banking' => ['sometimes', 'array:payout_bank_name,payout_account_name,payout_account_number,payout_account_type,payout_atm_number,release_method'],`
- **Add** `'banking.release_method' => ['sometimes', 'nullable', 'string', 'max:255'],`.
  *(Rationale: keeps `release_method` editable during the awaiting-member-information revision loop,
  now that it lives in banking.)*

### 4e. `app/Http/Requests/Admin/LoanRequestCorrectionRequest.php`
- **Delete** lines **58-61** (`$rules['authorization'] = ...` + the 3 `$rules['authorization.*']`).
- **Edit** `$rules['banking']` (line **62**) to append `release_method` to the `array:` list.
- **Add** `$rules['banking.release_method'] = ['sometimes', 'nullable', 'string', 'max:255'];`.

### 4f. `app/Http/Requests/Workflow/LoanRequestRequestMemberActionRequest.php`
- In the `field_keys.*` `Rule::in([...])` allow-list (lines 39-62): **remove**
  `'authorized_recipient_name'` (line **49**) and `'authorized_recipient_relationship'` (line **50**).
- **Optional:** add `'release_method'` to the same allow-list so staff can flag it for member
  revision (it is not currently present). Low priority; safe to include.

---

## 5. PDF FIELD MAP — `app/Services/LoanRequests/PdfFieldMaps/AuthorizationPdfFieldMap.php`

- **Delete** the field entry `'value' => 'authorization.authorized_recipient_name'` (lines **56-61**,
  the `x=26, y=78` block, including the `// TODO(calibrate-az)` comment above it if it only applies
  to these — keep the comment if it documents the whole region; safest to keep the comment).
- **Delete** the field entry `'value' => 'authorization.authorized_recipient_relationship'`
  (lines **62-68**, `x=26, y=86`).
- **KEEP** `'authorization.release_method'` (lines 69-75, `y=94`), `'authorization.payout_bank_name'`
  (76-82, `y=102`), `'authorization.payout_account_number'` (83-89, `y=110`).

**Confirmation (per brief point 5):** AZ is an FPDI overlay on a static PDF template
(`storage/app/templates/approved-loan-documents/pdf/authorization.pdf`, per
`LoanRequestDocumentCatalog.php:147`). Removing these two entries only **stops auto-filling** those
two lines — it does **not** require editing the template image. The recipient-name / relationship
lines simply print **blank** for hand-completion in person at release. No coordinate redesign needed.

> The `value` paths (`authorization.*`) here index into the **render payload** produced by
> `ApprovedLoanDocumentService` (§7), NOT the wizard data section. They keep working because §7 keeps
> emitting `release_method` + payout keys under its `authorization` render block.

---

## 6. APPLICABLE() GATE — `app/Services/LoanRequests/LoanRequestDocumentCatalog.php`

### 6a. `authorizationApplicable()` (lines 503-516)
The OR-gate currently fires on `release_method`, `authorized_recipient_name`,
`authorized_recipient_relationship`, `payout_bank_name`, `payout_account_number`. **Edit the
`hasAnyValue` array (lines 509-515)** to leave only:
```php
return $this->hasAnyValue($flatValues, [
    'payout_bank_name',
    'payout_account_number',
]);
```
Remove `release_method`, `authorized_recipient_name`, `authorized_recipient_relationship`. Per brief
point 6, the relocated `release_method` should **not** gate AZ generation.

### 6b. `'authorization'` definition `source_fields` (lines 132-140)
Remove `'authorized_recipient_name'` and `'authorized_recipient_relationship'` from the list. **Keep**
`release_method`, `payout_bank_name`, `payout_account_number`, `payout_bank_branch`,
`payout_atm_holder_name` (these still feed AZ and should still trigger regeneration via
`usesChangedFields()`).

**Why AZ still generates (not a regression):** `banking` is required at submit
(`LoanRequestStoreRequest`: `payout_bank_name` + `payout_account_number` are `required`), so **every**
approved request has non-empty payout fields → `authorizationApplicable()` returns `true` for every
member. The 🔒 "gates document generation" concern that previously protected the recipient fields is
fully satisfied by the payout fields alone. See Risk Assessment §10.

---

## 7. RENDER / SNAPSHOT PAYLOADS (auto-fill data source)

### 7a. `app/Services/LoanRequests/ApprovedLoanDocumentService.php`
In the `'authorization' => [ ... ]` render block (lines **899-930**): **delete**
`authorized_recipient_name` (lines 900-902) and `authorized_recipient_relationship` (lines 903-905).
**Keep** `release_method` (906-908) and all `payout_*` keys (909-929). These removed keys reference
`$flatValues['authorized_recipient_name'|'authorized_recipient_relationship']`, which will no longer
exist after the registry change + purge.

### 7b. `app/Services/LoanRequests/LoanRequestDocumentWorkflowService.php`
In the `'processing' => [ ... ]` snapshot block (lines **608-629**): **delete**
`'authorized_recipient_name' => ...` (line 616) and `'authorized_recipient_relationship' => ...`
(line 617). **Keep** `'release_method' => $flatValues['release_method'] ?? null` (line 618).

---

## 8. PURGE MIGRATION (new)

Create `database/migrations/2026_06_30_000000_purge_authorization_recipient_fields.php` (use the next
available timestamp; model exactly on `2026_06_29_203233_purge_removed_loan_request_fields.php`):

```php
<?php

use App\Models\LoanRequestDataEntry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Confirmed for unconditional removal — third-party authorization is handled
        // physically at release, never sourced from app data.
        LoanRequestDataEntry::query()
            ->whereIn('field_key', [
                'authorized_recipient_name',
                'authorized_recipient_relationship',
            ])
            ->delete();

        // release_method is relocated (not removed); realign legacy rows' section_key
        // from 'authorization' to 'banking' so stored metadata matches the new registry.
        LoanRequestDataEntry::query()
            ->where('field_key', 'release_method')
            ->update(['section_key' => 'banking']);
    }

    public function down(): void
    {
        // Irreversible — deleted rows cannot be recovered.
    }
};
```

- **Delete** only `authorized_recipient_name` + `authorized_recipient_relationship` rows.
- **Do NOT delete** `release_method` rows — instead realign their `section_key` (the second
  `update()` is cleanup; functionally optional because `flatValues` is keyed by `field_key`, but it
  keeps the DB consistent and avoids confusion).

---

## 9. TESTS

### 9a. `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`

1. **AZ pinning test** `authorization field map pins all field coordinates to calibrated values`
   (lines **946-984**): **NO CHANGE.** Verified — it only pins `applicant.full_name`,
   `applicant.address`, `loan.reference`, `loan.approved_amount`, `loan.approved_date`,
   `organization.company_name`. It does **not** pin recipient/relationship/release_method, so there
   are no pinned assertions to remove. *(The brief allowed for "3 or 2" pinned entries; the answer is
   zero.)*

2. **Render test** `authorization pdf prints recipient and release details` (lines **1404-1430**):
   **repurpose** (don't delete — `release_method` still prints).
   - Remove the persist calls for `authorized_recipient_name` (line 1410) and
     `authorized_recipient_relationship` (line 1411).
   - Remove the assertions `->toContain('MARIA C. SANTOS')` (1424) and `->toContain('SPOUSE')` (1425).
   - Keep `->toContain('ATM')`, `->toContain('LANDBANK')`, `->toContain('1122334455')`,
     `->not->toContain('Enterprise Bank')`.
   - Rename the test to e.g. `authorization pdf prints release and bank details`.

3. **Shared fixture** `approvedLoanDocumentsCreateDataEntries()` (lines 2498-2533): remove the
   `'authorized_recipient_name' => ['string', 'Authorized Recipient'],` (2510) and
   `'authorized_recipient_relationship' => ['string', 'Sibling'],` (2511) entries. **Keep**
   `'release_method' => ['string', 'Bank transfer'],` (2512).

4. **NEW regression test** (add near the AZ tests): assert AZ still generates with **only** payout
   data and no recipient/relationship/release_method present. Skeleton:
   ```php
   test('authorization pdf still generates from payout fields alone', function () {
       $admin = User::factory()->create();
       AdminProfile::factory()->create(['user_id' => $admin->user_id]);

       $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
       // NOTE: approvedLoanDocumentsCreateDataEntries() (called inside the helper) must
       // NO LONGER seed recipient/relationship after step 9a.3. Only payout fields present.
       approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_bank_name', 'string', 'LANDBANK');
       approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_account_number', 'string', '1122334455');

       $response = $this->actingAs($admin)
           ->get(route('admin.requests.documents.authorization', $loanRequest));

       $response->assertOk();
       $text = approvedLoanDocumentsExtractPdfText($response);
       expect($text)->toContain('LANDBANK')->toContain('1122334455');
   });
   ```
   *(Implementer: verify the helper `approvedLoanDocumentsCreateApprovedLoanRequestWithPeople()` does
   not pre-seed the removed fields; adjust so the test genuinely proves generation without them. May
   alternatively assert `LoanRequestDocumentCatalog::isApplicable(LoanRequestDocumentKey::Authorization, ...)`
   returns `true` for a `$flatValues` array containing only payout keys — a faster unit-style check.)*

### 9b. Loan-submission payload tests — move `release_method` into `banking`, drop `authorization`

Each of these submits a full create/submit payload with an `authorization` block (3 fields) and a
`banking` block. **Remove the `authorization` block; add `'release_method' => 'Bank transfer'` (or the
existing value) to the `banking` block.** Without this they 422 (banking.release_method now required
in Store; authorization key no longer validated).

| File | Block location |
|---|---|
| `tests/Feature/LoanRequestTest.php` | `authorization` at **203-207**, `banking` at 208-214 |
| `tests/Feature/LoanWorkflowAcceptanceTest.php` | `authorization` at **377-381**, `banking` at 382-388 |
| `tests/Feature/NotificationsTest.php` | `release_method` at **~1047** (locate the enclosing `authorization`/`banking` blocks) |
| `tests/Feature/LoanRequestPhaseFiveWorkflowTest.php` | `release_method` at **~221** |
| `tests/Feature/LoanRequestPhoneValidationTest.php` | matched grep for `authorized_recipient`/`release_method` — locate & apply same change |

### 9c. `tests/Feature/RemovedLoanRequestFieldsTest.php`
- The two payload-based tests send `release_method` under an `authorization` key
  (`save draft request strips authorized_recipient_contact...` at line 166;
  `loan request correction request no longer accepts...` at line 202). After relocation the
  `authorization` key is ignored (section removed). These tests assert that
  `authorized_recipient_contact` / `authorization_reason` are **not** persisted, which remains true,
  so they **still pass**. For accuracy, optionally move `release_method` into a `banking` key in those
  payloads. Low priority.
- `purge migration deletes authorization_reason and authorized_recipient_contact data entries`
  (lines 64-89) inlines the **old** migration's behavior (deletes only the two prior keys) and asserts
  `authorized_recipient_name` survives. It still passes as written. **Flag:** it is now slightly
  misleading (the new migration §8 deletes `authorized_recipient_name` in real data). Optional:
  add a sibling test asserting the **new** migration deletes `authorized_recipient_name` +
  `authorized_recipient_relationship` while preserving `release_method`.

### 9d. Step-count test
None exists. Verified: no test asserts "12 steps" (grep for `12` / `steps`/`toHaveLength` in
`tests/**/*LoanRequest*.php` returned only unrelated `requested_term => 12`). The step indicator
derives its count from `steps.length`, so nothing to update.

---

## 10. RISK ASSESSMENT

### 10a. Reversing the prior 🔒 "do not remove — gates document generation" decision — **safe**
`WIBS_DOCUMENT_FIELD_MAP.md:280-282` marked the three fields 🔒 because `authorizationApplicable()`
OR-gated on them. After §6 the gate keys on `payout_bank_name` OR `payout_account_number`, both of
which are **`required` at submit** in `LoanRequestStoreRequest` (lines 104, 106). Therefore **every**
approved loan request has non-empty payout data and AZ still generates for everyone. Removing the
recipient fields from the gate **cannot reduce** the set of requests that produce an AZ document → not
a regression. `release_method` is relocated (not deleted) and still prints on AZ; dropping it from the
gate is harmless for the same reason. *(Update `WIBS_DOCUMENT_FIELD_MAP.md` §4 / Summary tables to
reflect 🔒→removed/relocated if the implementer wants the doc kept current — optional.)*

### 10b. Mid-flight requests losing data — **acceptable per business confirmation**
The purge migration (§8) deletes `authorized_recipient_name` / `authorized_recipient_relationship`
rows for **all** requests regardless of status, including non-terminal ones
(`draft`, `submitted`, `under_review`, `awaiting_member_acceptance`, etc.). Any request that already
rendered an AZ PDF with a printed recipient name would, on regeneration, print those two lines blank.
**This is acceptable**: WIBS confirmed the recipient is always captured physically in person at
release and is never sourced from app data. `release_method` rows are **preserved** (only realigned),
so no member's payout-channel choice is lost. In-progress drafts on the (now-removed) wizard step lose
their recipient entries silently — acceptable for the same reason.

### 10c. Other `release_method` references — swept, no further work
Searched the whole codebase. `release_method` appears only in: the field registry, the 6 FormRequests,
the AZ field map, the document catalog gate/source_fields, the two render/snapshot payloads
(`ApprovedLoanDocumentService`, `LoanRequestDocumentWorkflowService`), and tests — **all covered
above**. Specifically confirmed **absent** from: staff processing screen (the staff-editable fields
are the `processing` section, `owner=staff`; `release_method` is `owner=member` and not in it),
reporting/exports (`app/Services/Reports/*` — no hits), and audit logging
(`LoanRequestDataChange` records changes generically by `field_key`, no hardcoded reference). Because
`flatValues` is keyed by `field_key` (not `section_key`), changing `release_method`'s section does
**not** affect any document rendering or gate — only which wizard step collects it.

---

## 11. IMPLEMENTATION CHECKLIST (ordered)

1. `LoanRequestDataService.php` — registry (§2).
2. Six FormRequests (§4a-f).
3. `AuthorizationPdfFieldMap.php` (§5).
4. `LoanRequestDocumentCatalog.php` — gate + source_fields (§6).
5. `ApprovedLoanDocumentService.php` + `LoanRequestDocumentWorkflowService.php` — payloads (§7).
6. Frontend: `loan-request.tsx`, `loan-request-steps.tsx`, `loan-requests.ts`,
   `loan-request-show.tsx` (§3a-d).
7. New purge migration (§8); run it.
8. Tests (§9a-c); add the new regression test + (optional) new-migration test.
9. Run the suite: `php artisan test` (or `vendor/bin/pest`). Build frontend / typecheck:
   `npm run build` (or `tsc`/`npm run lint`) to catch the `LoanRequestFormData.authorization`
   removal fallout.
10. `vendor/bin/pint --dirty` before finishing (per project Hard Rules).

> Per project Hard Rules: every change needs a Pest test (covered by §9), all validation stays in
> Form Requests (no inline `$request->validate()`), `AppUser` everywhere, enums stay in `app/Loan*.php`.
