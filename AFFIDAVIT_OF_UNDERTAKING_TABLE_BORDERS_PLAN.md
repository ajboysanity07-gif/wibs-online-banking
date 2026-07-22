# Plan — Affidavit of Undertaking (AU) Affiant-Info Table Borders Pass

> **Status:** Plan only. No implementation code has been written.
> **Relationship to prior plans:** `AFFIDAVIT_OF_UNDERTAKING_REBUILD_PLAN.md` did the from-scratch artwork
> build and data wiring (commits `168b5d5` … `5fd9a23`). `AFFIDAVIT_OF_UNDERTAKING_VISUAL_FIDELITY_PLAN.md`
> corrected page size, paragraph 1, the notarial block, and the header (commits `22641df`, `990168e`,
> `69c5891`, `8f967b2` — all landed, current HEAD). This plan is a **second follow-up correction pass**: a
> closer inspection of the affiant-info section (Name/Age/Marital Status/Nationality/Address/Designation/
> Agency/Agency Address) found it has no real table-cell geometry at all, unlike the reference document.
> Nothing here reopens the prior plans' decisions — paragraph 1, the notarial block, the header, and the
> Legal page size all stay exactly as landed.
> **Implementer:** every file path, line number, and coordinate below was confirmed by direct file
> inspection during plan-writing (this session). Phase 1 of this plan is itself a *measurement* phase —
> several numbers below (final row heights, whether paragraph 1 still fits) are explicitly **not yet
> known** and are the first thing Phase 1 must resolve before Phase 2 commits to anything.

---

## 0. What's wrong, in one paragraph

The affiant-info section of the current base artwork
(`storage/app/templates/approved-loan-documents/pdf/affidavit-undertaking.pdf`) has **zero bordered-cell
geometry**, confirmed via raw content-stream inspection (FPDI's `PdfParser`/`PdfReader`): every row is
independent, disconnected line-stroke segments — e.g. the 3-column Age/Marital Status/Nationality row is 3
unconnected lines with gaps between them, not a shared cell wall — plus separately-positioned text, not
real table cells. The reference document (`AFFIDAVIT OF UNDERTAKING (1).docx`) uses genuine bordered table
cells with consistent row heights and text sitting inside cell padding. Fixing this is a border-only
*intent* but not a border-only *change*: it requires rebuilding the artwork section with real bordered
cells (TCPDF `Cell()`/`MultiCell()`, `border=1`), which changes row geometry, which forces recalibrating
every field-map coordinate that targets this section, which may or may not fit in the same vertical space
the current borderless version uses.

---

## 1. Confirmed current state (read directly this session)

### 1.1 Affiant-info section — current field-map entries (8 of them)

`app/Services/LoanRequests/PdfFieldMaps/AffidavitUndertakingPdfFieldMap.php` (154 lines total today):

| value | x | y | size | mode | notes |
|---|---|---|---|---|---|
| `applicant.full_name` | 44.79 | 47.7 | 10 | `Text()` (no `width`) | single line |
| `applicant.age` | 25.78 | 55.7 | 9 | `Text()` | 3-column row, col 1 |
| `applicant.civil_status` | 83.07 | 55.7 | 9 | `Text()` | 3-column row, col 2 |
| `applicant.nationality` | 140.95 | 55.7 | 9 | `Text()` | 3-column row, col 3 |
| `applicant.address` | 18 | 66 | 8 | `MultiCell`, width 174, line_height 4 | multi-line |
| `applicant.position_or_designation` | 54.01 | 76.7 | 9 | `Text()` | single line |
| `applicant.employer_or_business` | 32.25 | 84.7 | 9 | `Text()` | single line |
| `applicant.office_address` | 18 | 95 | 8 | `MultiCell`, width 174, line_height 4 | multi-line |

Immediately after this section, `loan.gnthp` (paragraph 1's first inline blank) sits at `y=127` — this is
the current section's effective floor; whatever this plan does to the 8 rows above must not push content
below it without also shifting paragraph 1 and everything after it, which is explicitly out of scope
unless Phase 1 proves it's unavoidable (see §3).

Every one of these 8 coordinates targets **where text currently sits relative to a borderless underline**,
not where text would sit inside a real cell's padding. **None of the 8 can be reused as-is** once real
borders and consistent row heights exist — confirmed by the shape of the problem, not an assumption: adding
a cell wall changes the baseline offset from the top of the row, and consistent row heights (as opposed to
each row being hand-spaced independently, which is what the y-deltas above actually are: 8, 10.3, 10.7,
8, 8, 10.3) will themselves shift most rows' y position even before padding is considered.

### 1.2 How text is currently drawn — confirmed no existing bordered-table precedent

`ApprovedLoanPdfTemplateService::writeText()` (`app/Services/LoanRequests/ApprovedLoanPdfTemplateService.php:282-327`):
the `MultiCell()` call (304-321) hardcodes `border=0` at the 4th positional argument (line 308).
`ApprovedLoanImageTemplatePdfService::writeText()` (`app/Services/LoanRequests/ApprovedLoanImageTemplatePdfService.php:325-355+`)
does the same — its `MultiCell()` call (342) also hardcodes `border=0` (346). **Both of this codebase's
two `MultiCell()` call sites explicitly disable borders.** There is no existing pattern anywhere in this
codebase for a bordered TCPDF table. This plan establishes a new one — §4 below decides how.

### 1.3 Page geometry (confirmed, unaffected by this plan)

Current artwork is PH Legal, 215.9mm × 330.2mm portrait, body font 10pt Helvetica (landed in
`22641df`/`990168e`/`69c5891`, per the visual fidelity plan). This plan does **not** touch page size or
the base font size — only the affiant-info section's internal geometry.

### 1.4 Existing verification tooling (use, don't invent)

`php artisan loan-documents:calibrate-fields au`
(`app/Console/Commands/CalibrateApprovedLoanPdfFieldsCommand.php`) overlays a labeled 10mm grid and colored
field-bounding-boxes on the live template, writing to `storage/app/tmp/calibrate-au.pdf`. Use at every
coordinate pass, same as both prior AU plans.

### 1.5 No committed artwork-builder script (precedent, confirmed again this session)

`git show --stat 22641df` (the commit that rebuilt this artwork at Legal/10pt) touched only the binary PDF
— no `.php` generation script was committed. Per that commit's own message: *"built via a throwaway TCPDF
script (not committed, per this doc type's existing precedent — no generation script has ever been checked
in for this file)."* This plan's Phase 2 follows the same precedent: throwaway script in the scratchpad,
never `git add`ed, only its PDF output committed.

### 1.6 Test coverage currently pinning these 8 coordinates

`tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`, test `'affidavit undertaking field map pins
all field coordinates to calibrated values'` (starts line 901) — the 8 assertions for this section are at
lines 930-965 (`$fullName`, `$age`, `$civilStatus`, `$nationality`, `$address`, `$designation`, `$agency`,
`$officeAddress`). All 8 must be updated to the new coordinates in Phase 4.

### 1.7 Catalog version (confirmed current value)

`app/Services/LoanRequests/LoanRequestDocumentCatalog.php:104`: `'template_version' =>
'affidavit-undertaking-v3'` (bumped from `-v2` in the visual fidelity pass). This plan bumps it again to
`-v4` in Phase 3, same staleness-flagging rationale as before: any AU document already generated under the
borderless layout should regenerate under the bordered one.

---

## 2. Real risk, stated up front — this is not a border-only tweak

Measured row spacing in the current artwork (§1.1's y-deltas: 8mm, 10.3mm, 10.7mm, 8mm, 8mm, 10.3mm) shows
rows are **already tighter than the field-map-declared 4mm `line_height`** for the two MultiCell rows —
address/office-address rows pack roughly 3.3mm per wrapped line in practice, not the nominal 4mm the field
map declares (the 4mm is TCPDF's line-height parameter for wrapping, not a guarantee about how tightly
lines actually render). Standard TCPDF cell padding (`cMargin`, default ~1mm each side) plus a real top+
bottom border stroke per row will very likely need **more** vertical room per row, not less.

That matters because paragraph 1, sitting immediately below this section (`loan.gnthp` at `y=127`), was
already tightly fit to the Legal-size page in the visual fidelity pass — its wrap point was measured
character-by-character with `GetStringWidth()` at 10pt against the actual content width (per
`AFFIDAVIT_OF_UNDERTAKING_VISUAL_FIDELITY_PLAN.md` §3.3). Growth in the affiant-info table risks pushing
paragraph 1, the notarial block, and the Doc/Page/Book/Series stack further down an already-fully-used
Legal page. **This must be measured against the real page budget in Phase 1, not assumed to fit** — and if
it doesn't fit, the remedy (reduced cell padding, tighter row heights, or in the worst case reflowing
paragraph 1's wrap point again) is scoped as part of *this* plan, not deferred as a surprise follow-up,
since it cascades directly from this change.

---

## 3. Phase 1 — Prototype the bordered geometry in isolation, decide whether it fits

**Goal:** answer, concretely, "does a real bordered 8-row affiant-info table fit in the same vertical
footprint the borderless version uses today (y≈40 to y≈118, the space before paragraph 1's `loan.gnthp` at
y=127)?" This is a measurement/decision phase. **Nothing in Phase 2 is final until this is answered.**

1. Write a throwaway TCPDF script (scratchpad, not committed — same precedent as §1.5) that renders just
   the affiant-info section in isolation, matching the reference `.docx`'s table structure:
   - Row 1: Name of Affiant (full width)
   - Row 2: Age / Marital Status / Nationality (3 columns)
   - Row 3: Complete Residential Address (full width, will wrap — measure worst-case line count using a
     realistic long address, not a short test string)
   - Row 4: Designation (full width)
   - Row 5: Name of Agency (full width)
   - Row 6: Complete Address of Agency (full width, wraps — same worst-case-length caution as row 3)
   - Use `Cell()`/`MultiCell()` with `border=1` throughout (or `1` per side if a specific edge should be
     omitted — decide based on how the reference renders shared cell walls between adjacent rows; TCPDF
     draws overlapping borders idempotently so `border=1` on every cell is the simpler default unless it
     visibly double-strokes).
   - Use TCPDF's default `cMargin` first; only reduce it if Phase 1's fit check (step 2) fails with the
     default.
2. Measure the resulting total section height (top of row 1 to bottom of row 6) at 10pt Helvetica, Legal
   page content width (174mm, matching the existing field map's `width` values). Compare against the
   current section's footprint: `127 - 40 = 87mm` available (from just above `applicant.full_name`'s
   y=47.7 baseline down to `loan.gnthp`'s y=127, with a few mm of margin before the paragraph 1 text
   itself starts printing — confirm the actual paragraph-1 start y from the artwork, don't assume it's
   exactly at 127).
3. **Decision point — do not proceed to Phase 2 until this is resolved:**
   - **If it fits** within the current footprint (or fits with headroom under standard padding): proceed
     to Phase 2 with the measured geometry as-is, paragraph 1 untouched.
   - **If it doesn't fit:** try, in order of preference (least disruptive first):
     a. Reduce `cMargin` (cell padding) below TCPDF's default — cheapest fix, no downstream cascade.
     b. Reduce row heights for the single-line rows (1, 2, 4, 5) below the wrapped rows' natural line
        height, since single-line rows don't need as much vertical room as MultiCell-wrapped rows do.
     c. **Last resort:** reflow paragraph 1's wrap point again, same `GetStringWidth()`-based technique the
        visual fidelity plan used, to free enough space below. If this is needed, it must be executed as
        part of Phase 2 of *this* plan (not deferred) since it's a direct consequence of the table's new
        footprint — document the new wrap point the same way the visual fidelity plan documented its
        original one.
4. Document the final decision (fits as-is / fits with reduced padding / required paragraph 1 reflow) and
   the measured numbers (final row heights, final section height, final `cMargin` if changed) in this
   phase's commit message — Phase 2 and Phase 3 depend on these being pinned down, not re-derived.
5. **No files change in this phase** — it's a measurement-only throwaway script, no commit to the repo
   beyond a documentation note (see step 4) if this plan is tracked via commit messages rather than a
   separate note; if the team wants the measurement itself preserved, note the final numbers in the Phase 2
   commit message instead of a standalone Phase 1 commit, since there's no artifact to commit yet.
6. **Rollback:** N/A — no repo files change in this phase.

---

## 4. Open decision: reusable helper vs. ad hoc — decide before Phase 2

No existing precedent for bordered TCPDF tables exists anywhere in this codebase (§1.2 — both `MultiCell()`
call sites hardcode `border=0`). Two options:

- **Option A — build it ad hoc, inline in the Phase 1/2 throwaway builder script only.** Simplest, zero
  new production code, matches this plan's stated scope ("presentation-only, same category as the visual
  fidelity plan"). Downside: if another approved-loan document later needs a bordered table (none currently
  do — UB, Authorization, GREPALIFE are all borderless per §1.2), the pattern isn't captured anywhere
  reusable.
- **Option B — add a small reusable helper** (e.g. `writeBorderedRow()`/`writeBorderedTable()` alongside
  the existing `writeText()`/`writeCheck()`/`writeSignature()` methods in `ApprovedLoanPdfTemplateService`)
  that any current or future field-map-driven document could call. Only worth it if bordered tables are
  actually expected to recur.

**Recommendation: Option A.** The base artwork PDF itself is static (baked once via a throwaway script, not
regenerated per-request) — the bordered cells only need to exist in the *artwork*, not in the runtime
render path (`ApprovedLoanPdfTemplateService` only stamps *text* into the pre-existing bordered boxes at
request time via the existing borderless `writeText()`; it doesn't need to draw borders at all). A reusable
runtime helper would be solving a problem this plan doesn't have — no other current document has stated a
need for bordered tables, and per project conventions (no speculative abstractions ahead of a second real
use case) building one now would be premature. If a second bordered-table document need materializes later,
extract the pattern from this script into a shared helper at that point, not before. **State this decision
explicctly in the Phase 2 commit message** so it isn't silently relitigated later — don't pick Option B
without flagging why Option A was rejected.

---

## 5. Phase 2 — Rebuild the affiant-info section with bordered cells

**Goal:** replace only the affiant-info section (y≈40-118) of the current artwork with the bordered-cell
geometry confirmed in Phase 1. Paragraph 1 onward carries over unchanged from the current artwork **unless
Phase 1 determined a reflow is required** (§3 step 3c) — if so, that reflow is executed here too, in the
same commit, since it's one cascading change.

1. Extend the Phase 1 throwaway script (still scratchpad-only, not committed — §1.5 precedent) to open the
   *current* `affidavit-undertaking.pdf` via FPDI, import page 1 as a template, then draw the new bordered
   affiant-info section on top / in place of the old borderless one, leaving everything below `y≈118`
   (paragraph 1, the notarial block, Doc/Page/Book/Series) exactly as the current artwork already has it —
   **do not regenerate the whole page from scratch**; only this one section needs new content-stream
   geometry, minimizing risk of an unrelated regression elsewhere on the page.
   - If the old borderless line-strokes for this section are baked into the imported template's content
     stream (confirmed in the original investigation — the underline segments), they must be visually
     covered/replaced, not just overdrawn with new borders on top of old underlines. Confirm during Phase 1
     prototyping whether TCPDF/FPDI allows selectively suppressing part of an imported page's content
     stream, or whether the simplest approach is a white-filled rectangle over the old section before
     drawing the new bordered table on top (same "don't fight the old artwork, cover and redraw" approach
     is implicitly how `990168e`'s "remove debug placeholder" fix worked — check that commit's diff for the
     technique it used before choosing a new one here).
2. Use the row structure, geometry, `cMargin`, and (if needed) paragraph-1 wrap point exactly as decided in
   Phase 1 — no new measurement decisions in this phase, only execution of Phase 1's decision.
3. **Verification (required, not "file exists"):**
   - `php artisan loan-documents:calibrate-fields au` — confirms the new artwork loads under FPDI (correct
     page size still Legal, no corruption) and produces a fresh grid overlay baseline for Phase 3.
   - Render the new artwork standalone and do a **real rendered screenshot comparison** against the
     reference document — this document's history (garbled overlaps, missing spacing, a debug placeholder
     bleeding through in `990168e`) shows text-only/coordinate-only checks are not sufficient. Confirm: all
     6 rows have visible, connected cell borders (not disconnected segments); consistent row heights;
     paragraph 1 still reads correctly with its existing (or Phase-1-reflowed) wrap point; nothing below the
     table shifted unless Phase 1 determined it must.
4. **Rollback:** prior artwork restorable via
   `git checkout <commit-before-this-one> -- storage/app/templates/approved-loan-documents/pdf/affidavit-undertaking.pdf`.
   State the exact prior commit hash (`69c5891` or whatever the actual parent is at execution time — confirm
   with `git log -1` before writing the commit message, don't hardcode from this plan) in the Phase 2 commit
   message.
5. **Commit:** artwork binary only, no PHP/TS changes. Commit message must state: the Phase 1 measurement
   outcome (fits as-is / padding reduced / paragraph 1 reflowed, with the final numbers), and the Option A
   reusable-helper-vs-ad-hoc decision from §4.

---

## 6. Phase 3 — Recalibrate the 8 field-map coordinates

**Goal:** `AffidavitUndertakingPdfFieldMap.php`'s 8 affiant-info entries (§1.1) match Phase 2's new bordered
cells exactly — text sits inside each cell's padding, not floating at the old underline position.

1. For each of the 8 entries, remeasure x/y against the actual Phase 2 artwork using the calibration grid —
   do not reuse any of the 8 old values (§1.1 already established none of them can be reused as-is).
   - The 3-column row (`applicant.age`/`civil_status`/`nationality`) needs its 3 x-positions remeasured
     against the new column boundaries, not just the y.
   - The 2 MultiCell rows (`applicant.address`, `applicant.office_address`) need `width` reconfirmed against
     the new cell's interior width (cell width minus left+right `cMargin`, not the old flush-174mm value,
     since a real bordered cell has less usable interior width than a borderless line did) and `line_height`
     reconfirmed against the new row height if Phase 1 changed it.
2. Confirm `loan.gnthp` (y=127, first paragraph-1 field, unaffected by this section unless Phase 1's reflow
   touched it) and everything below still lands correctly — if Phase 1 required no reflow, this should need
   no change at all; confirm with the calibration grid rather than assuming.
3. **Verification (required):**
   - `php artisan loan-documents:calibrate-fields au` after every coordinate pass — confirm each of the 8
     boxes visually lands inside its intended cell's padding on the new artwork, not overlapping a border.
   - Generate an actual AU PDF with throwaway test data (reuse
     `approvedLoanDocumentsCreateApprovedLoanRequestWithPeople()` /
     `approvedLoanDocumentsPersistDataEntry()` helpers from
     `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`, matching both prior AU plans' pattern),
     and do a real rendered screenshot comparison against the reference — not just the calibration grid tool
     alone, since the grid tool shows box placement but not whether real (possibly longer) data still reads
     cleanly inside each bordered cell.
4. `app/Services/LoanRequests/LoanRequestDocumentCatalog.php:104`: bump `'affidavit-undertaking-v3'` →
   `'affidavit-undertaking-v4'` so any already-generated AU document is flagged stale and regenerates with
   the bordered layout.
5. **Rollback:** revert the field-map + catalog commit; Phase 1 (no files) and Phase 2 (artwork, separate
   commit) are unaffected.
6. **Commit:** field map + catalog version bump together (same logical change — new cell geometry, new
   version).

---

## 7. Phase 4 — Test coverage

**Goal:** the 8 recalibrated coordinates have Pest coverage; nothing else in the AU test file regressed.
Per project Hard Rules, every change needs a test.

1. `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php`, test `'affidavit undertaking field map pins
   all field coordinates to calibrated values'` (line 901, assertions at 930-965 per §1.6): update all 8
   `$fullName`/`$age`/`$civilStatus`/`$nationality`/`$address`/`$designation`/`$agency`/`$officeAddress`
   assertions to the new x/y/width/size values from Phase 3. Leave every other assertion in this test
   (header, gnthp, payout fields, date, notarial fields — lines 966 onward) untouched unless Phase 1's
   reflow decision (§3 step 3c) actually shifted them, in which case update only what moved and state why
   in the commit message.
2. Run the full existing `tests/Feature/ApprovedLoanDocumentPackageDownloadTest.php` file — confirm no other
   test in it (content assertions for age/civil status/nationality/designation/agency text, page-size
   assertion, header-image assertion) broke as a side effect of the new geometry.
3. Full Pest suite: `vendor/bin/pest` (or `php artisan test`) — confirm nothing in
   `tests/Feature/LoanRequestProcessingDetailsTest.php` or `tests/Feature/LoanWorkflowAcceptanceTest.php`
   (both currently touched on this branch per git status) has a stale AU-layout assertion.
4. `vendor/bin/pint --dirty`.
5. `tsc`/`npm run build` only if Phase 1-3 touched anything under `resources/js/` — not expected (this plan
   is PHP/artwork-only), but confirm before closing out.
6. **Commit:** tests only, separate from Phase 3's field-map/catalog commit.

---

## 8. Out of scope (flag only — do not implement here)

- Paragraph 1's content/wording, the notarial block, the header image, and the Legal page size — all landed
  in the visual fidelity pass, not reopened here. Paragraph 1's **wrap point** may be touched only if Phase
  1 proves it's forced by the table's growth (§3 step 3c), and even then only the wrap point, not the
  sentence content.
- Extracting a reusable bordered-table helper (§4 Option B) — explicitly rejected for this plan; revisit
  only if a second document later needs the same pattern.
- Any change to `applicant.*` data sourcing, validation, or `buildDocumentData()` wiring — this plan is
  presentation-only, no new staff-entered data, no new EAV keys.
- Bordering any other section of the AU artwork (paragraph blocks, the notarial acknowledgment, the
  Doc/Page/Book/Series stack) — only the affiant-info table is in scope.

---

## 9. Implementation checklist (ordered)

1. Phase 1: prototype bordered geometry in isolation, measure against the current 87mm footprint, decide
   fit / padding reduction / paragraph-1 reflow. **No commit — decision documented in Phase 2's message.**
2. Phase 2: rebuild the affiant-info section of `affidavit-undertaking.pdf` with the confirmed-fitting
   bordered cells (+ paragraph 1 reflow if Phase 1 required it). Real screenshot verification against the
   reference. **Commit.**
3. Phase 3: recalibrate all 8 field-map coordinates against the new cell geometry, wire header/gnthp/etc.
   unaffected-unless-proven-otherwise, bump `template_version` to `-v4`. Grid + real-PDF screenshot
   verification. **Commit.**
4. Phase 4: update the 8 pinned coordinates in the existing test, run the full AU test file + full Pest
   suite + `pint --dirty`. **Commit.**

> Per project Hard Rules: `AppUser` n/a (no user-model touch), enums n/a (no new enum), validation n/a (no
> new Form Request fields — presentation only), every change needs a Pest test (§7), `vendor/bin/pint
> --dirty` before finishing each phase that touches PHP.
