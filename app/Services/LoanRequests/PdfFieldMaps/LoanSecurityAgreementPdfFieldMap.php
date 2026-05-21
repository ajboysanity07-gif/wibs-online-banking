<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class LoanSecurityAgreementPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'x' => 25,
                'y' => 38,
                'size' => 10,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 25,
                'y' => 47,
                'size' => 8,
                'width' => 160,
                'line_height' => 4,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 25,
                'y' => 59,
                'size' => 9,
                'value' => 'loan.type',
            ],
            [
                'page' => 1,
                'x' => 108,
                'y' => 59,
                'size' => 9,
                'value' => 'loan.approved_amount',
            ],
            [
                'page' => 1,
                'x' => 160,
                'y' => 59,
                'size' => 9,
                'value' => 'loan.approved_term_label',
            ],
            [
                'page' => 1,
                'x' => 25,
                'y' => 69,
                'size' => 9,
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 98,
                'y' => 69,
                'size' => 9,
                'value' => 'reviewer.name',
            ],
            [
                'type' => 'signature',
                'page' => 1,
                'x' => 28,
                'y' => 214,
                'width' => 44,
                'height' => 16,
                'value' => 'applicant.signature_path',
            ],
            [
                'type' => 'signature',
                'page' => 1,
                'x' => 86,
                'y' => 214,
                'width' => 44,
                'height' => 16,
                'value' => 'co_maker_one.signature_path',
            ],
            [
                'type' => 'signature',
                'page' => 1,
                'x' => 144,
                'y' => 214,
                'width' => 44,
                'height' => 16,
                'value' => 'co_maker_two.signature_path',
            ],
        ];
    }
}
