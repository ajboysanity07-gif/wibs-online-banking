<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class AuthorizationPdfFieldMap implements ApprovedLoanPdfFieldMap
{
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
                'x' => 26,
                'y' => 38,
                'size' => 10,
                'style' => 'B',
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 26,
                'y' => 46,
                'size' => 8,
                'width' => 162,
                'line_height' => 4,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 26,
                'y' => 58,
                'size' => 9,
                'value' => 'loan.reference',
            ],
            // "...credit the loan security of my loan in the amount of ___"
            // -- prints the loan_security_amount blank, not the raw approved
            // loan amount.
            [
                'page' => 1,
                'x' => 88,
                'y' => 58,
                'size' => 9,
                'value' => 'loan.loan_security_amount',
            ],
            [
                'page' => 1,
                'x' => 138,
                'y' => 58,
                'size' => 9,
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 26,
                'y' => 68,
                'size' => 9,
                'value' => 'organization.company_name',
            ],
            // Bank name is a live field sourced from the member's own
            // selected bank -- never hardcoded static artwork text.
            [
                'page' => 1,
                'x' => 26,
                'y' => 102,
                'size' => 9,
                'value' => 'authorization.payout_bank_name',
            ],
            [
                'page' => 1,
                'x' => 26,
                'y' => 110,
                'size' => 9,
                'value' => 'authorization.payout_account_number',
            ],
            [
                'page' => 1,
                'x' => 26,
                'y' => 118,
                'size' => 9,
                'value' => 'authorization.payout_bank_branch',
            ],
            [
                'page' => 1,
                'x' => 26,
                'y' => 126,
                'size' => 9,
                'value' => 'authorization.payout_atm_holder_name',
            ],
            [
                'page' => 1,
                'x' => 30,
                'y' => 220,
                'size' => 10,
                'style' => 'B',
                'width' => 60,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 120,
                'y' => 220,
                'size' => 10,
                'style' => 'B',
                'width' => 60,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'authorization.payout_atm_holder_name',
            ],
        ];
    }
}
