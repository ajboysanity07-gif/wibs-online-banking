<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

use App\Services\LoanRequests\PdfFieldMaps\Concerns\UppercasesFieldValues;

class PensionDeductionWaiverPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    use UppercasesFieldValues;

    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'type' => 'image',
                'x' => 18,
                'y' => 15,
                'width' => 174,
                'height' => 18,
                'scale' => 1.5,
                'value' => 'organization.report_header.designPath',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 55,
                'size' => 11,
                'width' => 68,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 118,
                'y' => 55,
                'size' => 11,
                'width' => 72,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 97,
                'y' => 74,
                'size' => 11,
                'width' => 83,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_provider',
            ],
            [
                'page' => 1,
                'x' => 44,
                'y' => 82,
                'size' => 11,
                'width' => 68,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_bank_name',
            ],
            [
                'page' => 1,
                'x' => 152,
                'y' => 82,
                'size' => 11,
                'width' => 38,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_atm_card_number',
            ],
            // "to deduct <amount in words> from my PENSION" -- a single blank, no
            // parentheses and no separate numeral field: confirmed against the real
            // MRDINC form's raw docx XML (one continuous underlined run between
            // "to deduct" and "from my PENSION").
            [
                'page' => 1,
                'x' => 45,
                'y' => 100,
                'size' => 11,
                'width' => 100,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_deduction_amount_words',
            ],
            [
                'page' => 1,
                'x' => 139,
                'y' => 112,
                // Place of Signing blank on the "Done this ___ day of ______ at ___"
                // row. The day/month-year blanks beside it stay hand-fill -- only the
                // venue is system-printed (full composed org address, shrink-to-fit).
                'size' => 11,
                'width' => 51,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'notarial.signing_place',
            ],
            [
                'page' => 1,
                'x' => 60,
                'y' => 140,
                'size' => 11,
                'style' => 'B',
                'width' => 90,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
                'transform' => $this->upperTransform(),
            ],
        ];
    }
}
