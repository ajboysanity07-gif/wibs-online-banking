<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class GrepalifePdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'x' => 24,
                'y' => 34,
                'size' => 9,
                'value' => 'applicant.last_name',
            ],
            [
                'page' => 1,
                'x' => 76,
                'y' => 34,
                'size' => 9,
                'value' => 'applicant.first_name',
            ],
            [
                'page' => 1,
                'x' => 128,
                'y' => 34,
                'size' => 9,
                'value' => 'applicant.middle_name',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 41,
                'size' => 9,
                'value' => 'applicant.birthdate',
            ],
            [
                'page' => 1,
                'x' => 76,
                'y' => 41,
                'size' => 9,
                'value' => 'applicant.age',
            ],
            [
                'page' => 1,
                'x' => 106,
                'y' => 41,
                'size' => 9,
                'value' => 'applicant.civil_status',
            ],
            [
                'page' => 1,
                'x' => 150,
                'y' => 41,
                'size' => 9,
                'value' => 'applicant.nationality',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 48,
                'size' => 8,
                'width' => 165,
                'line_height' => 4,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 58,
                'size' => 9,
                'value' => 'applicant.mobile',
            ],
            [
                'page' => 1,
                'x' => 92,
                'y' => 58,
                'size' => 9,
                'value' => 'applicant.email',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 65,
                'size' => 9,
                'value' => 'loan.type',
            ],
            [
                'page' => 1,
                'x' => 92,
                'y' => 65,
                'size' => 9,
                'value' => 'loan.approved_amount',
            ],
            [
                'page' => 1,
                'x' => 142,
                'y' => 65,
                'size' => 9,
                'value' => 'loan.approved_term_label',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 72,
                'size' => 8,
                'width' => 165,
                'line_height' => 4,
                'value' => 'loan.purpose',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 84,
                'size' => 9,
                'value' => 'organization.company_name',
            ],
            [
                'page' => 1,
                'x' => 130,
                'y' => 84,
                'size' => 9,
                'value' => 'loan.approved_date',
            ],
            [
                'type' => 'signature',
                'page' => 1,
                'x' => 24,
                'y' => 230,
                'width' => 42,
                'height' => 16,
                'value' => 'applicant.signature_path',
            ],
        ];
    }
}
