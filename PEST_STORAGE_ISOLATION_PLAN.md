# Plan — Pest Storage Isolation for Approved-Loan Document Templates

> **Status:** Plan only. No implementation code has been written.
> **Scope:** how `storage/app/templates/approved-loan-documents/{pdf,excel,images}/*` (real,
> checked-out-of-git-but-production-live template files) get read and, in one test file, written
> during a Pest run. Root-caused directly this session by tracing every `storage_path()` /
> `Storage::` call in the relevant services and every test that touches them. No document-specific
> rendering/field-map logic is in scope — this is test-infrastructure only.

---

## 0. What's wrong, in one paragraph

`ApprovedLoanPdfTemplateService`, `ApprovedLoanExcelTemplateService`, and
`ApprovedLoanImageTemplatePdfService` all resolve their source template files with raw
`storage_path('app/templates/approved-loan-documents/...')` calls — never through the `Storage`
facade. `storage_path()` always resolves to the real physical `storage/` directory; it has no
testing-environment override anywhere in this app (`tests/TestCase.php` never calls
`useStoragePath()`, and `phpunit.xml` sets no `FILESYSTEM_DISK` override). `Storage::fake()`, used
elsewhere in the suite, is a no-op for this code path since it never touches the `Storage` facade.
Because there is no real isolation, one test file —
`tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php` — worked around it by hand: a
`beforeEach`/`afterEach` pair that backs up 9 real template files, overwrites them in place with
synthetic fixtures, runs the test, then copies the backups back. That manual swap is not
crash-safe: TCPDF's documented `die()`-on-error behavior (see memory
`pest_suite_pdf_crash_and_storage_corruption`) is a raw process exit that skips `afterEach`
entirely, so a die() during any single test in that file leaves the real production templates
permanently overwritten with test fixtures. This has already happened once for real.

---

## 1. Confirmed current state (read directly this session)

### 1.1 Where template paths get resolved, bypassing `Storage` entirely

```php
// app/Services/LoanRequests/ApprovedLoanPdfTemplateService.php:501-505
private function resolveTemplatePath(string $templateFilename): string
{
    $templatePath = storage_path(
        'app/'.trim(self::TEMPLATE_DIRECTORY.'/'.$templateFilename, '/'),
    );
    // TEMPLATE_DIRECTORY = 'templates/approved-loan-documents/pdf'
```

Identical pattern in:
- `ApprovedLoanExcelTemplateService::resolveTemplatePath()` (`:325-329`)
- `ApprovedLoanImageTemplatePdfService` (`:360-364`)
- `CalibrateApprovedLoanPdfFieldsCommand::calibratePdfTemplate()` (`:72-74`) — a manual dev
  command, not run by the suite; its *output* goes to `storage/app/tmp/calibrate-{doc}.pdf`, not
  back into the template directory, so it is not a corruption source.

All three production services read this path only — they never write back to it.
`ApprovedLoanPdfTemplateService::generate()` writes its rendered output to a caller-supplied
`$outputPath`, which traces back to `LoanRequestDocumentStorage::temporaryWorkingDirectory()` /
`newGeneratedDocumentPath()` — both go through `Storage::disk($this->documentDisk())->path(...)`,
a properly disk-abstracted, fakeable path. **The generation/download code path is not the
corruption source.**

### 1.2 Why `Storage::fake()` doesn't help here

`config/filesystems.php` defines only `local` (root `storage_path('app/private')`), `public`
(root `storage_path('app/public')`), and `s3`. The template directory
(`storage/app/templates/...`) isn't under either disk's root to begin with — it's addressed by a
raw `storage_path()` concatenation, not a disk. `Storage::fake('public')` (used at lines 624, 735,
1859, 1883, 1904, 1928 of the download test) swaps the `public` disk's underlying adapter to an
in-memory/temp fake; it has zero effect on any `storage_path()` call, faked or not. There is also
no `$this->app->useStoragePath()` call anywhere in `tests/TestCase.php`, `tests/Pest.php`, or any
service provider — confirmed via repo-wide grep. So `APP_ENV=testing` (set by `phpunit.xml`)
shares the exact same physical `storage/` directory as local dev and, structurally, production.

### 1.3 The existing workaround, and why it's unsafe

`tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php:34-43`:

```php
beforeEach(function () {
    config()->set('reports.pdf_driver', 'dompdf');
    approvedLoanDocumentsEnsureWmasterTable();
    approvedLoanDocumentsBackupTemplateFilesForTests();   // copies 9 real files → storage/app/testing-backups/
    approvedLoanDocumentsSeedTemplateFilesForTests();     // overwrites the 9 real files with synthetic fixtures
});

afterEach(function () {
    approvedLoanDocumentsRestoreTemplateFilesAfterTests(); // copies backups back over the real files
});
```

The 9 managed files (`approvedLoanDocumentsManagedTemplateFilesForTests()`, `:3876-3892`):
`grepalife-page-{1,2}.png`, `grepalife.pdf`, `loan-security-agreement.pdf`,
`undertaking-barangay-officials.pdf`, `affidavit-undertaking.pdf`, `authorization.pdf`,
`loan information sheet.pdf`, `plan-of-payment-disclosure-promissory-note.xlsx` — this is exactly
the 9-file set observed overwritten (by mtime) during this session's full-suite run.

This backup/overwrite/restore cycle runs **twice per test** (`beforeEach`/`afterEach` fire per
test, not per file) across the ~4,000-line file, so real production files are physically rewritten
dozens of times on every full-suite run — and correctness depends on every single one of those
tests reaching its `afterEach` normally. TCPDF's `die()`-on-error is a hard process exit (no
destructors, no exception, nothing PHPUnit/Pest can intercept), so any TCPDF failure mid-test in
this file skips the restore and leaves the real templates corrupted. This is precisely what memory
`pest_suite_pdf_crash_and_storage_corruption` documents as having already happened once
(mitigated 2026-07-16 by registering bold Calibri, which fixed *that specific* die() trigger, but
did not fix the underlying lack of isolation — the mechanism is still live and can be triggered by
any future TCPDF error).

On this session's run (829 passed, 0 failures, clean exit), no data was actually lost — the
restore ran correctly on every test — but 9 files' mtimes still churned, and content integrity was
only confirmed by an independent manual backup+hash-diff done outside the test framework, not by
anything the suite itself guarantees.

---

## 2. Blast radius — every test touching document generation

Grepped `tests/Feature` and `tests/Unit` for the template services, the `ApprovedLoanDocumentService`
download methods, and document-generation routes:

| File | Touches real templates? | How |
|---|---|---|
| `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php` | **Yes — writes** | `beforeEach`/`afterEach` backup-overwrite-restore of all 9 files, per test |
| `tests/Feature/ApprovedLoanPdfTemplateServiceShrinkToFitTest.php` | Yes — **read-only** | Calls `ApprovedLoanPdfTemplateService::renderContent('authorization.pdf', ...)` directly against the real file; asserts only on injected-field font-size operators, not template content |
| `tests/Feature/ApprovedLoanPdfTemplateServiceBoldFontTest.php` | Yes — **read-only** | Same pattern, real `authorization.pdf` |
| `tests/Feature/ApprovedLoanPdfTemplateServiceImageFieldTest.php` | Yes — **read-only** | Same pattern, real `authorization.pdf` |

No other test file references these services, `ApprovedLoanDocumentService`'s document methods, or
the approved-document download/package routes. So exactly one file is the write/corruption risk;
three others have a **read dependency on the real `authorization.pdf` existing on disk with
production-like dimensions**, which any fix must not silently break.

Pest runs sequentially in this project (`composer.json` `"test"` script: `php artisan test`, no
`--parallel` flag; confirmed no other CI invocation adds it). So there is no concurrent-write race
today — but that's incidental, not structural, and would become a real corruption vector the
moment anyone adds `--parallel` for speed.

---

## 3. Does anything rely on the current broken behavior?

Checked explicitly, since fixing isolation could break a test that's unknowingly depending on it:

- **No test reads back a real file that a prior test corrupted.** Every test in
  `ApprovedLoanDocumentPackageDownloadTest.php` gets a fresh synthetic fixture from its own
  `beforeEach` and never depends on another test's leftover state.
- **The three read-only render tests are coupled to the real `authorization.pdf`'s physical page
  dimensions** (216×330mm, per the fixture the download test uses to model it), but not to its
  *content* — they only assert on font-size operators from field-map-injected text. This is the
  one place a naive fix (e.g., relocating the source-of-truth path without providing the real file
  at the new location) would break passing tests. It is a coupling to worry about, not a bug being
  relied on.
- Nothing else in `tests/Unit` or `tests/Feature` references these paths.

---

## 4. Recommended fix

**Root cause is singular**, not multiple independent bugs: every affected service resolves its
template path via bare `storage_path()`, and nothing in the test bootstrap redirects
`storage_path()` for `APP_ENV=testing`. Fix that one thing and the manual backup/restore workaround
becomes unnecessary.

1. **Redirect the physical storage root for the testing environment**, once, in
   `tests/TestCase.php::setUp()` (or a shared trait applied via `Pest.php`'s
   `pest()->extend(...)->use(...)`), via Laravel's standard mechanism:
   ```php
   protected function setUp(): void
   {
       parent::setUp();
       $this->app->useStoragePath(storage_path('framework/testing/disks/'.Str::random(8)));
       // then copy/symlink the real template fixtures the suite needs into the new root once
   }
   ```
   `useStoragePath()` is the Laravel-supported way to make *every* `storage_path()` call —
   including the ones these services use directly — resolve somewhere disposable, without touching
   the three services' code at all.

2. **Seed the redirected root with the fixture templates.** Two options, and the right one differs
   per consumer:
   - For `ApprovedLoanDocumentPackageDownloadTest.php`: its existing
     `approvedLoanDocumentsSeedTemplateFilesForTests()` already generates synthetic fixture
     PDFs/images — keep that function, but point it at the new isolated root instead of the real
     one. Once writes land in an isolated root, the entire backup/restore apparatus
     (`approvedLoanDocumentsBackupTemplateFilesForTests`, `RestoreTemplateFilesAfterTests`,
     `BackupDirectoryForTests`, `RestoreDirectoryForTests`, `BackupFileForTests`,
     `RestoreFileForTests` — roughly `:3805-3927`) becomes dead code and should be deleted.
   - For the 3 read-only render tests: copy the **real** `authorization.pdf` (just that one file)
     into the isolated root once per test run, so they keep exercising production-shaped geometry
     instead of a synthetic stand-in — preserves current behavior exactly, at zero corruption risk
     since the copy target is now disposable.

3. **Do not attempt to fix this by routing the services through `Storage::disk()` instead of
   `storage_path()`.** That would also work in principle, but it's a much larger diff (three
   services' path-resolution logic, plus `CalibrateApprovedLoanPdfFieldsCommand`, plus anything
   else that assumes a real filesystem path for `Fpdi::setSourceFile()` / TCPDF font registration)
   for no additional safety over option 1 — `useStoragePath()` protects the *entire* storage tree
   for tests in one place, including any future service that makes the same `storage_path()`
   mistake.

---

## 5. Explicit non-goals

- Not fixing the TCPDF `die()`-on-error behavior itself (already addressed for the known
  bold-Calibri trigger 2026-07-16; a general catch-all would be a separate, TCPDF-configuration
  concern, not a storage-isolation one).
- Not touching `ApprovedLoanDocumentService`'s document-generation output path
  (`LoanRequestDocumentStorage`) — confirmed already disk-abstracted and fakeable; out of scope.
- Not adding `--parallel` test execution or protecting against it — flagged as a latent risk this
  fix happens to close as a side effect, not a driver for the work.
