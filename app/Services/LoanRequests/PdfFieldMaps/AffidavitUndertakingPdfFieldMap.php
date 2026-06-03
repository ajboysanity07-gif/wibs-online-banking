<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class AffidavitUndertakingPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'x' => 28,
                'y' => 40,
                'size' => 10,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 28,
                'y' => 48,
                'size' => 8,
                'width' => 158,
                'line_height' => 4,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 28,
                'y' => 60,
                'size' => 9,
                'value' => 'loan.approved_amount',
            ],
            [
                'page' => 1,
                'x' => 94,
                'y' => 60,
                'size' => 9,
                'value' => 'loan.type',
            ],
            [
                'page' => 1,
                'x' => 28,
                'y' => 70,
                'size' => 9,
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 98,
                'y' => 70,
                'size' => 9,
                'value' => 'reviewer.name',
            ],
        ];
    }
}
