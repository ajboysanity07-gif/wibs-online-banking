<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class AffidavitUndertakingPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'type' => 'image',
                'x' => 18,
                'y' => 10,
                'width' => 174,
                'height' => 18,
                'value' => 'organization.report_header.designPath',
            ],
            [
                'page' => 1,
                'x' => 46.5,
                'y' => 41.5,
                'size' => 10,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 26.5,
                'y' => 49.1,
                'size' => 9,
                'value' => 'applicant.age',
            ],
            [
                'page' => 1,
                'x' => 83.5,
                'y' => 49.1,
                'size' => 9,
                'value' => 'applicant.civil_status',
            ],
            [
                'page' => 1,
                'x' => 141.5,
                'y' => 49.1,
                'size' => 9,
                'value' => 'applicant.nationality',
            ],
            [
                'page' => 1,
                'x' => 78,
                'y' => 54.5,
                'size' => 8,
                'width' => 113,
                'line_height' => 3,
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 52,
                'y' => 63.1,
                'size' => 9,
                'value' => 'applicant.position_or_designation',
            ],
            [
                'page' => 1,
                'x' => 32.5,
                'y' => 68.1,
                'size' => 9,
                'value' => 'applicant.employer_or_business',
            ],
            [
                'page' => 1,
                'x' => 62,
                'y' => 73.5,
                'size' => 8,
                'width' => 129,
                'line_height' => 3,
                'value' => 'applicant.office_address',
            ],
            // GNTHP and payout account number now sit inline in paragraph 1's rewritten
            // sentence (Phase 2 artwork) rather than on separate labeled sub-lines.
            [
                'page' => 1,
                'x' => 74.28,
                'y' => 124.89,
                'size' => 9,
                'value' => 'loan.gnthp',
            ],
            [
                'page' => 1,
                'x' => 82.51,
                'y' => 129.90,
                'size' => 9,
                'value' => 'authorization.payout_account_number',
            ],
            [
                'page' => 1,
                'x' => 59.14,
                'y' => 138.11,
                'size' => 9,
                'value' => 'authorization.payout_atm_number',
            ],
            [
                'page' => 1,
                'x' => 50.91,
                'y' => 146.11,
                'size' => 9,
                'value' => 'authorization.payout_bank_name',
            ],
            [
                'page' => 1,
                'x' => 43.66,
                'y' => 154.10,
                'size' => 9,
                'value' => 'authorization.payout_bank_branch',
            ],
            [
                'page' => 1,
                'x' => 97.5,
                'y' => 254.56,
                'size' => 9,
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 157.5,
                'y' => 254.56,
                'size' => 9,
                'value' => 'notarial.signing_place',
            ],
            // notarial.province is intentionally not wired here -- the "for and in ___"
            // blank is filled by hand by the notary, same as valid_id_number/
            // valid_id_issued_at/doc_number/etc below.
            // Doc No. / Page No. / Book No. (x=18, y=297.56/305.56/313.56) are intentionally
            // left blank space on the artwork for the notary to fill by hand — see
            // buildDocumentData()'s 'notarial' block for why.
            [
                'page' => 1,
                'x' => 34.9,
                'y' => 321.56,
                'size' => 10,
                'value' => 'notarial.series_year',
            ],
        ];
    }
}
