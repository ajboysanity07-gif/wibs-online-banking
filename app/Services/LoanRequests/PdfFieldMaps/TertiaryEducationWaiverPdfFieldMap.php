<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

use App\Services\LoanRequests\PdfFieldMaps\Concerns\UppercasesFieldValues;

/**
 * Variant of DepedSalaryDeductionWaiverPdfFieldMap used when the applicant's
 * employer is a tertiary institution (state university/college, etc.) rather
 * than basic-education DepEd. The underlying template PDF
 * (deped-salary-deduction-waiver-tertiary.pdf) replaces the baked-in
 * "Dep. Ed."/"DEPED" wording in clauses 1, 2 and 4 with blanks filled here
 * from the applicant's actual employer name -- clause 5's "Dep. Ed. Order
 * No. 55" legal citation is untouched on the template itself since it
 * references a real DepEd regulation, not the employer.
 */
class TertiaryEducationWaiverPdfFieldMap implements ApprovedLoanPdfFieldMap
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
                'scale' => 1.5,
                'value' => 'organization.report_header.designPath',
            ],
            [
                'page' => 1,
                'x' => 24,
                'y' => 55,
                'size' => 11,
                'width' => 90,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 140,
                'y' => 55,
                'size' => 11,
                'width' => 50,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'applicant.address',
            ],
            // Institution name -- clause 1 ("regular teacher/non-teaching staff of ___"),
            // its own row beneath the clause text since a long institution name
            // collides with the clause wording when placed on the same line.
            [
                'page' => 1,
                'x' => 18,
                'y' => 80,
                'size' => 11,
                'width' => 110,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.deped_institution_name',
            ],
            [
                'page' => 1,
                'x' => 18,
                'y' => 92,
                'size' => 11,
                'width' => 90,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'deduction.deped_school_id_number',
            ],
            // Institution name -- clause 2 ("threshold set by ___")
            [
                'page' => 1,
                'x' => 18,
                'y' => 106,
                'size' => 11,
                'width' => 90,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.deped_institution_name',
            ],
            // Institution name -- clause 4 ("...RURAL DEVELOPMENT INC. with ___ to deduct-")
            [
                'page' => 1,
                'x' => 18,
                'y' => 128,
                'size' => 11,
                'width' => 78,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.deped_institution_name',
            ],
            [
                'page' => 1,
                'x' => 42,
                'y' => 136,
                'size' => 11,
                'width' => 88,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.deped_deduction_amount_words',
            ],
            [
                'page' => 1,
                'x' => 141,
                'y' => 136,
                'size' => 11,
                'width' => 33,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.deped_deduction_amount',
            ],
            [
                'page' => 1,
                'x' => 139,
                'y' => 166,
                'size' => 11,
                'width' => 51,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'notarial.signing_place',
            ],
            // Single signature block -- printed name over "Borrower", matching the
            // real MRDINC form exactly. No CONFORME/APPROVED/Loan Manager row: the
            // real form ends at the borrower's own signature.
            [
                'page' => 1,
                'x' => 60,
                'y' => 190,
                'size' => 11,
                'style' => 'B',
                'width' => 90,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
                'transform' => $this->upperTransform(),
            ],
        ];
    }
}
