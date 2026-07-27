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
