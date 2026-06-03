<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class AuthorizationPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'x' => 26,
                'y' => 38,
                'size' => 10,
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
            [
                'page' => 1,
                'x' => 88,
                'y' => 58,
                'size' => 9,
                'value' => 'loan.approved_amount',
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
        ];
    }
}
