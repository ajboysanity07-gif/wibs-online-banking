# Plan — Affidavit of Undertaking (AU) Visual Fidelity Pass

> **Status:** Plan only. No implementation code has been written.
> **Relationship to prior plan:** `AFFIDAVIT_OF_UNDERTAKING_REBUILD_PLAN.md` covered the from-scratch
> artwork build and data wiring (commits `168b5d5`, `b7c023a`, `2046aad`, `95ed269`, `5fd9a23` — all
> landed). This plan is a **follow-up correction pass**: a closer side-by-side comparison against the
> real reference document surfaced 5 visual-fidelity gaps in that already-shipped artwork/field-map.
> Nothing here contradicts the prior plan's data-wiring decisions (§2/§5 there) — nothing is being
> re-litigated, only the visual presentation is being corrected.
> **Implementer:** every file path, line number, and coordinate below was confirmed by direct file
> inspection during plan-writing (this session), not carried over unverified from the investigation
> brief. Where the investigation brief flagged something as "confirm before assuming," the confirmed
> answer is stated explicitly (see §1.4).

---

## 0. What's wrong, in one table

| # | Issue | Fix |
|---|---|---|
| 1 | Paragraph 1's GNTHP amount and deposit Account Number are separate labeled sub-lines; reference has them inline in the sentence | Rewrite sentence text, move 2 fields inline, shift 3 remaining sub-lines up |
| 2 | Notarial block has 3 extra separate labeled lines (Province, Valid ID no., ID issued at) not present in reference | Delete all 3 lines; move `notarial.province` inline into the existing notarial sentence |
| 3 | Page size is A4 (210×297mm); reference is PH Legal (215.9×330.2mm) | Rebuild artwork at Legal size |
| 4 | Header is a static baked mark + typeset org name/tagline; Application Form already has a dynamic, admin-uploadable header | Build a generic `image` field type, wire AU to `organization.report_header.*` |
| 5 | Doc/Page/Book/Series render as one horizontal row (reference stacks them vertically); font is 8.5pt vs. reference's 10pt | Restack vertically; bump to 10pt (Legal-size + paragraph/notarial consolidation frees enough vertical space) |

Item 5(c) (header title beside "Name of Affiant:" vs. centered above the table) is a **known, accepted
deviation** — not part of this plan, do not touch it.

---

## 1. Confirmed current state (read directly this session, not assumed)

### 1.1 Field map — `AffidavitUndertakingPdfFieldMap::fields()` (18 entries today)

`app/Services/LoanRequests/PdfFieldMaps/AffidavitUndertakingPdfFieldMap.php`:

| value | x | y | size | notes |
|---|---|---|---|---|
| `applicant.full_name` | 40.5 | 47.7 | 10 | |
| `applicant.age` | 25.17 | 55.7 | 9 | |
| `applicant.civil_status` | 80.16 | 55.7 | 9 | |
| `applicant.nationality` | 137.83 | 55.7 | 9 | |
| `applicant.address` | 18 | 65 | 8 | MultiCell, width 174, line_height 4 |
| `applicant.position_or_designation` | 48.34 | 76.7 | 9 | |
| `applicant.employer_or_business` | 29.83 | 84.7 | 9 | |
| `applicant.office_address` | 18 | 94 | 8 | MultiCell, width 174, line_height 4 |
| `loan.gnthp` | 63.33 | 123.7 | 9 | **moves inline (§2)** |
| `authorization.payout_account_number` | 50 | 129.7 | 9 | **moves inline (§2)** |
| `authorization.payout_atm_number` | 51.16 | 135.7 | 9 | shifts up (§2) |
| `authorization.payout_bank_name` | 43.33 | 141.7 | 9 | shifts up (§2) |
| `authorization.payout_bank_branch` | 37.17 | 147.7 | 9 | shifts up (§2) |
| `loan.approved_date` | 92.33 | 219.97 | 9 | unaffected |
| `notarial.signing_place` | 159.04 | 219.97 | 9 | unaffected — already correctly inline |
| `notarial.province` | 58 | 258.42 | 8 | **deleted here, re-added inline (§3)** |
| `notarial.valid_id_issued_at` | 43.5 | 270.42 | 8 | **deleted (§3) — no reference equivalent** |
| `notarial.series_year` | 164.17 | 280.42 | 8 | y shifts (§3, vertical restack) |

A code comment at lines 133–136 confirms Doc No./Page No./Book No. (`x≈30.5/76.67/121, y=280.42`) and
Valid ID number (`x≈43.17, y=264.42`) are **intentionally blank artwork space** with no field-map entry
— the notary fills them by hand. That does not change; only their visual layout (horizontal → vertical)
changes.

### 1.2 `ApprovedLoanPdfTemplateService` — confirmed exactly as the investigation described

`app/Services/LoanRequests/ApprovedLoanPdfTemplateService.php`:

- `renderField()` (lines 161–216) dispatches on `$field['type']`: `'signature'` → `renderSignatureField()`
  (242–255), `'check'` → `renderCheckField()` (222–236), anything else → plain text. **No `'image'` case
  exists yet.**
- `renderSignatureField()` → `writeSignature()` (311–366): resolves the path via `resolveSignaturePath()`
  (379–388, checks `Storage::disk('public')` then falls back to `storage_path('app/public/...')`), fits
  it into the target box via `fitImageToBox()` (413–429), which is a **thin wrapper around
  `DocumentSignaturePlacement::calculateFromImagePath()`** — confirmed generic (aspect-fit math off
  `getimagesize()`, a fallback path if the image can't be read, no signature-specific processing) — safe
  to reuse for a header image.
- `writeSignature()` **does** call `SignaturePngService::prepareOverlayImage()` before placement — this
  step is signature-specific (background/whitespace handling for hand-drawn signature PNGs) and must
  **not** be reused for the header image; the new `image` type should call `pdf->Image()` directly on the
  resolved path, skipping that service entirely.
- Page size is read per source file: `renderTemplateBytes()` (103–140) calls `$pdf->importPage()` then
  `$pdf->getTemplateSize($templateId)` (115) and uses that to `AddPage()` (121–124) — **the page size
  passed to `new Fpdi('P', 'mm', 'A4', ...)` in `makePdf()` (line 476) is only a constructor default**,
  overridden per page from each template's own MediaBox. Confirms §3's "zero risk to UB/Authorization"
  claim: only `affidavit-undertaking.pdf`'s own MediaBox needs to change.

### 1.3 Header data — confirmed already flowing, confirmed rendering pattern to mirror

- `ApprovedLoanDocumentService::buildDocumentData()` populates `organization.report_header` from
  `$branding['reportHeader']` (line ~819 today — line numbers in this file are moving as other work on
  this branch lands, don't hardcode this number in code comments).
- `OrganizationSettingsService::resolveReportHeader()` (line ~565) returns
  `['designPath' => ?string, 'designUrl' => ?string, 'designData' => ?string]` — `designData` is a data
  URI, already used by HTML-rendered reports.
- **Fallback pattern to mirror**, confirmed at `resources/views/reports/partials/report-header.blade.php`:
  if `designData` is falsy, render a plain-text fallback title instead of an image — never crash on an
  unconfigured header. AU's TCPDF equivalent: if the header image path can't be resolved, **skip drawing
  it silently** (matching `writeSignature()`'s existing `if ($signaturePath === null ...) return;` guard)
  rather than attempting a text fallback inside `ApprovedLoanPdfTemplateService` — a text-fallback title
  would need its own font/position decisions PDF-side that HTML doesn't have to make, and per the brief
  this case isn't expected to occur in practice for MRDINC. Note it as a known edge case, don't over-build
  it.

### 1.4 Resolving the investigation's flagged uncertainty — `notarial.valid_id_issued_at`

The investigation brief flagged: *"this field was already removed/reverted to blank in a prior correction
pass; confirm current actual state before assuming it still has a value source."* Confirmed this session,
`ApprovedLoanDocumentService::buildDocumentData()` (`'notarial'` block, ~955–966):

```php
'notarial' => [
    'signing_place' => $this->normalizeText($branding['businessAddress2'] ?? null),
    'province' => $this->normalizeText($branding['businessAddress3'] ?? null),
    'valid_id_issued_at' => $this->normalizeText($branding['businessAddress2'] ?? null),
    'series_year' => $documentDate?->format('Y'),
],
```

**It was not reverted to blank** — it still resolves to `businessAddress2`, same source as
`signing_place`. This doesn't change the plan (§3 still removes the field-map entry and artwork line,
since the reference has no "ID issued at" line at all), but it means the removal is purely an AU
presentation decision, not a data-availability question — the value simply won't be printed anywhere on
AU going forward. Leave the `buildDocumentData()` key itself in place (harmless, unused by AU after this
change, and may be useful if another document's notarial block wants it later — out of scope to remove
it here).

### 1.5 `LoanRequestDocumentCatalog` — confirmed AU entry

`app/Services/LoanRequests/LoanRequestDocumentCatalog.php:103–126`:

```php
'affidavit_undertaking' => [
    'template_version' => 'affidavit-undertaking-v2',
    'source_fields' => ['payout_bank_name', 'payout_account_number', 'payout_atm_number',
                         'payout_bank_branch', 'guaranteed_net_take_home_pay'],
    'source_paths' => ['loan_request.recommended_amount', 'loan_request.recommended_term', 'applicant.'],
    ...
],
```

`template_version` drives a staleness check — `LoanRequestDocumentWorkflowService` (~line 155) compares
the catalog's current `templateVersionFor()` against each document's stored `template_version` and flags
`GeneratedStale` on mismatch. **Bump `'affidavit-undertaking-v2'` → `'affidavit-undertaking-v3'` in the
Phase 3 commit** so every already-generated AU document (any loan request that reached the point of AU
generation before this change ships) is correctly flagged stale and regenerated with the new artwork/
coordinates, rather than silently continuing to serve the old visual layout. `source_fields` /
`source_paths` themselves need no change — no new EAV keys or data paths are introduced by this plan
(the header comes from `organization.*`, already covered by `buildDocumentData()` unconditionally; no
`'organization.'` wildcard needs adding to `source_paths` since org settings changes are rare and not
per-loan-request state the staleness system needs to track the same way applicant edits are).

### 1.6 No committed artwork-builder script exists

`git show --stat 168b5d5` (the commit that built the current AU artwork) touched **only the binary PDF
file** — no `.php`/generation script was committed alongside it. The artwork was built via a throwaway
script (TCPDF, run once, discarded) and only its output committed. **Phase 2 of this plan follows the
same precedent**: write a throwaway generation script in the scratchpad (not `git add`ed), run it once to
produce the new `affidavit-undertaking.pdf`, commit only the resulting binary. Document the general
approach (page size, font, section layout) in the Phase 2 commit message the way `168b5d5` did, since
there's no script in the repo to serve as documentation later.

---

## 2. Phase 1 — Generic `image` field type (infrastructure only, no AU wiring)

**Goal:** `ApprovedLoanPdfTemplateService` can render an `image`-type field. Zero behavior change for any
existing document (AU, UB, Authorization, or any other) until a field map actually declares one.

1. In `renderField()` (line 161), add a third dispatch branch:
   ```php
   if ($type === 'image') {
       $this->renderImageField($pdf, $field, $documentData);
       return;
   }
   ```
2. New `renderImageField()`, modeled directly on `renderSignatureField()` (242–255) but **without** the
   `SignaturePngService::prepareOverlayImage()` step (§1.2 — that step is signature-specific):
   - Resolve the relative path via `resolveValue($field['value'], $documentData)`.
   - Resolve to an absolute path the same way `resolveSignaturePath()` does (379–388) — reuse that method
     directly, it has no signature-specific logic despite the name; a rename is optional polish, not
     required for this plan.
   - If the resolved path is `null` or the file doesn't exist, **return without drawing anything** (same
     guard style as `writeSignature()` line 320) — this is the graceful-fallback behavior from §1.3.
   - Fit into the target box via `fitImageToBox()` (413–429, already generic per §1.2) using the field's
     `x`/`y`/`width`/`height`.
   - Call `$pdf->Image(...)` directly with the resolved dimensions — mirror the existing `Image()` call in
     `writeSignature()` (342–360) for parameter consistency (same `$pdf->Image()` signature/flags), but
     skip the `finally { File::delete(...) }` cleanup block since there's no temporary overlay file being
     created (no `prepareOverlayImage()` call means nothing temporary to clean up).
3. Support the same optional placement options `signature` fields already support (`scale`, `max_width`,
   `max_height`, `offset_x`, `offset_y` via `signaturePlacementOptions()`, 441–450) — reuse that method
   unchanged, it's already generic over any field array.
4. **No `AffidavitUndertakingPdfFieldMap.php` change in this phase** — this is infrastructure only,
   deliberately unused until Phase 3.
5. **Verification:**
   - Existing Pest coverage for signature-type fields (search `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`
     for `'type' => 'signature'` assertions) must still pass unmodified — confirms zero regression to the
     existing dispatch branches.
   - Add a narrow unit/feature test exercising the new `image` branch directly against a field map fixture
     (a small inline test double implementing `ApprovedLoanPdfFieldMap` with one `image`-type entry
     pointing at a known test asset) — assert the output PDF contains image data at roughly the expected
     position (byte-length sanity or `Imagick`/`Smalot\PdfParser` inspection, whichever pattern the
     existing signature tests already use in this suite — mirror it, don't invent a new assertion style).
   - Add a test for the graceful no-op case: `image`-type field with an unresolvable path renders no
     error and no output for that field (mirrors how the existing signature tests presumably cover a
     missing-signature-path case — check `writeSignature`'s existing test coverage for the pattern to
     mirror before writing a new one from scratch).
6. `vendor/bin/pint --dirty`.
7. **Rollback:** revert the commit; `renderField()`'s new branch is purely additive, nothing else in the
   class changes shape.
8. **Commit message must state explicitly:** the `image` field type is built generically (reusable later
   by UB, Authorization, or any other approved-loan document) but **this plan only wires it into AU** —
   using it elsewhere is out of scope here and must be a separate future change.

---

## 3. Phase 2 — Rebuild `affidavit-undertaking.pdf` base artwork

**Goal:** replace the current 62,458-byte artwork (from commit `168b5d5`) with corrected artwork. No
`AffidavitUndertakingPdfFieldMap.php` changes yet — this phase is pure static PDF content.

1. **Page size:** Legal, 215.9mm × 330.2mm, portrait — construct the throwaway builder script's TCPDF/FPDF
   instance accordingly (§1.6 — no repo script exists to edit; write a new one in scratchpad, don't commit
   it).
2. **Font:** 10pt body text throughout (up from 8.5pt) — confirm every paragraph and table cell still fits
   within the Legal-size page after the paragraph-1/notarial-block consolidation frees vertical space; do
   not assume it fits without rendering and checking.
3. **Paragraph 1 rewrite:** replace the current text ("...Guaranteed Net Take Home Pay (Guaranteed NTHP)
   which is credited to my Deposit account, as follows:" followed by 5 separate sub-lines) with:
   > "...Guaranteed Net Take Home Pay of ___ (Guaranteed NTHP) which is credited to my Deposit account
   > under Account Number: ___"

   followed by only 3 indented sub-lines: ATM Account No., Bank Name, Branch. The prefix
   "...Guaranteed Net Take Home Pay of " alone measures ~153.5mm at Helvetica 8.5pt against a ~176mm
   content width — re-measure at the new 10pt size before finalizing the wrap point (10pt will be wider
   per-character than the 8.5pt measurement this figure was taken at; don't reuse the 8.5pt number
   directly for the 10pt layout, remeasure with `GetStringWidth()` at the final font size).
4. **Notarial block:** the existing "SUBSCRIBED AND SWORN..." sentence with its baked-in underscore blanks
   is correct and stays untouched. Delete the 3 separate labeled lines below it: "Province where signed:",
   "Valid ID no.:", "ID issued at:". Reserve inline blank space within the notarial sentence itself for
   `notarial.province` at the position described in §4 below (this phase reserves the visual space; Phase
   3 stamps the coordinate).
5. **Doc/Page/Book/Series:** change from one horizontal row to 4 stacked vertical lines, using the freed
   space from the Legal page and the two consolidations above.
6. **Header:** do **not** bake header artwork into this PDF at all — reserve blank space at the top of the
   page sized to fit the org's report-header image (Phase 4's `image` field will draw into this reserved
   box at render time; baking a static header into the artwork would defeat the point of Phase 4). Keep
   the existing "AFFIDAVIT OF UNDERTAKING" beside "Name of Affiant:" layout as-is (§0 — accepted deviation,
   not touched).
7. **Everything else** (title-beside-table layout, the applicant data table, paragraphs 2–5, signature/
   date/place line) carries over unchanged from the current artwork — this phase is a targeted correction,
   not a full re-derivation from the reference docx.
8. **Verification (required, not "file exists"):**
   - `php artisan loan-documents:calibrate-fields au` → confirms the new artwork loads under FPDI (correct
     page size reported, no corruption) and produces a fresh grid overlay baseline for Phase 3.
   - Render the new artwork standalone (no field-map overlay) and do a **side-by-side visual comparison**
     against the reference document render — paragraph 1's wrap, the 3-sub-line block, the notarial
     block's removed lines, the vertical Doc/Page/Book/Series stack, and the reserved header space should
     all visually match the reference's intent (the header space just won't have an image yet, since
     that's Phase 4).
9. **Rollback:** prior artwork restorable via
   `git checkout <commit-before-this-one> -- storage/app/templates/approved-loan-documents/pdf/affidavit-undertaking.pdf`.
   State the exact prior commit hash in the Phase 2 commit message.
10. **Commit:** artwork binary only, no PHP/TS changes. Document the builder-script approach in the commit
    message per §1.6 (page size, font, what changed vs. what carried over) since no script survives in the
    repo to explain it later.

---

## 4. Phase 3 — Wire the field map to the new artwork

**Goal:** `AffidavitUndertakingPdfFieldMap.php` matches Phase 2's artwork exactly, and AU's header renders
via the new `image` field type from Phase 1.

1. **Paragraph 1 fields:**
   - `loan.gnthp`: move inline into the rewritten sentence, remeasured x/y against the actual Phase 2
     artwork (the investigation's x≈153.5mm-prefix measurement was taken at 8.5pt on A4 — remeasure fresh
     at 10pt on Legal, don't reuse the old number).
   - `authorization.payout_account_number`: move inline (same sentence, "under Account Number:" blank).
   - `authorization.payout_atm_number`, `authorization.payout_bank_name`, `authorization.payout_bank_branch`:
     shift y upward to sit directly under the now-shorter paragraph, spacing matched to the artwork's new
     line positions (not a fixed mm offset carried over from the old layout).
2. **Notarial block:**
   - Delete the `notarial.province` entry at its old position (x=58, y=258.42) and re-add it **inline**
     within the notarial sentence at the position reserved in Phase 2 (investigation measured this at
     x≈109.8mm, y≈242.91mm on the old A4 layout with ~38mm of blank width — remeasure against the actual
     Legal-size, 10pt Phase 2 artwork rather than trusting that figure directly, since both the page size
     and font size changed).
   - Delete the `notarial.valid_id_issued_at` entry entirely (x=43.5, y=270.42) — no reference equivalent
     (§1.4 — this is a presentation removal, not a data-source problem).
   - `notarial.series_year`: update y to match the new vertical Doc/Page/Book/Series stack from Phase 2.
   - `notarial.signing_place` (x=159.04, y=219.97): **no change** — already correctly inline, confirmed
     unaffected by any of this phase's other moves.
3. **Header:** add a new `image`-type entry:
   ```php
   [
       'page' => 1,
       'type' => 'image',
       'x' => /* reserved header box from Phase 2 */,
       'y' => /* reserved header box from Phase 2 */,
       'width' => /* reserved header box width */,
       'height' => /* reserved header box height */,
       'value' => 'organization.report_header.designPath',
   ]
   ```
   confirm `resolveValue()`'s `data_get()` path resolves `organization.report_header.designPath` correctly
   against the `documentData` array shape from `buildDocumentData()` (§1.3) — use `designPath` (a storage
   disk-relative path), not `designUrl` or `designData`, since `resolveSignaturePath()` (reused per §2.2)
   expects a disk-relative path the same way signature fields do, not a URL or data URI.
4. **Recalibrate every remaining entry**, not just the ones that moved — the applicant data table
   (`applicant.full_name` through `applicant.employer_or_business`, `applicant.office_address`) sits above
   the changed paragraph/notarial regions and its own y-coordinates are unaffected by this phase's changes,
   but confirm this with the grid overlay rather than assuming — the Legal page size change alone doesn't
   move earlier content, but it's a full-page rebuild and every coordinate should be re-verified against
   the new artwork, not just the ones this plan explicitly lists as moved.
5. **`LoanRequestDocumentCatalog.php`:** bump `'affidavit-undertaking-v2'` → `'affidavit-undertaking-v3'`
   (§1.5) so existing generated AU documents are flagged stale and regenerate with the corrected layout.
6. **Verification (required):**
   - `php artisan loan-documents:calibrate-fields au` after every coordinate pass — confirm each field box
     visually lands inside its intended blank/cell on the new artwork.
   - Generate an actual AU PDF with throwaway test data (reuse
     `approvedLoanDocumentsCreateApprovedLoanRequestWithPeople()` /
     `approvedLoanDocumentsPersistDataEntry()` helpers from
     `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php` in a scratch script or `tinker` session,
     matching the pattern the prior plan's Phase 2 used), including a test org-settings header image, and
     visually compare against the reference render.
7. **Rollback:** revert the field-map + catalog commit; Phase 1's infrastructure and Phase 2's artwork are
   unaffected since they're separate commits.
8. **Commit:** field map + catalog version bump together (they're the same logical change — new layout,
   new version).

---

## 5. Phase 4 — Test coverage

**Goal:** every changed coordinate, the new content, and the new `image` field type have Pest coverage.
Per project Hard Rules, every change needs a test — this phase is not optional polish.

1. **`tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`:**
   - Update `affidavit undertaking field map pins all field coordinates to calibrated values` (or
     equivalent — locate the current pinning test, it may have been renamed since the prior plan's Phase 2
     landed) to the new coordinates from §4, including the deleted `notarial.valid_id_issued_at` entry and
     the new `image`-type header entry.
   - Update or add content assertions confirming the rewritten paragraph-1 sentence text renders correctly
     (extracted-text assertion, mirroring the existing `->toContain(...)` pattern used for
     `guaranteed net take-home pay` and `payout bank details` tests already in this file).
   - Add an assertion that the generated AU PDF is Legal-size (215.9×330.2mm), not A4 — extract page
     dimensions from the rendered output the same way `getTemplateSize()` reads them internally, or via
     whatever PDF-inspection library this test file already depends on.
   - Add a header-image assertion: with a test org-settings header configured, the generated AU PDF
     contains image data at the reserved header position (mirror whatever assertion style Phase 1's new
     unit test used for the generic `image` type, applied here end-to-end through the real AU field map).
   - Add a graceful-fallback assertion: with **no** org-settings header configured, AU generation does not
     error and simply omits the header image (§1.3/§2.5's no-op guard) — confirms the edge case noted in
     the investigation doesn't crash in practice even though it's not expected to occur with MRDINC's live
     data.
2. **`tests/Unit/LoanRequestDocumentCatalogAffidavitNotarialFieldsTest.php`:** confirm this file's existing
   assertions don't hardcode the old `'affidavit-undertaking-v2'` string — update to `-v3` if they do.
3. **Any test asserting the old horizontal Doc/Page/Book/Series layout or the old separate
   Province/Valid-ID/ID-issued-at lines** — search the full changed-files list in git status
   (`tests/Feature/LoanRequestProcessingDetailsTest.php`, `tests/Feature/LoanWorkflowAcceptanceTest.php`)
   for any assertions that would break under the new layout and update them.
4. Full suite: `vendor/bin/pest` (or `php artisan test`), plus `npm run build`/`tsc` if any TS surface
   touched `resources/js/pages/staff/loan-request-show.tsx` incidentally during this pass (not expected —
   this plan is PHP/artwork-only — but confirm before closing out).
5. `vendor/bin/pint --dirty`.
6. **Commit:** tests only, separate from Phase 3's field-map/catalog commit.

---

## 6. Out of scope (flag only — do not implement here)

- Using the new `image` field type for UB or Authorization's headers — Phase 1 builds it generically per
  the investigation's instruction, but wiring it elsewhere is a separate future task.
- Any change to the notarial sentence's dropped "due ___" blank — already decided as staying dropped
  (investigation confirmed), not reopened here.
- The header-title-beside-table layout deviation from the reference (§0) — accepted, not a bug, not
  touched.
- Renaming `resolveSignaturePath()` to something generic like `resolvePublicDiskPath()` even though Phase
  1 reuses it for non-signature images — cosmetic, not required for correctness, leave for a future
  cleanup pass if anyone cares.

---

## 7. Implementation checklist (ordered)

1. Phase 1: `image` field type in `ApprovedLoanPdfTemplateService`, infrastructure only, own tests.
   **Commit.**
2. Phase 2: rebuild `affidavit-undertaking.pdf` at Legal size/10pt with the paragraph-1 rewrite, notarial
   cleanup, vertical Doc/Page/Book/Series, reserved header space. Visual side-by-side verification.
   **Commit.**
3. Phase 3: recalibrate the full field map against the new artwork, wire the header via the new `image`
   type, bump `template_version` to `-v3`. Visual + generated-PDF verification. **Commit.**
4. Phase 4: update/add Pest coverage for coordinates, content, page size, and the new field type. Full
   suite + `pint --dirty`. **Commit.**

> Per project Hard Rules: `AppUser` n/a (no user-model touch), enums n/a (no new enum), validation n/a (no
> new Form Request fields — this plan touches presentation only, no new staff-entered data), every change
> needs a Pest test (§5), `vendor/bin/pint --dirty` before finishing each phase that touches PHP.
