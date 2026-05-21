<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class UndertakingBarangayPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'x' => 27,
                'y' => 42,
                'size' => 10,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 50,
                'size' => 8,
                'width' => 160,
                'line_height' => 4,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 62,
                'size' => 9,
                'value' => 'loan.type',
            ],
            [
                'page' => 1,
                'x' => 107,
                'y' => 62,
                'size' => 9,
                'value' => 'loan.approved_amount',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 72,
                'size' => 9,
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 104,
                'y' => 72,
                'size' => 9,
                'value' => 'organization.company_name',
            ],
            [
                'type' => 'signature',
                'page' => 1,
                'x' => 31,
                'y' => 225,
                'width' => 44,
                'height' => 16,
                'value' => 'applicant.signature_path',
            ],
        ];
    }
}
