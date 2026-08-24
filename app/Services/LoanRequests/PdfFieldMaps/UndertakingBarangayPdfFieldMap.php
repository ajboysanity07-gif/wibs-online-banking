<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

use App\Services\LoanRequests\PdfFieldMaps\Concerns\UppercasesFieldValues;

class UndertakingBarangayPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    use UppercasesFieldValues;

    public function fields(): array
    {
        return [
            [
                'page' => 1,
                'type' => 'image',
                'x' => 18,
                'y' => 15,
                'width' => 174,
                'height' => 18,
                // Override DocumentSignaturePlacement's default 2x SIGNATURE_SCALE_FACTOR
                // (tuned for small hand-drawn signature stamps) -- the header image must
                // fit the declared 174x18mm box as-is, not be blown up and bleed into the
                // title below it.
                'scale' => 1.5,
                'value' => 'organization.report_header.designPath',
            ],
            [
                'page' => 1,
                'x' => 55,
                'y' => 71,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 92.5,
                'y' => 83.25,
                'size' => 11,
                'style' => 'B',
                'width' => 160,
                'value' => 'applicant.address',
            ],
            // loan.type, the old-position loan.approved_date, and organization.company_name
            // were dropped here in the artwork rebuild (loan.approved_date now lives only
            // on the signature line below; loan.type and organization.company_name have no
            // remaining slot in the rewritten paragraph text).
            // Age/Civil Status/Nationality -- new row occupying the space vacated by the
            // three dead barangay.* fields (removed, see LoanRequestDocumentCatalog and
            // buildDocumentData()). Column boundaries: 27-82, 88-142, 148-196.
            [
                'page' => 1,
                'x' => 37,
                'y' => 77.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.age',
            ],
            [
                'page' => 1,
                'x' => 110,
                'y' => 77.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.civil_status',
            ],
            [
                'page' => 1,
                'x' => 158,
                'y' => 77.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.nationality',
            ],
            // Designation/Agency/Agency Address now source from the applicant's own
            // employment record, not a staff-entered barangay.* override -- confirmed bug
            // fix, position unchanged.
            [
                'page' => 1,
                'x' => 65,
                'y' => 88.75,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.position_or_designation',
            ],
            [
                'page' => 1,
                'x' => 42.5,
                'y' => 94.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'applicant.employer_or_business',
            ],
            [
                'page' => 1,
                'x' => 72.5,
                'y' => 100.25,
                'size' => 11,
                'width' => 160,
                'style' => 'B',
                'value' => 'applicant.office_address',
            ],
            // GNTHP moved off its collision with loan.approved_amount at (107,62) -- now
            // sits inline in paragraph 1's rewritten sentence (same technique as AU's own
            // inline GNTHP blank), measured against the real artwork's content stream:
            // "...net take-home pay of " ends at x≈116.29mm on this line (y≈157.82), and the
            // baked-in blank run is ≈29.87mm wide before "(Guaranteed Net Take-Home Pay)"
            // resumes -- shrinks to fit rather than overflow into that parenthetical.
            [
                'page' => 1,
                'x' => 62.5,
                'y' => 133.75,
                'size' => 11,
                'style' => 'B',
                'width' => 33,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'loan.gnthp',
            ],
            // Paragraph 2's "...in the amount of Pesos: ___ (P ___)" blank, measured
            // against the real artwork's content stream: the label ends at x≈94.25mm and
            // the baked-in "(P" parenthetical starts at x≈160.02mm on this line (y=152.75,
            // same baseline as loan.approved_amount below) -- shrinks to fit rather than
            // overflow into "(P ___)" for long spelled-out amounts.
            [
                'page' => 1,
                'x' => 94.5,
                'y' => 152.75,
                'width' => 60,
                'size' => 11,
                'style' => 'B',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'loan.approved_amount_words',
            ],
            [
                'page' => 1,
                'x' => 164,
                'y' => 152.75,
                'width' => 23,
                'size' => 11,
                'style' => 'B',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'loan.approved_amount',
            ],
            // Signature block (bordered-line row, not a boxed table) -- values print above
            // the underline stroke drawn at y=221.7 in the artwork. y=217.5 confirmed by
            // real rendering: 219.7 (2mm clearance) let 10pt bold text collide with the
            // line stroke; 217.5 (4.2mm clearance) does not.
            [
                'page' => 1,
                'x' => 33,
                'y' => 205,
                'size' => 10,
                'style' => 'B',
                'width' => 50,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
                'transform' => $this->upperTransform(),
            ],
            // notarial_province ("for and in ___") is intentionally not wired here -- same
            // hand-fill convention as AU. Series of ___ sits on the last blank line of the
            // Doc/Page/Book/Series stack (y=282.6 label, underline at y=286.6).
            [
                'page' => 1,
                'x' => 135,
                'y' => 205,
                // Place of Signing column of the signature row. The Date column beside it
                // stays blank for hand-fill -- only the venue is system-printed (full
                // composed org address, shrink-to-fit for this ~45mm column).
                'size' => 11,
                'style' => 'B',
                'width' => 45,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'notarial.signing_place',
            ],
            [
                'page' => 1,
                'x' => 42,
                'y' => 254.5,
                'size' => 11,
                'style' => 'B',
                'value' => 'notarial.series_year',
            ],
        ];
    }
}
