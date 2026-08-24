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
                'y' => 75,
                'size' => 11,
                'width' => 83,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_provider',
            ],
            [
                'page' => 1,
                'x' => 44,
                'y' => 83,
                'size' => 11,
                'width' => 68,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_bank_name',
            ],
            [
                'page' => 1,
                'x' => 152,
                'y' => 83,
                'size' => 11,
                'width' => 38,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_atm_card_number',
            ],
            // "to deduct- <amount in words> (<amount>) from my PENSION" -- both blanks
            // sit inline on clause 2's second line in the typeset template.
            [
                'page' => 1,
                'x' => 47,
                'y' => 103,
                'size' => 11,
                'width' => 83,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_deduction_amount_words',
            ],
            [
                'page' => 1,
                'x' => 137,
                'y' => 103,
                'size' => 11,
                'width' => 33,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.pension_deduction_amount',
            ],
            [
                'page' => 1,
                'x' => 119,
                'y' => 125,
                // Place of Signing blank on the "Signed this ___ day of ______ at ___"
                // row. The day/month-year blanks beside it stay hand-fill -- only the
                // venue is system-printed (full composed org address, shrink-to-fit).
                'size' => 11,
                'width' => 71,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'notarial.signing_place',
            ],
            [
                'page' => 1,
                'x' => 60,
                'y' => 171,
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
