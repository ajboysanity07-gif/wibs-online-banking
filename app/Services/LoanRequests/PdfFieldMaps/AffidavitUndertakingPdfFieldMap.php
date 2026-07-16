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
                'x' => 20,
                'y' => 20,
                'width' => 174,
                'height' => 18,
                // Override DocumentSignaturePlacement's default 2x SIGNATURE_SCALE_FACTOR
                // (tuned for small hand-drawn signature stamps) -- the header image must
                // fit the declared 174x18mm box as-is, not be blown up and bleed into the
                // title below it.
                'scale' => 2,
                'value' => 'organization.report_header.designPath',
            ],
            [
                'page' => 1,
                'x' => 53,
                'y' => 62.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 36.5,
                'y' => 69.25,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.age',
            ],
            [
                'page' => 1,
                'x' => 109,
                'y' => 69.25,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.civil_status',
            ],
            [
                'page' => 1,
                'x' => 156,
                'y' => 69.25,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.nationality',
            ],
            [
                'page' => 1,
                'x' => 92.5,
                'y' => 75,
                'size' => 11,
                'width' => 113,
                'style' => 'B',
                'value' => 'applicant.address',
            ],
            [
                'page' => 1,
                'x' => 64.5,
                'y' => 80.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.position_or_designation',
            ],
            [
                'page' => 1,
                'x' => 40.5,
                'y' => 86.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.employer_or_business',
            ],
            [
                'page' => 1,
                'x' => 73.5,
                'y' => 92.25,
                'size' => 11,
                'style' => 'B',
                'width' => 129,
                // 'line_height' => 3,
                'value' => 'applicant.office_address',
            ],
            // GNTHP and payout account number now sit inline in paragraph 1's rewritten
            // sentence (Phase 2 artwork) rather than on separate labeled sub-lines.
            [
                'page' => 1,
                'x' => 59,
                'y' => 120.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'loan.gnthp',
            ],
            [
                'page' => 1,
                'x' => 106.5,
                'y' => 131,
                'size' => 11,
                'style' => 'B',
                'value' => 'authorization.payout_account_number',
            ],
            [
                'page' => 1,
                'x' => 106.5,
                'y' => 136,
                'size' => 11,
                'style' => 'B',
                'value' => 'authorization.payout_atm_number',
            ],
            [
                'page' => 1,
                'x' => 106.5,
                'y' => 141,
                'size' => 11,
                'style' => 'B',
                'value' => 'authorization.payout_bank_name',
            ],
            [
                'page' => 1,
                'x' => 106.5,
                'y' => 146,
                'size' => 11,
                'style' => 'B',
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
                'size' => 11,
                'style' => 'B',
                'width' => 50,
                'align' => 'C',
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
                'y' => 243,
                // Given a width so it wraps via MultiCell -- this now carries the full
                // composed org address (street/barangay, city, province), not just a short
                // city name. y is nudged up from the row's other two fields' y=250 and
                // line_height tightened so a 2-line wrap (needed for longer addresses)
                // still clears the "Place of Signing" caption baseline, measured directly
                // from the real production artwork's content stream at y=258.4mm -- confirmed
                // by rendering against the real artwork (not the test placeholder) with both
                // the real production address and a longer hypothetical one.
                'size' => 11,
                'style' => 'B',
                'width' => 55,
                'line_height' => 3.2,
                'align' => 'C',
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
                'x' => 45,
                'y' => 300,
                'size' => 10,
                'line_height' => 3,
                'style' => 'B',
                'value' => 'notarial.series_year',
            ],
        ];
    }
}
