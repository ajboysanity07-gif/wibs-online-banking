<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class LoanInformationPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    // Content box x-bounds measured from the artwork's /Artifact BBox divider rules (see
    // LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md §2). Visually calibrated in Phase 2 against
    // the real production artwork (rendered via ApprovedLoanPdfTemplateService and rasterized
    // for inspection, not just "no exceptions thrown"): all row y-coordinates below are the
    // artwork's own baked-in label y minus a per-font-size offset, because TCPDF's
    // Text()/MultiCell() vertically anchor $y at the TOP of the text box, while the artwork's
    // baked-in labels use raw PDF `Tm` baseline positioning -- using the same y for both
    // makes stamped values sit visibly lower than their labels. See §8 of the plan for the
    // measured before/after.
    private const CONTENT_LEFT = 16.35;

    private const CONTENT_RIGHT = 199.6;

    // Section 1 / 4 single-column label:value rows share this value-start x, leaving room
    // for the longest baked-in label ("Interest Rate (Per Annum)") at 9pt Cabin-Regular.
    private const LABEL_VALUE_X = 75.0;

    private const LABEL_VALUE_WIDTH = self::CONTENT_RIGHT - self::LABEL_VALUE_X - 2.0;

    // Section 2 / 3 table "Amount" column x, measured from the shaded header-row fill
    // boundary (x≈133.7-136mm) shared by both tables.
    private const AMOUNT_COLUMN_X = 138.0;

    private const AMOUNT_COLUMN_WIDTH = self::CONTENT_RIGHT - self::AMOUNT_COLUMN_X - 2.0;

    public function fields(): array
    {
        return [
            // Header logo box -- reuses AU's image field shape unmodified (generic
            // renderImageField() path, see ApprovedLoanPdfTemplateService::renderField()).
            // Visually confirmed in Phase 2 against the real MRDINC header design: this box
            // sizes and centers the logo cleanly above the y=38.75mm title with no overlap.
            [
                'page' => 1,
                'type' => 'image',
                'x' => 18,
                'y' => 10,
                'width' => 174,
                'height' => 24,
                'scale' => 1.0,
                'value' => 'organization.report_header.designPath',
            ],

            // ===== Section 1: Borrower & Loan Details =====
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 55.25,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 61.23,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 67.21,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'applicant.employer_or_business',
            ],
            [
                // Confirmed present (2026-07-19): reuses the same applicant.position_or_designation
                // path AffidavitUndertakingPdfFieldMap already stamps -- see
                // ApprovedLoanDocumentService::buildDocumentData() line ~1035.
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 73.19,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => 'applicant.position_or_designation',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 79.17,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => 'loan.type',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 85.15,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::money('loan.approved_amount_raw'),
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 91.12,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::percent('loan.interest_rate_raw'),
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 97.10,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::integer('loan.approved_term_raw'),
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 103.08,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => 'loan.payment_mode_workbook',
            ],
            [
                // "Loan Manager" -- confirmed wired to reviewer.name (not stale), unlike the
                // Disclosure Statement's situation at the time of commit 0d9d2a3. See
                // LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md §3.
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 109.06,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => 'reviewer.name',
            ],

            // ===== Section 2: Deductions / Computed Amounts table =====
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 132.42,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.service_charge_amount_raw'),
            ],
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 139.39,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.insurance_premium_raw'),
            ],
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 146.36,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.loan_security_amount_raw'),
            ],
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 153.33,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.documentary_stamp_amount_raw'),
            ],
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 160.29,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.notarial_fee_raw'),
            ],

            // ===== Section 3: Monthly Amortization table =====
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 183.77,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.amortization_principal_raw'),
            ],
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 190.74,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.amortization_interest_raw'),
            ],
            [
                // "Savings" row: resolved (2026-07-19) as an artwork wording choice, NOT a
                // relabel -- deliberately wired to the SAME figure the current Blade's third
                // amortization row already reads (loan.amortization_loan_security_raw), not
                // the newer independent loan.savings_rate_raw field added in commit
                // b3c7e0f. DO NOT "correct" this to savings_rate_raw just because the label
                // says "Savings" -- that field drives a different, unrelated figure. See
                // LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md §3.
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 197.70,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.amortization_loan_security_raw'),
            ],
            [
                'page' => 1,
                'x' => self::AMOUNT_COLUMN_X,
                'y' => 204.67,
                'size' => 8,
                'width' => self::AMOUNT_COLUMN_WIDTH,
                'align' => 'R',
                'value' => self::money('loan.amortization_total_raw'),
            ],

            // ===== Section 4: Promissory Note Details =====
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 221.29,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'co_maker_one.full_name',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 227.27,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'co_maker_one.address',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 233.25,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'co_maker_two.full_name',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 239.23,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'co_maker_two.address',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 245.21,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::integer('loan.approved_term_raw'),
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 251.19,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::money('loan.approved_amount_raw'),
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 257.17,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::percent('loan.interest_rate_raw'),
            ],
            [
                // "Payable Every" -- resolved (2026-07-19): the current Blade has no field by
                // this name at all (Section 4 is an artwork-only addition). Per the decision,
                // this intentionally restates Section 1's "Mode of Payment" -- same source,
                // same literal value, no separate formatting.
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 263.15,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => 'loan.payment_mode_workbook',
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 269.13,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::integer('loan.amortization_count'),
            ],
            [
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 275.11,
                'size' => 9,
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => self::percent('loan.penalty_rate_raw'),
            ],

            // ===== Section 5: Signatories =====
            [
                // Resolved (2026-07-19), explicit deliberate choice: hardcoded name to match
                // Disclosure Statement's commit 0d9d2a3 precedent exactly, even though
                // reviewer.name/reviewer.position ARE properly wired here (unlike DS at the
                // time) -- this is a cross-document consistency choice, not an oversight. Do
                // not "fix" this to use the live reviewer fields later.
                //
                // Role ("BOOKKEEPER") is appended inline on the same line, NOT stacked as a
                // separate line below the name like DS's two-line "name / Position" block --
                // visually confirmed (2026-07-20) that this artwork's Certified Correct row is
                // a single baked-in line (~6mm tall), unlike DS's dedicated two-line signature
                // block. A second line at the smallest usable size still collided with the
                // ruled divider above "Witnessed By". Inline "Name, ROLE" was confirmed to
                // render cleanly with no collision -- do not split this into two lines without
                // re-verifying visually.
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 291.78,
                'size' => 10,
                'style' => 'B',
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => static fn (): string => 'VELINA P. GAMUTAN, BOOKKEEPER',
            ],
            [
                // "Witnessed By" -- resolved (2026-07-19): single-source field only, the
                // assigned processor's auto-filled witness_one_name
                // (LoanRequestProcessingService::updateProcessingDetails(), matches
                // loan-information.blade.php's own $witness1 usage). Do not use
                // witness_two_name or concatenate both.
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 297.76,
                'size' => 10,
                'style' => 'B',
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => 'reviewer.witness_one_name',
            ],
            [
                // "Approved By" -- confirmed intentional reuse of reviewer.name, same value as
                // "Loan Manager" above, matching the current Blade's existing pattern of
                // reusing $certifierName for multiple signature slots.
                'page' => 1,
                'x' => self::LABEL_VALUE_X,
                'y' => 303.74,
                'size' => 10,
                'style' => 'B',
                'width' => self::LABEL_VALUE_WIDTH,
                'value' => 'reviewer.name',
            ],

            // The two blank Calibri (C2_0) text runs near y=313.11/316.49mm are intentionally
            // NOT wired here. Resolved in Phase 2: rendering the raw artwork (no field map
            // applied) and visually inspecting the Signatories section shows no ruled line,
            // caption, or blank space of any kind between "Approved By" and the footer
            // paragraph -- unlike AU/UB's notarization blocks, which always have a visible
            // "Doc No./Page No./Book No." caption. These are leftover empty text frames from
            // the design tool with no visual footprint, not a notarization slot. See
            // LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md §3/§8.
        ];
    }

    private static function money(string $path): callable
    {
        return static function (array $documentData) use ($path): ?string {
            $value = data_get($documentData, $path);

            if ($value === null || ! is_numeric((string) $value)) {
                return null;
            }

            return number_format((float) $value, 2, '.', ',');
        };
    }

    private static function percent(string $path): callable
    {
        return static function (array $documentData) use ($path): ?string {
            $value = data_get($documentData, $path);

            if ($value === null || ! is_numeric((string) $value)) {
                return null;
            }

            return number_format((float) $value * 100, 2, '.', '').'%';
        };
    }

    private static function integer(string $path): callable
    {
        return static function (array $documentData) use ($path): ?string {
            $value = data_get($documentData, $path);

            if ($value === null || ! is_numeric((string) $value)) {
                return null;
            }

            return (string) (int) round((float) $value);
        };
    }
}
