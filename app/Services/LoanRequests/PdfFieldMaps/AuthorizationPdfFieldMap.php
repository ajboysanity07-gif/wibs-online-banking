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
                'y' => 10,
                'width' => 174,
                'height' => 14,
                'scale' => 1.5,
                'value' => 'organization.report_header.designPath',
            ],
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
            // Repointed from loan.approved_amount -- the real reference paragraph says
            // "...credit the loan security of my loan in the amount of ___", which is the
            // loan security deduction, not the raw approved principal. Position unchanged.
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
            // authorization.release_method (CONFIRMED DEAD) and authorization.payout_bank_name
            // (bank name is now static artwork text -- "Enterprise Bank, Inc." is MRDINC's
            // fixed partner bank, not a per-loan data field) are intentionally not wired here.
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
            // Signature block -- values print above the underline strokes drawn at y=150
            // and y=195 in the artwork. y offset of -4.2mm from the line confirmed by real
            // rendering (mirrors the same fix applied to UndertakingBarangayPdfFieldMap).
            [
                'page' => 1,
                'x' => 20,
                'y' => 145.8,
                'size' => 10,
                'style' => 'B',
                'width' => 70,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 20,
                'y' => 190.8,
                'size' => 10,
                'style' => 'B',
                'width' => 70,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'authorization.payout_atm_holder_name',
            ],
        ];
    }
}
