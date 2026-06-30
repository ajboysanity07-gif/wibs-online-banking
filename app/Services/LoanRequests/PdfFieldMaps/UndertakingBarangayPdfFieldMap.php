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
            // TODO(calibrate-ub): verify x/y against loan-documents:calibrate-fields ub overlay
            [
                'page' => 1,
                'x' => 27,
                'y' => 82,
                'size' => 9,
                'value' => 'barangay.name',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 90,
                'size' => 9,
                'value' => 'barangay.clearance_reference',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 98,
                'size' => 9,
                'value' => 'barangay.locality',
            ],
            // TODO(calibrate-ub): placeholders — verify against loan-documents:calibrate-fields ub overlay
            [
                'page' => 1,
                'x' => 27,
                'y' => 106,
                'size' => 9,
                'value' => 'barangay.official_designation',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 114,
                'size' => 9,
                'value' => 'barangay.agency_name',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 122,
                'size' => 8,
                'width' => 160,
                'line_height' => 4,
                'value' => 'barangay.agency_address',
            ],
            [
                'page' => 1,
                'x' => 107,
                'y' => 62,
                'size' => 9,
                'value' => 'loan.gnthp',
            ],
        ];
    }
}
