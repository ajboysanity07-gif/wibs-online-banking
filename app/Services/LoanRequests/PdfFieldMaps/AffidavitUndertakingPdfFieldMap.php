<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class AffidavitUndertakingPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'x' => 40.5,
                'y' => 47.7,
                'size' => 10,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 25.17,
                'y' => 55.7,
                'size' => 9,
                'value' => 'applicant.age',
            ],
            [
                'page' => 1,
                'x' => 80.16,
                'y' => 55.7,
                'size' => 9,
                'value' => 'applicant.civil_status',
            ],
            [
                'page' => 1,
                'x' => 137.83,
                'y' => 55.7,
                'size' => 9,
                'value' => 'applicant.nationality',
            ],
            [
                'page' => 1,
                'x' => 18,
                'y' => 65,
                'size' => 8,
                'width' => 174,
                'line_height' => 4,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 48.34,
                'y' => 76.7,
                'size' => 9,
                'value' => 'applicant.position_or_designation',
            ],
            [
                'page' => 1,
                'x' => 29.83,
                'y' => 84.7,
                'size' => 9,
                'value' => 'applicant.employer_or_business',
            ],
            [
                'page' => 1,
                'x' => 18,
                'y' => 94,
                'size' => 8,
                'width' => 174,
                'line_height' => 4,
                'value' => 'applicant.office_address',
            ],
            [
                'page' => 1,
                'x' => 63.33,
                'y' => 123.7,
                'size' => 9,
                'value' => 'loan.gnthp',
            ],
            [
                'page' => 1,
                'x' => 50,
                'y' => 129.7,
                'size' => 9,
                'value' => 'authorization.payout_account_number',
            ],
            [
                'page' => 1,
                'x' => 51.16,
                'y' => 135.7,
                'size' => 9,
                'value' => 'authorization.payout_atm_number',
            ],
            [
                'page' => 1,
                'x' => 43.33,
                'y' => 141.7,
                'size' => 9,
                'value' => 'authorization.payout_bank_name',
            ],
            [
                'page' => 1,
                'x' => 37.17,
                'y' => 147.7,
                'size' => 9,
                'value' => 'authorization.payout_bank_branch',
            ],
            [
                'page' => 1,
                'x' => 92.33,
                'y' => 219.97,
                'size' => 9,
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 159.04,
                'y' => 219.97,
                'size' => 9,
                'value' => 'notarial.signing_place',
            ],
            [
                'page' => 1,
                'x' => 58,
                'y' => 258.42,
                'size' => 8,
                'value' => 'notarial.province',
            ],
            [
                'page' => 1,
                'x' => 43.17,
                'y' => 264.42,
                'size' => 8,
                'value' => 'notarial.valid_id_number',
            ],
            [
                'page' => 1,
                'x' => 43.5,
                'y' => 270.42,
                'size' => 8,
                'value' => 'notarial.valid_id_issued_at',
            ],
            [
                'page' => 1,
                'x' => 30.5,
                'y' => 280.42,
                'size' => 8,
                'value' => 'notarial.doc_number',
            ],
            [
                'page' => 1,
                'x' => 76.67,
                'y' => 280.42,
                'size' => 8,
                'value' => 'notarial.page_number',
            ],
            [
                'page' => 1,
                'x' => 121,
                'y' => 280.42,
                'size' => 8,
                'value' => 'notarial.book_number',
            ],
            [
                'page' => 1,
                'x' => 164.17,
                'y' => 280.42,
                'size' => 8,
                'value' => 'notarial.series_year',
            ],
        ];
    }
}
