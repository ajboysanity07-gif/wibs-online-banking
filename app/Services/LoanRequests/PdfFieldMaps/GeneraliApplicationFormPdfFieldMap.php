<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

/**
 * Generali (GLAPI) "Individual Application Form" (Form No: 005) -- distinct
 * from GeneraliPdfFieldMap's "Individual Application and Health Statement
 * Form": no health questionnaire, but adds a self-PEP question, an
 * ID-type/source-of-funds row, and the full dependents table (spouse/
 * children/siblings/parents/extended relatives).
 *
 * Coordinates were measured directly off the real template's text layer
 * (PyMuPDF word/line bounding boxes converted to mm) rather than typeset from
 * scratch, but the blank fill-in lines themselves obviously have no text to
 * measure -- placements are therefore best-effort until confirmed via
 * `php artisan loan-documents:calibrate-fields ga`.
 */
class GeneraliApplicationFormPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    public function fields(): array
    {
        return [
            ...$this->identityFields(),
            ...$this->employmentFields(),
            ...$this->pepFields(),
            ...$this->sourceOfFundFields(),
            ...$this->beneficiaryFields(),
            ...$this->dependentsFields(),
            ...$this->signatureFields(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function identityFields(): array
    {
        return [
            ['page' => 1, 'x' => 27.3, 'y' => 83.5, 'size' => 9, 'width' => 36, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.last_name'],
            ['page' => 1, 'x' => 65.9, 'y' => 83.5, 'size' => 9, 'width' => 38, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.first_name'],
            ['page' => 1, 'x' => 106.7, 'y' => 83.5, 'size' => 9, 'width' => 33, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.middle_name'],

            // The form's own "New/Old enrollment cycle" checkbox for the applicant
            // (distinct from each dependent's own cycle checkbox below).
            ['page' => 1, 'type' => 'check', 'x' => 142.7, 'y' => 78.6, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'application_form.cycle_status') === 'New'],
            ['page' => 1, 'type' => 'check', 'x' => 142.7, 'y' => 83.1, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'application_form.cycle_status') === 'Old'],
            ['page' => 1, 'x' => 178.0, 'y' => 84.0, 'size' => 9, 'width' => 6, 'value' => 'application_form.cycle_number'],

            // Street value goes under the "(Street No.)" caption, not under
            // the "Residence Address" row label -- applicants almost always
            // give a Purok name rather than a numbered street.
            ['page' => 1, 'x' => 92.7, 'y' => 95.5, 'size' => 9, 'width' => 68, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_street'],
            ['page' => 1, 'x' => 163.8, 'y' => 95.5, 'size' => 9, 'width' => 24, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_barangay'],
            ['page' => 1, 'x' => 43.1, 'y' => 101.5, 'size' => 9, 'width' => 48, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_city'],
            ['page' => 1, 'x' => 94.0, 'y' => 101.5, 'size' => 9, 'width' => 34, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_province'],
            ['page' => 1, 'x' => 130.5, 'y' => 101.5, 'size' => 9, 'width' => 24, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => static fn (): string => 'Philippines'],
            ['page' => 1, 'x' => 165.6, 'y' => 101.5, 'size' => 9, 'width' => 22, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_zip'],

            // "Home"/"Office" are landline-type columns the app doesn't distinguish
            // from mobile beyond work_phone -- mobile goes under Cell Phone only.
            ['page' => 1, 'x' => 76.5, 'y' => 112.5, 'size' => 9, 'width' => 45, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.work_phone'],
            ['page' => 1, 'x' => 135.6, 'y' => 112.5, 'size' => 9, 'width' => 35, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.mobile'],

            ['page' => 1, 'x' => 27.3, 'y' => 125.0, 'size' => 9, 'value' => 'applicant.birthdate'],
            ['page' => 1, 'x' => 76.5, 'y' => 125.0, 'size' => 9, 'width' => 55, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.place_of_birth'],
            ['page' => 1, 'x' => 135.4, 'y' => 125.0, 'size' => 9, 'value' => 'applicant.age'],
            ['page' => 1, 'type' => 'check', 'x' => 158.4, 'y' => 121.9, 'size' => 9, 'value' => static fn (array $d): bool => strtoupper((string) (data_get($d, 'applicant.sex') ?? '')) === 'MALE'],
            ['page' => 1, 'type' => 'check', 'x' => 158.4, 'y' => 126.2, 'size' => 9, 'value' => static fn (array $d): bool => strtoupper((string) (data_get($d, 'applicant.sex') ?? '')) === 'FEMALE'],

            ['page' => 1, 'x' => 27.3, 'y' => 137.0, 'size' => 9, 'value' => 'applicant.nationality'],
            ['page' => 1, 'x' => 76.5, 'y' => 137.0, 'size' => 9, 'value' => 'applicant.nationality'],
            ['page' => 1, 'type' => 'check', 'x' => 124.7, 'y' => 134.6, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'applicant.civil_status') === 'Single'],
            ['page' => 1, 'type' => 'check', 'x' => 152.5, 'y' => 134.6, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'applicant.civil_status') === 'Married'],
            ['page' => 1, 'type' => 'check', 'x' => 124.7, 'y' => 138.9, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'applicant.civil_status') === 'Separated'],
            ['page' => 1, 'type' => 'check', 'x' => 152.2, 'y' => 138.9, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'applicant.civil_status') === 'Widowed'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function employmentFields(): array
    {
        return [
            ['page' => 1, 'x' => 27.3, 'y' => 177.0, 'size' => 9, 'width' => 90, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.employer_or_business'],
            ['page' => 1, 'x' => 124.1, 'y' => 177.0, 'size' => 9, 'width' => 60, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.nature_of_business'],

            ['page' => 1, 'x' => 27.3, 'y' => 186.0, 'size' => 9, 'width' => 90, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.office_address_line'],
            ['page' => 1, 'x' => 124.1, 'y' => 186.0, 'size' => 9, 'width' => 60, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.email'],

            ['page' => 1, 'x' => 27.3, 'y' => 193.5, 'size' => 9, 'width' => 75, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.position_or_designation'],
            ['page' => 1, 'x' => 124.1, 'y' => 193.5, 'size' => 9, 'width' => 18, 'value' => 'application_form.employer_date_employed'],

            // Credit Life section -- reuses the same recommended amount/term already
            // printed on the other approved documents (same precedent as
            // GeneraliPdfFieldMap's identityFields()).
            ['page' => 1, 'x' => 27.3, 'y' => 205.5, 'size' => 9, 'value' => 'loan.approved_amount'],
            ['page' => 1, 'x' => 124.1, 'y' => 205.5, 'size' => 9, 'value' => 'loan.approved_term_label'],
        ];
    }

    /**
     * The applicant's own PEP question -- distinct from GeneraliPdfFieldMap's
     * gl_health_q16_relative_pep, which asks about a relative, not the applicant.
     * The form uses "[    ] Yes [    ] No" bracket checkboxes rather than the ☐
     * glyph used elsewhere, but the same check-mark stamping applies.
     *
     * @return list<array<string, mixed>>
     */
    private function pepFields(): array
    {
        return [
            ['page' => 1, 'type' => 'check', 'x' => 85.6, 'y' => 143.2, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'application_form.pep_status') === true],
            ['page' => 1, 'type' => 'check', 'x' => 99.5, 'y' => 143.2, 'size' => 9, 'value' => static fn (array $d): bool => data_get($d, 'application_form.pep_status') === false],
            ['page' => 1, 'x' => 152.0, 'y' => 161, 'size' => 9, 'width' => 40, 'shrink_to_fit' => true, 'min_size' => 5.5, 'value' => 'application_form.pep_status_details'],
        ];
    }

    /**
     * "Source of Fund/s" (free text) plus the ID-type row: SSS/GSIS/TIN/Phil I.D.
     * number goes on one blank, a hand-specified "Others" ID type + number on the
     * other -- only one of the two is ever filled, based on id_type.
     *
     * @return list<array<string, mixed>>
     */
    private function sourceOfFundFields(): array
    {
        return [
            ['page' => 1, 'x' => 27.3, 'y' => 170.0, 'size' => 9, 'width' => 63, 'shrink_to_fit' => true, 'min_size' => 5.5, 'value' => 'application_form.source_of_fund_wealth'],
            [
                'page' => 1, 'x' => 148.0, 'y' => 166.0, 'size' => 9, 'width' => 35, 'shrink_to_fit' => true, 'min_size' => 5.5,
                'value' => static function (array $d): ?string {
                    $idType = data_get($d, 'application_form.id_type');

                    return $idType !== null && $idType !== 'Others' ? data_get($d, 'application_form.id_number') : null;
                },
            ],
            [
                'page' => 1, 'x' => 148.0, 'y' => 170.0, 'size' => 9, 'width' => 35, 'shrink_to_fit' => true, 'min_size' => 5.5,
                'value' => static function (array $d): ?string {
                    if (data_get($d, 'application_form.id_type') !== 'Others') {
                        return null;
                    }

                    $label = data_get($d, 'application_form.id_type_other');
                    $number = data_get($d, 'application_form.id_number');

                    return trim(implode(': ', array_filter([$label, $number], static fn ($part) => $part !== null && $part !== '')));
                },
            ],
        ];
    }

    /**
     * Same 2-row beneficiary table shape as GeneraliPdfFieldMap::beneficiaryFields()
     * -- citizenship isn't collected per-beneficiary, so it's sourced from
     * applicant.nationality, same as the other Generali document.
     *
     * @return list<array<string, mixed>>
     */
    private function beneficiaryFields(): array
    {
        $row = static fn (int $index, float $y): array => [
            ['page' => 1, 'x' => 25, 'y' => $y, 'size' => 9, 'width' => 47, 'shrink_to_fit' => true, 'min_size' => 6.0, 'alignment' => 'C', 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.name")],
            ['page' => 1, 'x' => 75.5, 'y' => $y, 'size' => 9, 'width' => 35.5, 'shrink_to_fit' => true, 'min_size' => 6.0, 'alignment' => 'C', 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.birthdate")],
            ['page' => 1, 'x' => 115.5, 'y' => $y, 'size' => 9, 'width' => 30, 'shrink_to_fit' => true, 'min_size' => 6.0, 'alignment' => 'C', 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.name") !== null ? data_get($d, 'applicant.nationality') : null],
            ['page' => 1, 'x' => 152.5, 'y' => $y, 'size' => 9, 'width' => 35, 'shrink_to_fit' => true, 'min_size' => 6.0, 'alignment' => 'C', 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.relationship")],
        ];

        return [...$row(0, 222.0), ...$row(1, 226.5)];
    }

    /**
     * Spouse (page 1) + Children/Siblings/Parents/Extended (page 2), each row
     * combining name, DOB, age, and a New/Old enrollment-cycle checkbox on a
     * single printed line -- the first document to consume the
     * dependent_{category}_{slot}_* flat fields (see ApprovedLoanDocumentService
     * ::dependentsDocumentData()).
     *
     * @return list<array<string, mixed>>
     */
    private function dependentsFields(): array
    {
        return [
            ...$this->dependentRow(1, 259.6, 'dependents.spouse', nameWidth: 68, checkY: 258.5),
            ...$this->dependentRow(2, 50.1, 'dependents.children.0', checkY: 49.1),
            ...$this->dependentRow(2, 54.8, 'dependents.children.1', checkY: 53.7),
            ...$this->dependentRow(2, 59.4, 'dependents.children.2', checkY: 58.3),
            ...$this->dependentRow(2, 71.4, 'dependents.siblings.0', checkY: 70.3),
            ...$this->dependentRow(2, 76.0, 'dependents.siblings.1', checkY: 74.9),
            ...$this->dependentRow(2, 80.7, 'dependents.siblings.2', checkY: 79.6),
            ...$this->dependentRow(2, 94.7, 'dependents.parents.0', checkY: 93.6),
            ...$this->dependentRow(2, 99.4, 'dependents.parents.1', checkY: 98.2),
            ...$this->dependentRow(2, 113.4, 'dependents.extended.0', checkY: 112.3),
            ...$this->dependentRow(2, 118.0, 'dependents.extended.1', checkY: 116.9),
            ...$this->dependentRow(2, 122.7, 'dependents.extended.2', checkY: 121.6),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dependentRow(int $page, float $y, string $path, int $nameWidth = 70, ?float $checkY = null): array
    {
        $checkY ??= $y - 1.9;

        return [
            ['page' => $page, 'label' => "{$path}.name", 'x' => 28.5, 'y' => $y, 'size' => 9, 'width' => $nameWidth, 'shrink_to_fit' => true, 'min_size' => 5.5, 'value' => static fn (array $d) => data_get($d, "{$path}.name")],
            ['page' => $page, 'label' => "{$path}.birthdate", 'x' => 156.9, 'y' => $y, 'size' => 9, 'width' => 15, 'shrink_to_fit' => true, 'min_size' => 5.5, 'value' => static fn (array $d) => data_get($d, "{$path}.birthdate")],
            ['page' => $page, 'label' => "{$path}.age", 'x' => 181.6, 'y' => $y, 'size' => 9, 'width' => 5, 'value' => static fn (array $d) => data_get($d, "{$path}.age")],
            ['page' => $page, 'type' => 'check', 'label' => "{$path}.cycle_status=New", 'x' => 98.8, 'y' => $checkY, 'size' => 9, 'value' => static fn (array $d) => data_get($d, "{$path}.cycle_status") === 'New'],
            ['page' => $page, 'type' => 'check', 'label' => "{$path}.cycle_status=Old", 'x' => 122.6, 'y' => $checkY, 'size' => 9, 'value' => static fn (array $d) => data_get($d, "{$path}.cycle_status") === 'Old'],
        ];
    }

    /**
     * Signature block: "SIGNED AT ___ ON ___", witness printed name baked into
     * the template artwork (like AU/the health-statement form), borrower
     * signature left blank for physical signing.
     *
     * @return list<array<string, mixed>>
     */
    private function signatureFields(): array
    {
        return [
            ['page' => 3, 'x' => 40.0, 'y' => 237.2, 'size' => 9, 'width' => 65, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'notarial.signing_place'],
            ['page' => 3, 'x' => 112.0, 'y' => 237.2, 'size' => 9, 'width' => 70, 'value' => 'loan.approved_date'],
        ];
    }
}
