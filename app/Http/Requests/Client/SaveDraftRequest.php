<?php

namespace App\Http\Requests\Client;

use App\LoanCivilStatus;
use App\LoanPaydayOption;
use App\LoanPaymentOption;
use App\LoanReleaseMethod;
use App\LoanRequestStatus;
use App\LoanSex;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDraftRequest extends FormRequest
{
    private const HOUSING_STATUS_OPTIONS = ['OWNED', 'RENT'];

    /**
     * GLAPI (Generali) 17-item health questionnaire field keys. See
     * LoanRequestDraftRequest for the rationale (mirrors that request).
     *
     * @var list<string>
     */
    private const HEALTH_GLAPI_KEYS = [
        'gl_health_q01_weight_change',
        'gl_health_q01_weight_change_details',
        'gl_health_q02a_neuro',
        'gl_health_q02a_neuro_details',
        'gl_health_q02b_respiratory',
        'gl_health_q02b_respiratory_details',
        'gl_health_q02c_cardiac',
        'gl_health_q02c_cardiac_details',
        'gl_health_q02d_digestive',
        'gl_health_q02d_digestive_details',
        'gl_health_q02e_diabetes',
        'gl_health_q02e_kidney',
        'gl_health_q02e_liver',
        'gl_health_q02e_urinary',
        'gl_health_q02e_diabetes_renal_details',
        'gl_health_q02f_musculoskeletal',
        'gl_health_q02f_musculoskeletal_details',
        'gl_health_q02g_oncology_blood',
        'gl_health_q02g_oncology_blood_details',
        'gl_health_q02h_dermatologic',
        'gl_health_q02h_dermatologic_details',
        'gl_health_q02i_std_viral',
        'gl_health_q02i_std_viral_details',
        'gl_health_q02j_other_illness',
        'gl_health_q02j_other_illness_details',
        'health_hypertension_details',
        'gl_health_q04_prescribed_drugs',
        'gl_health_q04_prescribed_drugs_details',
        'gl_health_q05_confinement_5yr',
        'gl_health_q05_confinement_5yr_details',
        'gl_health_q06_abnormal_labs',
        'gl_health_q06_abnormal_labs_details',
        'gl_health_q07_confinement_contemplated',
        'gl_health_q07_confinement_contemplated_details',
        'gl_health_q08_blood_transfusion',
        'gl_health_q08_blood_transfusion_details',
        'gl_health_q09_other_disease',
        'gl_health_q09_other_disease_details',
        'gl_health_q10_narcotics',
        'gl_health_q10_narcotics_details',
        'health_smoking_status_details',
        'gl_health_q12_alcohol',
        'gl_health_q12_alcohol_details',
        'gl_health_q13_advised_stop',
        'gl_health_q13_advised_stop_details',
        'gl_health_q14_current_medication',
        'gl_health_q14_current_medication_details',
        'gl_health_q15_pregnancy',
        'gl_health_q15_pregnancy_details',
        'gl_health_q16_relative_pep',
        'gl_health_q16_relative_pep_details',
        'gl_health_q17_pending_reinstatement',
        'gl_health_q17_pending_reinstatement_details',
        'gl_health_q17_with_glapi',
        'gl_health_q17_with_glapi_amount',
        'gl_health_q17_with_other_companies',
        'gl_health_q17_with_other_companies_amount',
        'health_recent_hospitalization',
        'applicant_pep_status',
        'applicant_pep_status_details',
    ];

    private const HEALTH_GLAPI_BOOLEAN_KEYS = [
        'gl_health_q01_weight_change',
        'gl_health_q02a_neuro',
        'gl_health_q02b_respiratory',
        'gl_health_q02c_cardiac',
        'gl_health_q02d_digestive',
        'gl_health_q02e_diabetes',
        'gl_health_q02e_kidney',
        'gl_health_q02e_liver',
        'gl_health_q02e_urinary',
        'gl_health_q02f_musculoskeletal',
        'gl_health_q02g_oncology_blood',
        'gl_health_q02h_dermatologic',
        'gl_health_q02i_std_viral',
        'gl_health_q02j_other_illness',
        'gl_health_q04_prescribed_drugs',
        'gl_health_q05_confinement_5yr',
        'gl_health_q06_abnormal_labs',
        'gl_health_q07_confinement_contemplated',
        'gl_health_q08_blood_transfusion',
        'gl_health_q09_other_disease',
        'gl_health_q10_narcotics',
        'gl_health_q12_alcohol',
        'gl_health_q13_advised_stop',
        'gl_health_q14_current_medication',
        'gl_health_q15_pregnancy',
        'gl_health_q16_relative_pep',
        'gl_health_q17_pending_reinstatement',
        'gl_health_q17_with_glapi',
        'gl_health_q17_with_other_companies',
        'health_recent_hospitalization',
        'applicant_pep_status',
    ];

    private const HEALTH_GLAPI_AMOUNT_KEYS = [
        'gl_health_q17_with_glapi_amount',
        'gl_health_q17_with_other_companies_amount',
    ];

    /**
     * Dependents (Form B) fixed slots: child x3, sibling x3, parent x2,
     * extended x3. Never required on submit -- see LoanRequestDataService.
     *
     * @var list<string>
     */
    private const DEPENDENT_KEYS = [
        'dependent_child_1_name',
        'dependent_child_1_birthdate',
        'dependent_child_1_cycle_status',
        'dependent_child_1_cycle_number',
        'dependent_child_2_name',
        'dependent_child_2_birthdate',
        'dependent_child_2_cycle_status',
        'dependent_child_2_cycle_number',
        'dependent_child_3_name',
        'dependent_child_3_birthdate',
        'dependent_child_3_cycle_status',
        'dependent_child_3_cycle_number',
        'dependent_sibling_1_name',
        'dependent_sibling_1_birthdate',
        'dependent_sibling_1_cycle_status',
        'dependent_sibling_1_cycle_number',
        'dependent_sibling_2_name',
        'dependent_sibling_2_birthdate',
        'dependent_sibling_2_cycle_status',
        'dependent_sibling_2_cycle_number',
        'dependent_sibling_3_name',
        'dependent_sibling_3_birthdate',
        'dependent_sibling_3_cycle_status',
        'dependent_sibling_3_cycle_number',
        'dependent_parent_1_name',
        'dependent_parent_1_birthdate',
        'dependent_parent_1_cycle_status',
        'dependent_parent_1_cycle_number',
        'dependent_parent_2_name',
        'dependent_parent_2_birthdate',
        'dependent_parent_2_cycle_status',
        'dependent_parent_2_cycle_number',
        'dependent_extended_1_name',
        'dependent_extended_1_birthdate',
        'dependent_extended_1_cycle_status',
        'dependent_extended_1_cycle_number',
        'dependent_extended_2_name',
        'dependent_extended_2_birthdate',
        'dependent_extended_2_cycle_status',
        'dependent_extended_2_cycle_number',
        'dependent_extended_3_name',
        'dependent_extended_3_birthdate',
        'dependent_extended_3_cycle_status',
        'dependent_extended_3_cycle_number',
        'dependent_spouse_cycle_status',
        'dependent_spouse_cycle_number',
        'applicant_cycle_status',
        'applicant_cycle_number',
    ];

    private const DEPENDENT_DATE_KEYS = [
        'dependent_child_1_birthdate',
        'dependent_child_2_birthdate',
        'dependent_child_3_birthdate',
        'dependent_sibling_1_birthdate',
        'dependent_sibling_2_birthdate',
        'dependent_sibling_3_birthdate',
        'dependent_parent_1_birthdate',
        'dependent_parent_2_birthdate',
        'dependent_extended_1_birthdate',
        'dependent_extended_2_birthdate',
        'dependent_extended_3_birthdate',
    ];

    private const DEPENDENT_CYCLE_STATUS_KEYS = [
        'dependent_child_1_cycle_status',
        'dependent_child_2_cycle_status',
        'dependent_child_3_cycle_status',
        'dependent_sibling_1_cycle_status',
        'dependent_sibling_2_cycle_status',
        'dependent_sibling_3_cycle_status',
        'dependent_parent_1_cycle_status',
        'dependent_parent_2_cycle_status',
        'dependent_extended_1_cycle_status',
        'dependent_extended_2_cycle_status',
        'dependent_extended_3_cycle_status',
        'dependent_spouse_cycle_status',
        'applicant_cycle_status',
    ];

    private const DEPENDENT_CYCLE_NUMBER_KEYS = [
        'dependent_child_1_cycle_number',
        'dependent_child_2_cycle_number',
        'dependent_child_3_cycle_number',
        'dependent_sibling_1_cycle_number',
        'dependent_sibling_2_cycle_number',
        'dependent_sibling_3_cycle_number',
        'dependent_parent_1_cycle_number',
        'dependent_parent_2_cycle_number',
        'dependent_extended_1_cycle_number',
        'dependent_extended_2_cycle_number',
        'dependent_extended_3_cycle_number',
        'dependent_spouse_cycle_number',
        'applicant_cycle_number',
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function dependentsRules(): array
    {
        $rules = [
            'dependents' => ['sometimes', 'array:'.implode(',', self::DEPENDENT_KEYS)],
        ];

        foreach (self::DEPENDENT_KEYS as $key) {
            if (in_array($key, self::DEPENDENT_DATE_KEYS, true)) {
                $rules["dependents.{$key}"] = ['sometimes', 'nullable', 'date'];

                continue;
            }

            if (in_array($key, self::DEPENDENT_CYCLE_STATUS_KEYS, true)) {
                $rules["dependents.{$key}"] = ['sometimes', 'nullable', 'string', Rule::in(['New', 'Old'])];

                continue;
            }

            if (in_array($key, self::DEPENDENT_CYCLE_NUMBER_KEYS, true)) {
                $statusKey = str_replace('_cycle_number', '_cycle_status', $key);

                // No 'sometimes' here: combined with a same-field required_if,
                // 'sometimes' would skip validation entirely whenever the
                // key is absent from the payload, silently bypassing the
                // Old-requires-a-cycle-number rule.
                $rules["dependents.{$key}"] = [
                    'nullable',
                    'integer',
                    'min:1',
                    Rule::requiredIf($this->input("dependents.{$statusKey}") === 'Old'),
                ];

                continue;
            }

            $rules["dependents.{$key}"] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function healthGlapiRules(): array
    {
        $rules = [
            'health_glapi' => ['sometimes', 'nullable', 'array'],
        ];

        foreach (self::HEALTH_GLAPI_KEYS as $key) {
            $rules["health_glapi.{$key}"] = match (true) {
                in_array($key, self::HEALTH_GLAPI_BOOLEAN_KEYS, true) => ['sometimes', 'nullable', 'boolean'],
                in_array($key, self::HEALTH_GLAPI_AMOUNT_KEYS, true) => ['sometimes', 'nullable', 'numeric', 'min:0'],
                default => ['sometimes', 'nullable', 'string', 'max:1000'],
            };
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        foreach (['applicant', 'co_maker_1', 'co_maker_2'] as $key) {
            $person = $this->input($key);

            if (! is_array($person)) {
                continue;
            }

            $payload[$key] = $this->normalizePersonLocationFields($person);
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof AppUser) {
            return false;
        }

        /** @var LoanRequest|null $loanRequest */
        $loanRequest = $this->route('loanRequest');

        if (! $loanRequest instanceof LoanRequest) {
            return false;
        }

        if ((int) $loanRequest->user_id !== (int) $user->user_id) {
            return false;
        }

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return $status === LoanRequestStatus::Draft->value;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'typecode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'requested_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'requested_term' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:360'],
            'loan_purpose' => ['sometimes', 'nullable', 'string', 'max:255'],
            'availment_status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(['New', 'Re-Loan', 'Restructured']),
            ],
            'wizard_step' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:23'],
            'insurance' => ['sometimes', 'nullable', 'array'],
            'insurance.beneficiary_primary_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_primary_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_primary_birthdate' => ['sometimes', 'nullable', 'date'],
            'insurance.beneficiary_secondary_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_birthdate' => ['sometimes', 'nullable', 'date'],
            'health' => ['sometimes', 'nullable', 'array'],
            'health.health_smoking_status' => ['sometimes', 'nullable', 'string', Rule::in(['none', 'light', 'heavy'])],
            'health.health_hypertension' => ['sometimes', 'nullable', 'boolean'],
            ...$this->healthGlapiRules(),
            'banking' => ['sometimes', 'nullable', 'array'],
            'banking.payout_bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.release_method' => ['sometimes', 'nullable', 'string', 'max:255', Rule::in(array_column(LoanReleaseMethod::cases(), 'value'))],
            'banking.payment_option' => ['sometimes', 'nullable', 'string', 'max:255', Rule::in(array_column(LoanPaymentOption::cases(), 'value'))],
            'banking.payout_atm_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_bank_branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_atm_holder_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations' => ['sometimes', 'nullable', 'array'],
            'declarations.declaration_existing_loans' => ['sometimes', 'nullable', 'boolean'],
            'declarations.declaration_pending_cases' => ['sometimes', 'nullable', 'boolean'],
            'declarations.declaration_truth_confirmation' => ['sometimes', 'nullable', 'boolean'],
            'declarations.declaration_data_privacy_consent' => ['sometimes', 'nullable', 'boolean'],
            'declarations.existing_loan_1_date' => ['sometimes', 'nullable', 'date'],
            'declarations.existing_loan_1_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations.existing_loan_1_amount' => ['sometimes', 'nullable', 'numeric'],
            'declarations.existing_loan_2_date' => ['sometimes', 'nullable', 'date'],
            'declarations.existing_loan_2_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations.existing_loan_2_amount' => ['sometimes', 'nullable', 'numeric'],
            'declarations.existing_loan_3_date' => ['sometimes', 'nullable', 'date'],
            'declarations.existing_loan_3_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations.existing_loan_3_amount' => ['sometimes', 'nullable', 'numeric'],
            ...$this->dependentsRules(),
            ...$this->personRules('applicant', true, true, true, true),
            ...$this->personRules('co_maker_1', false, false, false),
            ...$this->personRules('co_maker_2', false, false, false),
            ...$this->savedCoMakerRules('co_maker_1'),
            ...$this->savedCoMakerRules('co_maker_2'),
        ];
    }

    /**
     * Optional "save this co-maker for reuse" fields -- see
     * SavedCoMakersService. Never required; the borrower opts in explicitly.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function savedCoMakerRules(string $prefix): array
    {
        return [
            "{$prefix}.save_for_reuse" => ['sometimes', 'boolean'],
            "{$prefix}.saved_co_maker_id" => ['sometimes', 'nullable', 'integer'],
            "{$prefix}.saved_co_maker_label" => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function personRules(
        string $prefix,
        bool $includeSpouse,
        bool $includeChildren,
        bool $includeCivilHousing = false,
        bool $includeDateEmployed = false,
    ): array {
        $rules = [
            "{$prefix}" => ['sometimes', 'nullable', 'array'],
            "{$prefix}.first_name" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.last_name" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.middle_name" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.nickname" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.birthdate" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.birthplace_city" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.birthplace_province" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.address1" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.address_barangay" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.address2" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.address3" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.length_of_stay" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.cell_no" => ['sometimes', 'nullable', 'string', 'max:20'],
            "{$prefix}.educational_attainment" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employment_type" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_name" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address1" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address_barangay" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address2" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address3" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.telephone_no" => ['sometimes', 'nullable', 'string', 'max:20'],
            "{$prefix}.current_position" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.nature_of_business" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.years_in_work_business" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.gross_monthly_income" => ['sometimes', 'nullable', 'numeric', 'min:0'],
            "{$prefix}.payday" => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(LoanPaydayOption::values()),
            ],
        ];

        if ($includeDateEmployed) {
            $rules["{$prefix}.employer_date_employed"] = ['sometimes', 'nullable', 'date'];
        }

        if ($includeCivilHousing) {
            $rules["{$prefix}.housing_status"] = [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::HOUSING_STATUS_OPTIONS),
            ];
            $rules["{$prefix}.civil_status"] = [
                'sometimes',
                'nullable',
                'string',
                Rule::in(LoanCivilStatus::values()),
            ];
            $rules["{$prefix}.sex"] = [
                'sometimes',
                'nullable',
                'string',
                Rule::in(LoanSex::values()),
            ];
        }

        if ($includeChildren) {
            $rules["{$prefix}.number_of_children"] = ['sometimes', 'nullable', 'integer', 'min:0'];
        }

        if ($includeSpouse) {
            $rules["{$prefix}.spouse_name"] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules["{$prefix}.spouse_age"] = ['sometimes', 'nullable', 'integer', 'min:18', 'max:120'];
            $rules["{$prefix}.spouse_cell_no"] = ['sometimes', 'nullable', 'string', 'max:20'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function normalizePersonLocationFields(array $person): array
    {
        $birthplaceCity = $this->normalizeOptionalString($person['birthplace_city'] ?? null);
        $birthplaceProvince = $this->normalizeOptionalString($person['birthplace_province'] ?? null);
        $legacyBirthplace = $this->normalizeOptionalString($person['birthplace'] ?? null);

        if ($birthplaceCity === null && $birthplaceProvince === null && $legacyBirthplace !== null) {
            $parsed = LocationComposer::parseLegacyBirthplace($legacyBirthplace);
            $birthplaceCity = $parsed['city'];
            $birthplaceProvince = $parsed['province'];
        }

        $person['birthplace_city'] = $birthplaceCity;
        $person['birthplace_province'] = $birthplaceProvince;

        $address1 = $this->normalizeOptionalString($person['address1'] ?? null);
        $address2 = $this->normalizeOptionalString($person['address2'] ?? null);
        $address3 = $this->normalizeOptionalString($person['address3'] ?? null);
        $legacyAddress = $this->normalizeOptionalString($person['address'] ?? null);

        if ($address1 === null && $address2 === null && $address3 === null && $legacyAddress !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacyAddress);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $person['address1'] = $address1;
        $person['address_barangay'] = $this->normalizeOptionalString($person['address_barangay'] ?? null);
        $person['address2'] = $address2;
        $person['address3'] = $address3;

        $employerAddress1 = $this->normalizeOptionalString($person['employer_business_address1'] ?? null);
        $employerAddress2 = $this->normalizeOptionalString($person['employer_business_address2'] ?? null);
        $employerAddress3 = $this->normalizeOptionalString($person['employer_business_address3'] ?? null);
        $legacyEmployerAddress = $this->normalizeOptionalString($person['employer_business_address'] ?? null);

        if ($employerAddress1 === null && $employerAddress2 === null && $employerAddress3 === null && $legacyEmployerAddress !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacyEmployerAddress);
            $employerAddress1 = $parsed['address1'];
            $employerAddress2 = $parsed['address2'];
            $employerAddress3 = $parsed['address3'];
        }

        $person['employer_business_address1'] = $employerAddress1;
        $person['employer_business_address_barangay'] = $this->normalizeOptionalString($person['employer_business_address_barangay'] ?? null);
        $person['employer_business_address2'] = $employerAddress2;
        $person['employer_business_address3'] = $employerAddress3;

        return $person;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
