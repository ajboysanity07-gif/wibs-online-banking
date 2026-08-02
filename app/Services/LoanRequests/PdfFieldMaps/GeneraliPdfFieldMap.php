<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

class GeneraliPdfFieldMap implements ApprovedLoanPdfFieldMap
{
    /**
     * Shared Y/N checkbox + "details of yes answer" text column, identical
     * on both pages of the template (same table design repeats on page 2).
     */
    private const HEALTH_Y_X = 128.2;

    private const HEALTH_N_X = 135.2;

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
            ['page' => 1, 'type' => 'check', 'x' => 170.1, 'y' => 54.9, 'size' => 7, 'value' => static fn (): bool => true],

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
            ['page' => 1, 'type' => 'check', 'x' => 146.9, 'y' => 111.7, 'size' => 7, 'value' => static fn (array $d): bool => strtoupper((string) (data_get($d, 'applicant.sex') ?? '')) === 'MALE'],
            ['page' => 1, 'type' => 'check', 'x' => 146.9, 'y' => 116.1, 'size' => 7, 'value' => static fn (array $d): bool => strtoupper((string) (data_get($d, 'applicant.sex') ?? '')) === 'FEMALE'],
            ['page' => 1, 'x' => 165.9, 'y' => 115.0, 'size' => 8, 'width' => 12, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => static fn (array $d): ?string => self::withUnit(data_get($d, 'application_form.height_cm'), ' cm')],
            ['page' => 1, 'x' => 178.9, 'y' => 115.0, 'size' => 8, 'width' => 12, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => static fn (array $d): ?string => self::withUnit(data_get($d, 'application_form.weight_kg'), ' kg')],

            ['page' => 1, 'x' => 23.3, 'y' => 126.0, 'size' => 9, 'value' => 'applicant.nationality'],
            ['page' => 1, 'x' => 64.2, 'y' => 126.0, 'size' => 9, 'value' => 'applicant.nationality'],
            ['page' => 1, 'x' => 104.3, 'y' => 126.0, 'size' => 9, 'width' => 55, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'applicant.position_or_designation'],

            ['page' => 1, 'x' => 56.0, 'y' => 129.2, 'size' => 8, 'width' => 18, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'application_form.source_of_fund_wealth'],
            [
                'page' => 1, 'x' => 153.0, 'y' => 129.2, 'size' => 8, 'width' => 37,
                'shrink_to_fit' => true, 'min_size' => 6.0,
                'value' => static fn (array $d): ?string => self::composeGovernmentId($d),
            ],
            [
                'page' => 1, 'x' => 153.0, 'y' => 133.1, 'size' => 8, 'width' => 37,
                'shrink_to_fit' => true, 'min_size' => 6.0,
                'value' => static fn (array $d): ?string => data_get($d, 'application_form.id_type') === 'Others'
                    ? data_get($d, 'application_form.id_type_other')
                    : null,
            ],

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
            ...$this->healthRow(1, 207.45, 'gl_health_q01_weight_change'),
            ...$this->healthRowQ2Header(1, 216.64),
            ...$this->healthRow(1, 220.73, 'gl_health_q02a_neuro'),
            ...$this->healthRow(1, 225.46, 'gl_health_q02b_respiratory'),
            ...$this->healthRow(1, 229.48, 'gl_health_q02c_cardiac'),
            ...$this->healthRow(1, 234.56, 'gl_health_q02d_digestive'),
            ...$this->healthRow2e(1, 243.17),
            ...$this->healthRow(1, 247.55, 'gl_health_q02f_musculoskeletal'),
            ...$this->healthRow(1, 251.85, 'gl_health_q02g_oncology_blood'),
            ...$this->healthRow(1, 256.30, 'gl_health_q02h_dermatologic'),
            ...$this->healthRow(1, 264.55, 'gl_health_q02i_std_viral'),
            ...$this->healthRow(1, 269.22, 'gl_health_q02j_other_illness'),
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
            ...$this->healthRow(2, 33.58, 'health_hypertension', section: 'health'),
            ...$this->healthRow(2, 38.03, 'gl_health_q04_prescribed_drugs'),
            ...$this->healthRow(2, 52.41, 'gl_health_q05_confinement_5yr'),
            ...$this->healthRow(2, 62.44, 'gl_health_q06_abnormal_labs'),
            ...$this->healthRow(2, 66.82, 'gl_health_q07_confinement_contemplated'),
            ...$this->healthRow(2, 74.30, 'gl_health_q08_blood_transfusion'),
            ...$this->healthRow(2, 78.31, 'gl_health_q09_other_disease'),
            ...$this->healthRow(2, 85.72, 'gl_health_q10_narcotics'),
            ...$this->healthRowSmoker(2, 90.02),
            ...$this->healthRow(2, 97.50, 'gl_health_q12_alcohol'),
            ...$this->healthRow(2, 104.99, 'gl_health_q13_advised_stop'),
            ...$this->healthRow(2, 109.73, 'gl_health_q14_current_medication'),
            // Item 15 prints two sub-questions ("Are you pregnant?" / "Any
            // complications with pregnancy?") but only one Y/N checkbox pair, on the
            // "Any complications" line -- the wizard's single gl_health_q15_pregnancy
            // answer is printed there; its detail blank stays empty (not collected).
            ...$this->healthRow(2, 120.51, 'gl_health_q15_pregnancy'),
            ...$this->healthRow(2, 127.92, 'gl_health_q16_relative_pep'),
            ...$this->healthRow(2, 135.40, 'gl_health_q17_pending_reinstatement'),
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_Y_X, 'y' => 139.5, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_glapi') === true,
            ],
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_N_X, 'y' => 139.5, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_glapi') === false,
            ],
            ['page' => 2, 'x' => 64.0, 'y' => 138.2, 'size' => 8, 'width' => 65, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'health_glapi.gl_health_q17_with_glapi_amount'],
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_Y_X, 'y' => 143.8, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_other_companies') === true,
            ],
            [
                'page' => 2, 'type' => 'check', 'x' => self::HEALTH_N_X, 'y' => 143.8, 'size' => 7,
                'value' => static fn (array $d) => data_get($d, 'health_glapi.gl_health_q17_with_other_companies') === false,
            ],
            ['page' => 2, 'x' => 64.0, 'y' => 142.5, 'size' => 8, 'width' => 65, 'shrink_to_fit' => true, 'min_size' => 6.0, 'value' => 'health_glapi.gl_health_q17_with_other_companies_amount'],
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

    /**
     * Item 2e ("Diabetes/Kidney/Liver/Urinary condition") is one Y/N row on the
     * printed form, but the wizard now collects it as 4 independent checkboxes --
     * Y is checked if any of the four is true, N only once all four are answered
     * false, and it's left blank while any of the four is still unanswered.
     *
     * @return list<array<string, mixed>>
     */
    private function healthRow2e(int $page, float $y): array
    {
        return $this->healthRowCustom($page, $y, static function (array $d): ?bool {
            $answers = [
                data_get($d, 'health_glapi.gl_health_q02e_diabetes'),
                data_get($d, 'health_glapi.gl_health_q02e_kidney'),
                data_get($d, 'health_glapi.gl_health_q02e_liver'),
                data_get($d, 'health_glapi.gl_health_q02e_urinary'),
            ];

            if (in_array(true, $answers, true)) {
                return true;
            }

            return in_array(null, $answers, true) ? null : false;
        }, 'health_glapi.gl_health_q02e_diabetes_renal_details');
    }

    /**
     * Item 2's section header ("Have you ever suffered from...") carries its own
     * Y/N boxes with no detail line. The answer is derived across all ten
     * sub-questions (2a-2j, with 2e expanded into its four wizard checkboxes):
     * Y when any is Yes, N once every sub-question is answered No, blank while any
     * is still unanswered.
     *
     * @return list<array<string, mixed>>
     */
    private function healthRowQ2Header(int $page, float $y): array
    {
        return [
            [
                'page' => $page, 'type' => 'check', 'x' => self::HEALTH_Y_X, 'y' => $y, 'size' => 7,
                'value' => static fn (array $d): bool => self::resolveQ2Answer($d) === true,
            ],
            [
                'page' => $page, 'type' => 'check', 'x' => self::HEALTH_N_X, 'y' => $y, 'size' => 7,
                'value' => static fn (array $d): bool => self::resolveQ2Answer($d) === false,
            ],
        ];
    }

    private static function resolveQ2Answer(array $d): ?bool
    {
        $answers = [
            data_get($d, 'health_glapi.gl_health_q02a_neuro'),
            data_get($d, 'health_glapi.gl_health_q02b_respiratory'),
            data_get($d, 'health_glapi.gl_health_q02c_cardiac'),
            data_get($d, 'health_glapi.gl_health_q02d_digestive'),
            data_get($d, 'health_glapi.gl_health_q02e_diabetes'),
            data_get($d, 'health_glapi.gl_health_q02e_kidney'),
            data_get($d, 'health_glapi.gl_health_q02e_liver'),
            data_get($d, 'health_glapi.gl_health_q02e_urinary'),
            data_get($d, 'health_glapi.gl_health_q02f_musculoskeletal'),
            data_get($d, 'health_glapi.gl_health_q02g_oncology_blood'),
            data_get($d, 'health_glapi.gl_health_q02h_dermatologic'),
            data_get($d, 'health_glapi.gl_health_q02i_std_viral'),
            data_get($d, 'health_glapi.gl_health_q02j_other_illness'),
        ];

        if (in_array(true, $answers, true)) {
            return true;
        }

        return in_array(null, $answers, true) ? null : false;
    }

    private static function withUnit(mixed $value, string $unit): ?string
    {
        $value = $value === null ? null : trim((string) $value);

        return $value === null || $value === '' ? null : $value.$unit;
    }

    private static function composeGovernmentId(array $d): ?string
    {
        $idNumber = trim((string) (data_get($d, 'application_form.id_number') ?? ''));

        if ($idNumber === '') {
            return null;
        }

        $idType = trim((string) (data_get($d, 'application_form.id_type') ?? ''));

        if ($idType === 'Others') {
            $idType = trim((string) (data_get($d, 'application_form.id_type_other') ?? ''));
        }

        return trim($idType.' '.$idNumber);
    }

    /**
     * Item 11 ("Do you smoke more than 10 cigarettes a day?") is now derived from
     * the wizard's 3-value health_smoking_status field -- only 'heavy' answers Yes,
     * matching the original question's ">10/day" threshold exactly.
     *
     * @return list<array<string, mixed>>
     */
    private function healthRowSmoker(int $page, float $y): array
    {
        return $this->healthRowCustom($page, $y, static fn (array $d): ?bool => match (data_get($d, 'health.health_smoking_status')) {
            'heavy' => true,
            'none', 'light' => false,
            default => null,
        }, 'health_glapi.health_smoking_status_details');
    }

    /**
     * Shared Y/N/"details" row builder for questions whose Yes/No answer is derived
     * from something other than a single boolean field (tri-state: true/false/null).
     *
     * @return list<array<string, mixed>>
     */
    private function healthRowCustom(int $page, float $y, \Closure $resolveAnswer, string $detailPath): array
    {
        return [
            [
                'page' => $page, 'type' => 'check', 'x' => self::HEALTH_Y_X, 'y' => $y, 'size' => 7,
                'value' => static fn (array $d) => $resolveAnswer($d) === true,
            ],
            [
                'page' => $page, 'type' => 'check', 'x' => self::HEALTH_N_X, 'y' => $y, 'size' => 7,
                'value' => static fn (array $d) => $resolveAnswer($d) === false,
            ],
            [
                'page' => $page, 'x' => self::HEALTH_DETAIL_X, 'y' => $y, 'size' => 8,
                'width' => self::HEALTH_DETAIL_WIDTH, 'shrink_to_fit' => true, 'min_size' => 6.0,
                'value' => static fn (array $d) => data_get($d, $detailPath),
            ],
        ];
    }
}
