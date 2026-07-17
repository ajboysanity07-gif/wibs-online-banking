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
                // Override DocumentSignaturePlacement's default 2x SIGNATURE_SCALE_FACTOR
                // (tuned for small hand-drawn signature stamps) -- the header image must
                // fit the declared 174x18mm box as-is, not be blown up and bleed into the
                // title below it.
                'scale' => 1.0,
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
                'x' => 59,
                'y' => 120.5,
                // Sits inline inside paragraph 1's sentence, between "...Pay of " and
                // "(Guaranteed NTHP)" -- the next word starts at x≈82.65mm (measured from the
                // real artwork's content stream), leaving ~20mm before it collides. A large
                // approved amount ("₱1,250,000.00") doesn't fit that blank at size 11, so it
                // shrinks to fit rather than overflow into the parenthetical.
                'size' => 11,
                'width' => 20,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
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
            // Signature over Printed Name / Date / Place of Signing / BORROWER now sits
            // in a bordered 3-column table (x=18-192, y=252-268) instead of freeform
            // underlines -- see the throwaway artwork builder script referenced in the
            // commit that rebuilt this section. Coordinates below are remeasured fresh
            // against the new cell geometry, not reused from the old freeform layout.
            [
                'page' => 1,
                'x' => 33,
                'y' => 250,
                // A long full name (multiple middle names, suffixes) can exceed this
                // column's ~50mm width -- shrinks to fit rather than overflowing into the
                // Date column or wrapping a name mid-word.
                'size' => 11,
                'style' => 'B',
                'width' => 50,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 83,
                'y' => 250,
                'size' => 11,
                'style' => 'B',
                'width' => 50,
                'align' => 'C',
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 133,
                'y' => 250,
                // Carries the full composed org address (street/barangay, city, province),
                // not just a short city name -- shrinks to fit this ~50mm-wide column
                // instead of wrapping to multiple lines (which risked colliding with the
                // "Place of Signing" caption directly beneath it, confirmed by rendering
                // against the real production artwork, not the test placeholder).
                'size' => 11,
                'style' => 'B',
                'width' => 50,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
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
