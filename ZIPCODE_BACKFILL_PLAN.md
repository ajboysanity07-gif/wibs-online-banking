# ZIPCODE Backfill & Grepalife PDF Fix Plan

> Self-contained plan. Reproduce in a new session: read this file, implement the
> changes below, run the verification steps at the end.
> Date: 2026-08-02.

## Status (updated 2026-08-02)

- **Part 1 — DONE.** `profile.tsx` else-branch read-only ZIP input added; `tsc --noEmit` passes.
- **Part 2 — DONE.** `personDocumentData(?Wmaster $memberRecord = null)` + `address_zip`/`office_zip` fallbacks; applicant-only call site.
- **Part 3 — DONE.** `app/Console/Commands/BackfillZipCodesCommand.php` created and live-applied: **81 applicant rows backfilled** (req 72–98 confirmed, e.g. req 90→8501, 91→8309, 92/93/96/97/98→8307; 4 rows no `zone_number`).
- **Part 4 — DONE.** `zone_number` added to test wmaster helper; render-fallback test + `tests/Feature/BackfillZipCodesCommandTest.php` (9 tests). All pass.
- **Verification — mostly DONE.** Live backfill applied & verified via DB. Remaining manual: real Grepalife PDF visual check (zip at x≈191.7mm, y=86.5mm) and profile Personal-tab visual check.

## Problem

1. **Grepalife PDF zip fields are blank** — `applicant.address_zip` /
   `applicant.office_zip` are NULL in `loan_request_people` for **every existing
   loan request (id 72–98)**, so the zip columns on the Grepalife PDF render empty.
   The ZIP columns were added recently (commit `34f4d9d`, migration
   `2026_08_02_000000_add_zip_to_loan_and_profile_tables.php`) after those requests
   were submitted.
2. **Profile settings Personal tab hides the home ZIP** — the read-only ZIP field
   only renders inside the structured-address branch; members whose `wmaster` has
   only a legacy/composed address get the `else` branch, which has no ZIP field.
3. **Many old members have `wmaster.zone_number`** (the home ZIP) but it was never
   propagated into their loan-request applicant snapshots or profile display.

## Facts (verified against live DB)

- `wmaster.zone_number` is the **only** home-ZIP source. `member_application_profiles`
  has **no home-zip column** (only `employer_business_address_zip` for office).
- Example live rows: req 90→zone `8501`, 91→`8309`, 92/93/96/97/98→`8307`.
- All `loan_request_people.address_zip` and `employer_business_address_zip` are NULL
  for requests 72–98.
- `member_application_profiles.employer_business_address_zip` is empty (0 rows) —
  **office ZIP cannot be backfilled** (no source; `wmaster` has no employer-zip field).
- `AppUser::wmaster()` is a BelongsTo (AppUser.php:106).
- `ApprovedLoanDocumentService::resolveMemberWmaster()` already resolves a member's
  wmaster via the user relation or `loan_request.acctno`.

## Decisions (locked with user)

- **Personal tab home ZIP**: read-only display from `wmaster.zone_number`, **always
  shown**. No new profile column / schema change.
- **Old records**: backfill command + render-time fallback (both).

---

## Part 1 — Profile settings Personal tab: home ZIP always visible (read-only)

**File:** `resources/js/pages/settings/profile.tsx`

The member-record address block has two branches:
- Structured branch (approx. lines 1601–1690): street/city/province + ZIP
  (read-only, `defaultValue = memberAddressZip`, `disabled`).
- `else` branch (approx. lines 1691–1713): a single "Address" input only — **no ZIP**.

**Change:** add a read-only "ZIP code" input to the `else` branch, mirroring the
structured branch's ZIP field (lines 1670–1688):

```tsx
<div className="grid gap-2">
    <Label htmlFor="member_record_address_zip">
        ZIP code
    </Label>

    <Input
        id="member_record_address_zip"
        className={cn(
            'mt-1 block w-full',
            hasWmasterValue(memberAddressZip) && WMASTER_VALUE_CLASS,
        )}
        defaultValue={memberAddressZip}
        placeholder="Not available"
        disabled
    />
</div>
```

Result: `wmaster.zone_number` always displays in the Personal tab regardless of
address shape. No backend/schema change. (No JS test infra exists — pure
presentation change, not Pest-testable.)

> **Work & Finances tab:** the editable office ZIP (`employer_business_address_zip`,
> `profile.tsx:2233`) already exists and is not gated. If a user reports it missing,
> it is a stale build on the running instance — re-verify against the current code.

---

## Part 2 — Render-time fallback in ApprovedLoanDocumentService

**File:** `app/Services/LoanRequests/ApprovedLoanDocumentService.php`

1. `personDocumentData()` (line 1361): add optional `?Wmaster $memberRecord = null` param.
2. `address_zip` (line 1394):
   ```php
   'address_zip' => $this->normalizeText($person?->address_zip)
       ?? $this->normalizeText($memberRecord?->zone_number),
   ```
3. `office_zip` (line 1408):
   ```php
   'office_zip' => $this->normalizeText($person?->employer_business_address_zip)
       ?? $this->normalizeText($loanRequest->user?->memberApplicationProfile?->employer_business_address_zip),
   ```
4. Call site (line 1339) — pass `$memberRecord` (already resolved at line 916) for
   the **applicant only**:
   ```php
   'applicant' => $this->personDocumentData($applicant, $loanRequest, $memberRecord),
   ```
5. Co-maker calls (lines 1340–1341) stay unchanged (no fallback).
6. `normalizeText()` trims and returns null for blank (`ApprovedLoanDocumentService.php:2017`).

---

## Part 3 — Backfill command: `loan-requests:backfill-zip-codes`

**New file:** `app/Console/Commands/BackfillZipCodesCommand.php`

Mirror `app/Console/Commands/BackfillHealthSmokingStatusCommand.php` (dry-run by
default, `--fix` to write, `--limit`, `chunkById`).

```php
protected $signature = 'loan-requests:backfill-zip-codes
    {--fix : Apply the address_zip backfill (default is dry run)}
    {--limit= : Limit the number of applicant rows to scan}';
protected $description = 'Backfill applicant address_zip from the member wmaster zone_number.';
```

Behavior:
- Guard: tables `loan_requests`, `loan_request_people` exist and
  `loan_request_people` has an `address_zip` column.
- Query `LoanRequestPerson` where `role = applicant` and `address_zip` is
  NULL/blank, ordered by id, chunked (`--limit` respected).
- Resolve wmaster for each row:
  1. `$loanRequest->loadMissing('user.wmaster')` → `$loanRequest->user?->wmaster`
  2. else `$loanRequest->acctno` (or `user->acctno`) → `Wmaster::where('acctno', $acctno)->first()`
- If `zone_number` non-blank → set `address_zip` = trimmed `zone_number` (with `--fix`).
- Log each row (dry-run shows what would change), then print summary:
  `Checked: N / Already set: N / Backfilled: N / No wmaster source: N`.
- **Office ZIP**: explicitly reported as no-source (wmaster has no employer zip;
  `member_application_profiles.employer_business_address_zip` is NULL) — never guessed.

---

## Part 4 — Tests (Pest)

### 4a. Grepalife render-fallback test

**File:** `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`

- Add `'zone_number'` to the missing-column list in
  `approvedLoanDocumentsEnsureWmasterTable()` (line 39) so the test `wmaster` table
  has the column.
- New test (mirror `grepalife pdf includes structured applicant fields`, line 633):
  - Create admin + `approvedLoanDocumentsCreateApprovedLoanRequestWithPeople()`.
  - Leave the applicant's `address_zip` NULL.
  - `DB::table('wmaster')->updateOrInsert(['acctno' => $loanRequest->acctno], ['zone_number' => '8501', ...])`.
  - GET `admin.requests.documents.grepalife`.
  - Assert `approvedLoanDocumentsExtractPdfText($response)` contains `8501`.

### 4b. Backfill command test

**New file:** `tests/Feature/BackfillZipCodesCommandTest.php`
(model on `tests/Feature/BackfillHealthSmokingStatusCommandTest.php`)

Cover:
- Dry run (`loan-requests:backfill-zip-codes`) writes nothing.
- `--fix` copies `wmaster.zone_number` → applicant `address_zip`.
- Skips rows where `address_zip` already set.
- Applicant-only (co-maker `address_zip` untouched).
- Rows with no matching wmaster are reported and not written.
- `--limit` respected.
- Guard: skips gracefully if `address_zip` column missing.

Helper setup per test: create member with `acctno`, insert `wmaster` row with
`zone_number`, create `LoanRequest` (status Approved) + `LoanRequestPerson`
applicant/co-maker snapshots.

---

## Verification

1. `vendor/bin/pint --dirty`
2. Tests:
   ```
   php artisan test --filter="Grepalife|BackfillZip|ApprovedLoanDocument"
   ```
3. Live backfill (dry-run first, then apply):
   ```
   php artisan loan-requests:backfill-zip-codes
   php artisan loan-requests:backfill-zip-codes --fix
   ```
   Expect requests 90–98 applicant rows to be backfilled (e.g. `8307`).
4. Generate a real Grepalife PDF for an old request (e.g. #96) and visually confirm
   the home ZIP renders in the zip column (x≈191.7mm, y=86.5mm).
5. Check profile settings Personal tab for a member whose wmaster has only a legacy
   address — the read-only ZIP field should now always appear.

## Key reference files

- `app/Services/LoanRequests/ApprovedLoanDocumentService.php`
- `app/Services/LoanRequests/PdfFieldMaps/GrepalifePdfFieldMap.php`
- `app/Console/Commands/BackfillHealthSmokingStatusCommand.php`
- `resources/js/pages/settings/profile.tsx`
- `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`
- `tests/Feature/BackfillHealthSmokingStatusCommandTest.php`
- `app/Support/SettingsPageData.php`
