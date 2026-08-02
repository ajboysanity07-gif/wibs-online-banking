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
   - **Tool:** Use pdfplumber to extract actual Y-coordinates from the template

3. **Font/baseline drift** — text appears but sits above/below the intended baseline
   - **Cause:** Font metrics changed or baseline calculation is wrong
   - **Fix:** Check font embedding and adjust per-font baseline offsets
   - **Reference:** LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md documents systematic baseline offset fixes

### Measurement Tools

**Python-based coordinate extraction** (when visual calibration isn't enough):

```python
import pdfplumber

with pdfplumber.open('template.pdf') as pdf:
    page = pdf.pages[0]  # or [1] for page 2
    page_height = page.height * 0.352778  # convert points to mm
    
    # Extract text Y-coordinates
    for word in page.extract_words():
        y_mm = page_height - (word['top'] * 0.352778)
        print(f"{word['text']}: Y={y_mm:.1f}mm")
    
    # Extract checkbox characters (look for specific symbols)
    for char in page.chars:
        if char['text'] in ['☐', '□']:
            x_mm = char['x0'] * 0.352778
            y_mm = page_height - (char['top'] * 0.352778)
            print(f"Checkbox at X={x_mm:.1f}mm, Y={y_mm:.1f}mm")
```

**Note:** Measure character **centers**, not bounding box edges. For checkboxes, average `top` and `bottom` or `x0` and `x1` before converting to mm.

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

### Coordinate Convention

- **Units:** Millimeters (mm) from page edges
- **Origin:** Top-left corner of page (0,0)
- **X-axis:** Left to right (increasing →)
- **Y-axis:** Top to bottom (increasing ↓)
- **Conversion:** PDF points to mm: `mm = points × 0.352778`

### Testing After Calibration

1. **Run existing tests:** `php artisan test --filter=generali` (or relevant document)
2. **Generate real PDF:** Visit the document download route with an approved loan request
3. **Visual verification:** Open the PDF and verify all fields appear inside their boxes
4. **Commit only after visual confirmation** — tests can pass while coordinates are still wrong

### Common Pitfalls

- **Don't adjust Y-coordinates when X-coordinates are the problem** — if ALL checkboxes in a column shift together, it's an X-axis issue
- **Don't measure bounding boxes when you need centers** — checkbox alignment requires center points, not character bounds
- **Don't trust extracted checkbox Y-coordinates from pdfplumber** — the vertical position of checkbox characters in the PDF content stream may not match their visual center on the template
- **Don't skip visual verification** — Pest tests verify data resolution, not coordinate accuracy
- **Don't forget shrink-to-fit settings** — long text can overflow if `shrink_to_fit` and `min_size` aren't configured

### Reference Commits

- `a2cda2a` — Initial calibration command and pinning tests for AU/AZ/UB/GL
- `e3845ef` — Field coordinate calibration command implementation
- `69c5891` — AU recalibration for new artwork
- `16f7f51` — AU field map recalibration for bordered table
- `2026-07-31` — Generali GHS checkbox X-coordinate fix (HEALTH_Y_X/HEALTH_N_X +1.7mm)
