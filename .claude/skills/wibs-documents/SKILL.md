---
name: wibs-documents
description: WIBS document generation conventions -- FPDI overlay pattern, coordinate calibration, font handling. Use whenever creating or modifying a loan document (PDF field maps, calibration).
---

# WIBS Document Generation Conventions

- Most documents use the FPDI-overlay pattern (stamp coordinates onto a real
  base PDF), not Blade/CSS -- only Application Form and Loan Security
  Agreement are Blade-authored.
- Use `php artisan loan-documents:calibrate-fields {doc}` to visually verify
  field placement before committing coordinate changes.
- Font convention: Calibri (embedded), not system fonts -- see
  OrganizationSettingsService::resolveCalibriFontFaceCss() for the pattern.
- Always verify with a REAL rendered PDF, not just Pest assertions --
  coordinate drift has caused repeated regressions in this codebase.

## Coordinate Convention

- **Units:** Millimeters (mm).
- **Origin:** Top-left corner of the page (0,0). This is TCPDF/FPDI's native
  space and also what PyMuPDF reports natively, so **no y-flip is needed.**
- **X-axis:** Left to right (increasing →)
- **Y-axis:** Top to bottom (increasing ↓)
- Field map arrays (`x`, `y`) use this top-down convention directly.

The older claim that maps use "y from bottom" is wrong and must not be
reintroduced -- it contradicts the calibration grid labels, the TCPDF renderer,
and PyMuPDF's raw coordinates.

## The Checkbox Glyph Offset (critical)

Checkmarks are stamped with TCPDF's core `zapfdingbats` font via
`Text($x, $y, '4')` (ApprovedLoanPdfTemplateService::writeCheck). The glyph is
NOT centered on the anchor point:

```
glyph_center = anchor + (dX, dY)     (y-down)
```

Measured offsets (empirical, constant for a given size):

| Font size | dX (mm) | dY (mm) |
|---|---|---|
| 7  | 1.98 | 1.14 |
| 9  | 2.34 | 1.95 |

The offset **scales with font size** (it's a fraction of the glyph cell).
Consequences:

- To center a check on a template box: `anchor = boxCenter − offset(size)`.
- **Changing the check font size breaks every existing check anchor.** This is
  the exact regression hit 2026-08-20 on the Generali Application Form: all
  checks were bumped 6/7→9 with size-7 anchors, shifting every glyph ~1mm off.
- When recalibrating, never reuse anchors calibrated at another size.

## Measurement Tools

**Use PyMuPDF (verified).** pdfplumber has given unreliable results on these
templates and is not recommended.

The template fonts (MS-Gothic/SegoeUISymbol for ☐, ArialNarrow for text) often
lack ToUnicode CMaps, so extracted `text` is empty. Match glyphs by **font
name** in `rawdict` char bboxes, and read coordinates top-down (no flip):

```python
import sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')  # Windows cp1252 crashes on '☐'
import fitz  # PyMuPDF

PT2MM = 25.4 / 72.0

def char_centers(page, font_substr):
    out = []
    for block in page.get_text('rawdict')['blocks']:
        for line in block.get('lines', []):
            for span in line['spans']:
                if font_substr not in span['font']:
                    continue
                for ch in span['chars']:
                    b = fitz.Rect(ch['bbox'])
                    out.append(((b.x0 + b.x1) / 2 * PT2MM, (b.y0 + b.y1) / 2 * PT2MM))
    return out

# Template box centers (checkbox glyphs):
#   ch['c'] in ('\u2610',) -- or '[' / ']' for bracket-style PEP boxes
# Rendered check centers (what the map actually produced):
#   char_centers(render_page, 'ZapfDingbats')  -- match by font, NOT text
```

`anchor = boxCenter − offset(current_size)` then produces a centered check.

### Dual-template pitfall

There are two different `generali.pdf` files:

- `storage/app/templates/approved-loan-documents/pdf/generali.pdf` — the
  runtime ge template (larger, ~731 KB). **Measure against this one.**
- `<repo-root>/generali.pdf` — a stale/different form (~236 KB, same size as
  `generali-application-partner-005.pdf`). Ignore it.

The ga template is `generali-application-form.pdf` in the same storage dir.

## PDF Field Calibration Process

### When Alignment Issues Occur

**Diagnosis hierarchy** (apply in order):

1. **Uniform misalignment** — all instances of a field type (e.g., all checkboxes) shift by the same amount
   - **Cause:** Shared X or Y coordinate constants are off
   - **Fix:** Adjust the constant (e.g., `HEALTH_Y_X`, `HEALTH_N_X` in GeneraliPdfFieldMap)
   - **Example:** 2026-07-31 Generali GHS checkbox fix — all Y/N checkboxes were 1.7mm left of their boxes; fixed by adjusting `HEALTH_Y_X` and `HEALTH_N_X` only

2. **Individual field misalignment** — specific fields are off while others using the same constants are correct
   - **Cause:** Per-field Y-coordinates are wrong
   - **Fix:** Measure and adjust individual field Y values
   - **Tool:** PyMuPDF character-center extraction (above)

3. **Checkboxes off after a font-size change** — all checks drift by the same delta
   - **Cause:** zapfdingbats `4` glyph center offset scales with font size
   - **Fix:** Re-derive every check anchor: `anchor = boxCenter − offset(new_size)`; re-measure the offset at the new size first (see The Checkbox Glyph Offset)

4. **Font/baseline drift** — text appears but sits above/below the intended baseline
   - **Cause:** Font metrics changed or baseline calculation is wrong
   - **Fix:** Check font embedding and adjust per-font baseline offsets
   - **Reference:** LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md documents systematic baseline offset fixes

### Calibration Command Usage

```bash
# Generate calibration PDF with grid overlay
php artisan loan-documents:calibrate-fields ge  # Generali Health Statement
php artisan loan-documents:calibrate-fields ga  # Generali Application Form
php artisan loan-documents:calibrate-fields au  # Affidavit of Undertaking
php artisan loan-documents:calibrate-fields li  # Loan Information
# etc. (see command help for full list)
```

The calibration PDF overlays:
- Red boxes showing where each field will print
- Millimeter grid for visual measurement
- Helps spot alignment issues before committing coordinate changes

### Render-Verify Loop (required)

1. Render a REAL PDF (the `render_*.php` scratch scripts at repo root, or the
   feature-test download route) with data that triggers every field you
   changed — for checkboxes, include data for both values of every pair
   (New/Old, Yes/No, MALE/FEMALE, etc.).
2. Extract the rendered `zapfdingbats '4'` glyph centers (match by font name)
   and the template box centers with PyMuPDF.
3. Confirm `check_center ≈ box_center` within ±0.1mm; iterate anchors until
   they match. Do not trust eyeballing alone.

### Testing After Calibration

1. **Run existing tests:** `php artisan test --filter=generali` (or relevant document)
2. **Pin the new coordinates:** add/extend the coordinate-pinning unit test
   (see `tests/Unit/GeneraliPdfFieldMapTest.php` pattern) asserting the
   recalibrated `x`/`y`/`size` on the changed fields.
3. **Generate real PDF:** Visit the document download route with an approved loan request
4. **Visual verification:** Open the PDF and verify all fields appear inside their boxes
5. **Commit only after visual confirmation** — tests can pass while coordinates are still wrong

### Common Pitfalls

- **Don't adjust Y-coordinates when X-coordinates are the problem** — if ALL checkboxes in a column shift together, it's an X-axis issue
- **Don't reuse check anchors across font sizes** — the zapfdingbats offset scales with size
- **Don't flip y to bottom-origin** — the maps are top-down; `page_height − top` gives wrong coordinates
- **Don't match checkbox glyphs by text content** — template fonts often extract empty text; match by font name
- **Don't skip visual/render verification** — Pest tests verify data resolution, not coordinate accuracy
- **Don't forget shrink-to-fit settings** — long text can overflow if `shrink_to_fit` and `min_size` aren't configured

### Reference Commits

- `a2cda2a` — Initial calibration command and pinning tests for AU/AZ/UB/GL
- `e3845ef` — Field coordinate calibration command implementation
- `69c5891` — AU recalibration for new artwork
- `16f7f51` — AU field map recalibration for bordered table
- `2026-07-31` — Generali GHS checkbox X-coordinate fix (HEALTH_Y_X/HEALTH_N_X +1.7mm)
- `50b9ab6` — Generali ge/ga checkbox calibration (anchors centered at size 6/7)
- `2026-08-20` — Generali Application Form size-9 check recalibration (offset (2.34, 1.95) at size 9; all 34 ga check anchors re-derived from template box centers)