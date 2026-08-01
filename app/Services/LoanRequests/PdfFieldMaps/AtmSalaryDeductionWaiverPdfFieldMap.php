<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

/**
 * Placeholder coordinates only -- no real blank PDF template has been supplied
 * for this document yet (unlike PensionDeductionWaiverPdfFieldMap/
 * DepedSalaryDeductionWaiverPdfFieldMap, which were calibrated against real
 * forms). The catalog's templateBlockers() check keeps this document from
 * reaching "ReadyToGenerate" until storage/app/templates/approved-loan-documents/pdf/atm-salary-deduction-waiver.pdf
 * exists, so these coordinates are safe to land now and recalibrate once the
 * template arrives.
 */
class AtmSalaryDeductionWaiverPdfFieldMap implements ApprovedLoanPdfFieldMap
{
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
                'width' => 68,
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
            [
                'page' => 1,
                'x' => 118,
                'y' => 55,
                'size' => 11,
                'width' => 72,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'applicant.address',
            ],
            // "That I am a regular employee of ___" -- the wording differs from the
            // Pension waiver's "That I am a regular pensioner of ___" clause, but the
            // blank sits at the same coordinates and is filled from the applicant's
            // actual employer rather than a separate staff-entered field.
            [
                'page' => 1,
                'x' => 97,
                'y' => 75,
                'size' => 11,
                'width' => 83,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'applicant.employer_or_business',
            ],
            [
                'page' => 1,
                'x' => 44,
                'y' => 83,
                'size' => 11,
                'width' => 68,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.atm_salary_deduction_bank_name',
            ],
            [
                'page' => 1,
                'x' => 152,
                'y' => 83,
                'size' => 11,
                'width' => 38,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.atm_salary_deduction_card_number',
            ],
            // "to deduct- <amount in words> (<amount>) from my SALARY" -- both blanks
            // sit inline on clause 2's second line, mirroring the Pension waiver layout.
            [
                'page' => 1,
                'x' => 47,
                'y' => 103,
                'size' => 11,
                'width' => 83,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.atm_salary_deduction_amount_words',
            ],
            [
                'page' => 1,
                'x' => 137,
                'y' => 103,
                'size' => 11,
                'width' => 33,
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'deduction.atm_salary_deduction_amount',
            ],
            [
                'page' => 1,
                'x' => 40,
                'y' => 125,
                'size' => 11,
                'width' => 13,
                'align' => 'C',
                'value' => 'loan.approved_date_day',
            ],
            [
                'page' => 1,
                'x' => 71,
                'y' => 125,
                'size' => 11,
                'width' => 38,
                'align' => 'C',
                'value' => 'loan.approved_date_month_year',
            ],
            [
                'page' => 1,
                'x' => 119,
                'y' => 125,
                'size' => 11,
                'width' => 71,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 6.0,
                'value' => 'notarial.signing_place',
            ],
            [
                'page' => 1,
                'x' => 60,
                'y' => 171,
                'size' => 11,
                'style' => 'B',
                'width' => 90,
                'align' => 'C',
                'shrink_to_fit' => true,
                'min_size' => 7.0,
                'value' => 'applicant.full_name',
            ],
        ];
    }
}
