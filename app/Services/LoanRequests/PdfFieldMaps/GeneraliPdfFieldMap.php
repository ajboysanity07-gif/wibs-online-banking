<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class GeneraliPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    /**
     * Shared Y/N checkbox + "details of yes answer" text column, identical
     * on both pages of the template (same table design repeats on page 2).
     */
    private const HEALTH_Y_X = 126.5;

    private const HEALTH_N_X = 133.5;

    private const HEALTH_DETAIL_X = 141.0;

    private const HEALTH_DETAIL_WIDTH = 60.0;

    public function fields(): array
    {
        return [
            ...$this->identityFields(),
            ...$this->beneficiaryFields(),
            ...$this->page1HealthFields(),
            ...$this->page2HealthFields(),
            ...$this->signatureFields(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function identityFields(): array
    {
        return [
            ['page' => 1, 'x' => 23.3, 'y' => 63.0, 'size' => 9, 'width' => 30, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.last_name'],
            ['page' => 1, 'x' => 54.7, 'y' => 63.0, 'size' => 9, 'width' => 42, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.first_name'],
            ['page' => 1, 'x' => 99.3, 'y' => 63.0, 'size' => 9, 'width' => 60, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.middle_name'],
            // Every WIBS loan applicant is the insured principal on this form -- no
            // member is ever a dependent on someone else's coverage in this context.
            // Hardcoded by design; no wizard field needed.
            ['page' => 1, 'type' => 'check', 'x' => 165.0, 'y' => 54.5, 'size' => 7, 'value' => static fn (): bool => true],

            // Fax: intentionally omitted -- not applicable to a loan application.
            // See WIBS_DOCUMENT_FIELD_MAP.md, Generali Health Statement section.

            ['page' => 1, 'x' => 23.3, 'y' => 81.0, 'size' => 9, 'width' => 57, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_line'],
            ['page' => 1, 'x' => 41.6, 'y' => 91.0, 'size' => 9, 'width' => 40, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_city'],
            ['page' => 1, 'x' => 85.0, 'y' => 91.0, 'size' => 9, 'width' => 45, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.address_province'],
            ['page' => 1, 'x' => 134.0, 'y' => 91.0, 'size' => 9, 'value' => static fn (): string => 'Philippines'],

            ['page' => 1, 'x' => 42.6, 'y' => 102.0, 'size' => 9, 'width' => 30, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.mobile'],
            ['page' => 1, 'x' => 113.4, 'y' => 102.0, 'size' => 9, 'width' => 33, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.mobile'],

            ['page' => 1, 'x' => 23.3, 'y' => 115.0, 'size' => 9, 'value' => 'applicant.birthdate'],
            ['page' => 1, 'x' => 64.2, 'y' => 115.0, 'size' => 9, 'width' => 55, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.place_of_birth'],
            ['page' => 1, 'x' => 124.1, 'y' => 115.0, 'size' => 9, 'value' => 'applicant.age'],
            ['page' => 1, 'type' => 'check', 'x' => 138.5, 'y' => 111.0, 'size' => 7, 'value' => static fn (array $d): bool => strtoupper((string) (data_get($d, 'applicant.sex') ?? '')) === 'MALE'],
            ['page' => 1, 'type' => 'check', 'x' => 138.5, 'y' => 115.2, 'size' => 7, 'value' => static fn (array $d): bool => strtoupper((string) (data_get($d, 'applicant.sex') ?? '')) === 'FEMALE'],

            ['page' => 1, 'x' => 23.3, 'y' => 126.0, 'size' => 9, 'value' => 'applicant.nationality'],
            ['page' => 1, 'x' => 64.2, 'y' => 126.0, 'size' => 9, 'value' => 'applicant.nationality'],
            ['page' => 1, 'x' => 104.3, 'y' => 126.0, 'size' => 9, 'width' => 55, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.position_or_designation'],

            ['page' => 1, 'x' => 23.3, 'y' => 143.0, 'size' => 9, 'width' => 100, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.employer_or_business'],
            ['page' => 1, 'x' => 124.1, 'y' => 143.0, 'size' => 9, 'width' => 75, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.nature_of_business'],

            ['page' => 1, 'x' => 23.3, 'y' => 150.0, 'size' => 9, 'width' => 100, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.office_address'],
            ['page' => 1, 'x' => 124.1, 'y' => 150.0, 'size' => 9, 'width' => 75, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.email'],

            ['page' => 1, 'x' => 23.3, 'y' => 157.0, 'size' => 9, 'width' => 100, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.position_or_designation'],

            // Only meaningful for a Credit Life rider on this loan -- reuses the same
            // recommended amount/term already printed on the other approved documents.
            ['page' => 1, 'x' => 23.3, 'y' => 168.0, 'size' => 9, 'value' => 'loan.approved_amount'],
            ['page' => 1, 'x' => 124.1, 'y' => 168.0, 'size' => 9, 'value' => 'loan.approved_term_label'],
        ];
    }

    /**
     * Only primary/secondary beneficiary rows are collected by the wizard (see
     * MemberApplicationProfile::beneficiaryFields()) -- citizenship isn't collected
     * per-beneficiary, so it's sourced from applicant.nationality, the same value
     * already printed elsewhere on this form and on AU/UB.
     *
     * @return list<array<string, mixed>>
     */
    private function beneficiaryFields(): array
    {
        $row = static fn (int $index, float $y): array => [
            ['page' => 1, 'x' => 41.6, 'y' => $y, 'size' => 8, 'width' => 42, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.name")],
            ['page' => 1, 'x' => 85.0, 'y' => $y, 'size' => 8, 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.birthdate")],
            ['page' => 1, 'x' => 124.1, 'y' => $y, 'size' => 8, 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.name") !== null ? data_get($d, 'applicant.nationality') : null],
            ['page' => 1, 'x' => 168.9, 'y' => $y, 'size' => 8, 'width' => 40, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => static fn (array $d) => data_get($d, "beneficiaries.{$index}.relationship")],
        ];

        return [...$row(0, 187.5), ...$row(1, 192.2)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function page1HealthFields(): array
    {
        return [
            ...$this->healthRow(1, 207.5, 'gl_health_q01_weight_change'),
            ...$this->healthRow(1, 220.7, 'gl_health_q02a_neuro'),
            ...$this->healthRow(1, 224.7, 'gl_health_q02b_respiratory'),
            ...$this->healthRow(1, 228.7, 'gl_health_q02c_cardiac'),
            ...$this->healthRow(1, 232.6, 'gl_health_q02d_digestive'),
            ...$this->healthRow(1, 242.8, 'gl_health_q02e_diabetes_renal'),
            ...$this->healthRow(1, 246.8, 'gl_health_q02f_musculoskeletal'),
            ...$this->healthRow(1, 250.8, 'gl_health_q02g_oncology_blood'),
            ...$this->healthRow(1, 254.8, 'gl_health_q02h_dermatologic'),
            ...$this->healthRow(1, 258.8, 'gl_health_q02i_std_viral'),
            ...$this->healthRow(1, 267.2, 'gl_health_q02j_other_illness'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function page2HealthFields(): array
    {
        return [
            // Item 3 reuses the plain "health" section's hypertension boolean (already
            // collected on the earlier, simpler Health declarations step) rather than a
            // GLAPI-only duplicate -- see LoanRequestDataService's comment on
            // health_hypertension_details ("GLAPI Q3").
            ...$this->healthRow(2, 34.2, 'health_hypertension', section: 'health'),
            ...$this->healthRow(2, 38.5, 'gl_health_q04_prescribed_drugs'),
            ...$this->healthRow(2, 53.4, 'gl_health_q05_confinement_5yr'),
            ...$this->healthRow(2, 58.6, 'gl_health_q06_abnormal_labs'),
            ...$this->healthRow(2, 67.0, 'gl_health_q07_confinement_contemplated'),
            ...$this->healthRow(2, 71.2, 'gl_health_q08_blood_transfusion'),
            ...$this->healthRow(2, 78.9, 'gl_health_q09_other_disease'),
            ...$this->healthRow(2, 82.7, 'gl_health_q10_narcotics'),
            ...$this->healthRow(2, 90.4, 'gl_health_q11_smoker'),
            ...$this->healthRow(2, 94.2, 'gl_health_q12_alcohol'),
            ...$this->healthRow(2, 101.9, 'gl_health_q13_advised_stop'),
            ...$this->healthRow(2, 109.5, 'gl_health_q14_current_medication'),
            // Item 15's second sub-question ("Any complications with pregnancy?") has
            // no corresponding wizard field -- only "Are you pregnant?" is collected --
            // so that row is intentionally left blank rather than fabricated.
            ...$this->healthRow(2, 117.6, 'gl_health_q15_pregnancy'),
            ...$this->healthRow(2, 128.4, 'gl_health_q16_relative_pep'),
            ...$this->healthRow(2, 135.9, 'gl_health_q17_pending_reinstatement'),
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_Y_X, 'y' => 143.3, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_glapi') === true,
            ],
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_N_X, 'y' => 143.3, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_glapi') === false,
            ],
            ['page' => 2, 'x' => 57.7, 'y' => 143.3, 'size' => 8, 'width' => 65, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'health_glapi.gl_health_q17_with_glapi_amount'],
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_Y_X, 'y' => 146.7, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_other_companies') === true,
            ],
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_N_X, 'y' => 146.7, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_other_companies') === false,
            ],
            ['page' => 2, 'x' => 57.7, 'y' => 146.7, 'size' => 8, 'width' => 65, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'health_glapi.gl_health_q17_with_other_companies_amount'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function signatureFields(): array
    {
        return [
            // "OF WITNESS" printed name (ANNABELLE M. AMORA) is baked into the source
            // template artwork itself, not rendered here. "OF PROPOSED INSURED
            // INDIVIDUAL" is the borrower's own hand signature -- left blank, same as
            // AU's notarial hand-fill blanks.
            ['page' => 2, 'x' => 11.5, 'y' => 247.8, 'size' => 9, 'width' => 75, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'notarial.signing_place'],
            ['page' => 2, 'x' => 108.4, 'y' => 247.8, 'size' => 9, 'width' => 90, 'value' => 'loan.approved_date'],
        ];
    }

    /**
     * A single Y/N/"details of yes answer" question row. $section defaults to
     * 'health_glapi' (the GLAPI-only questionnaire); item 3 passes 'health' to reuse
     * the plain health-declarations boolean instead.
     *
     * @return list<array<string, mixed>>
     */
    private function healthRow(int $page, float $y, string $field, string $section = 'health_glapi'): array
    {
        $detailField = $field === 'health_hypertension' ? 'health_hypertension_details' : "{$field}_details";
        $path = "{$section}.{$field}";
        $detailPath = "health_glapi.{$detailField}";

        return [
            [
                'page' => $page, 'type' => 'check', 'x' => self::HEALTH_Y_X, 'y' => $y, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, $path) === true,
            ],
            [
                'page' => $page, 'type' => 'check', 'x' => self::HEALTH_N_X, 'y' => $y, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, $path) === false,
            ],
            [
                'page' => $page, 'x' => self::HEALTH_DETAIL_X, 'y' => $y, 'size' => 8,
                'width' => self::HEALTH_DETAIL_WIDTH, 'shrink_to_fit' => true, 'min_size' => 6.0,
                'value' => static fn (array $d) => data_get($d, $detailPath),
            ],
        ];
    }
}
