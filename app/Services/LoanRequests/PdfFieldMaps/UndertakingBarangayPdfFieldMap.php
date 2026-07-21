<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class UndertakingBarangayPdfFieldMap implements ApprovedLoanPdfFieldMap
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
            // Age/Civil Status/Nationality -- new row occupying the space vacated by the
            // three dead barangay.* fields (removed, see LoanRequestDocumentCatalog and
            // buildDocumentData()). Column boundaries: 27-82, 88-142, 148-196.
            [
                'page' => 1,
                'x' => 27,
                'y' => 86,
                'size' => 9,
                'style' => 'B',
                'value' => 'applicant.age',
            ],
            [
                'page' => 1,
                'x' => 88,
                'y' => 86,
                'size' => 9,
                'style' => 'B',
                'value' => 'applicant.civil_status',
            ],
            [
                'page' => 1,
                'x' => 148,
                'y' => 86,
                'size' => 9,
                'style' => 'B',
                'value' => 'applicant.nationality',
            ],
            // Designation/Agency/Agency Address now source from the applicant's own
            // employment record, not a staff-entered barangay.* override -- confirmed bug
            // fix, position unchanged.
            [
                'page' => 1,
                'x' => 27,
                'y' => 106,
                'size' => 9,
                'value' => 'applicant.position_or_designation',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 114,
                'size' => 9,
                'value' => 'applicant.employer_or_business',
            ],
            [
                'page' => 1,
                'x' => 27,
                'y' => 122,
                'size' => 8,
                'width' => 160,
                'line_height' => 4,
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
                'x' => 116.29,
                'y' => 157.82,
                'size' => 10,
                'style' => 'B',
                'width' => 29.87,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'loan.gnthp',
            ],
            // Signature block (bordered-line row, not a boxed table) -- values print above
            // the underline stroke drawn at y=221.7 in the artwork. y=217.5 confirmed by
            // real rendering: 219.7 (2mm clearance) let 10pt bold text collide with the
            // line stroke; 217.5 (4.2mm clearance) does not.
            [
                'page' => 1,
                'x' => 25,
                'y' => 217.5,
                'size' => 10,
                'style' => 'B',
                'width' => 50,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 90,
                'y' => 217.5,
                'size' => 10,
                'width' => 45,
                'align' => 'C',
                'value' => 'loan.approved_date',
            ],
            [
                'page' => 1,
                'x' => 150,
                'y' => 217.5,
                'size' => 9,
                'width' => 40,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'notarial.signing_place',
            ],
            // notarial_province ("for and in ___") is intentionally not wired here -- same
            // hand-fill convention as AU. Series of ___ sits on the last blank line of the
            // Doc/Page/Book/Series stack (y=282.6 label, underline at y=286.6).
            [
                'page' => 1,
                'x' => 50,
                'y' => 284,
                'size' => 9,
                'value' => 'notarial.series_year',
            ],
        ];
    }
}
