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
                'x' => 20,
                'y' => 12,
                'width' => 176,
                'height' => 20,
                'scale' => 1.0,
                'value' => 'organization.report_header.designPath',
            ],
            // Inline blanks in paragraph 1 -- coordinates captured live from the artwork
            // builder script at generation time (build_authorization_artwork.php,
            // throwaway/not committed, see the rebuild commit message), not guessed
            // against the rendered PDF after the fact. The "amount in words" blank ahead
            // of this one has no data source and is left for hand-fill, same convention
            // as AU/UB's notary-filled blanks.
            [
                'page' => 1,
                'x' => 83.18,
                'y' => 80.5,
                'size' => 11,
                'style' => 'B',
                'width' => 34,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'loan.loan_security_amount_plain',
            ],
            [
                'page' => 1,
                'x' => 25.4,
                'y' => 87.0,
                'size' => 11,
                'style' => 'B',
                'width' => 52,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'authorization.payout_atm_holder_name',
            ],
            [
                'page' => 1,
                'x' => 121.32,
                'y' => 87.0,
                'size' => 11,
                'style' => 'B',
                'width' => 38,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'authorization.payout_account_number',
            ],
            [
                'page' => 1,
                'x' => 58.75,
                'y' => 93.5,
                'size' => 11,
                'style' => 'B',
                'width' => 38,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'authorization.payout_bank_branch',
            ],
            // Signature block -- values print above the underline strokes drawn in the
            // artwork (y=132 Borrower, y=180 ATM Card Holder). ~5mm clearance mirrors the
            // same class of fix already applied on AU/UB's own signature lines.
            [
                'page' => 1,
                'x' => 45.4,
                'y' => 127.0,
                'size' => 11,
                'style' => 'B',
                'width' => 65,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 95.4,
                'y' => 175.0,
                'size' => 11,
                'style' => 'B',
                'width' => 65,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'authorization.payout_atm_holder_name',
            ],
        ];
    }
}
