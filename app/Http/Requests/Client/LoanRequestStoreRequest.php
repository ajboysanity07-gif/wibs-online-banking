<?php

namespace App\Http\Requests\Client;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LoanRequestStoreRequest extends FormRequest
{
    private const HOUSING_STATUS_OPTIONS = ['OWNED', 'RENT'];

    private const CIVIL_STATUS_OPTIONS = [
        'Single',
        'Married',
        'Separated',
        'Widowed',
    ];

    private const PAYDAY_OPTIONS = [
        'Weekly',
        '15th',
        '30th',
        '15th & 30th',
        'Bi-Weekly',
        'Monthly',
    ];

    private const SEX_OPTIONS = ['Male', 'Female'];

    /**
     * GLAPI (Generali) 17-item health questionnaire field keys. Not required
     * on submit yet -- see LoanRequestDraftRequest for the rationale (mirrors
     * that request).
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
        'gl_health_q02e_diabetes_renal',
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
        'gl_health_q11_smoker',
        'gl_health_q11_smoker_details',
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
    ];

    private const HEALTH_GLAPI_BOOLEAN_KEYS = [
        'gl_health_q01_weight_change',
        'gl_health_q02a_neuro',
        'gl_health_q02b_respiratory',
        'gl_health_q02c_cardiac',
        'gl_health_q02d_digestive',
        'gl_health_q02e_diabetes_renal',
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
        'gl_health_q11_smoker',
        'gl_health_q12_alcohol',
        'gl_health_q13_advised_stop',
        'gl_health_q14_current_medication',
        'gl_health_q15_pregnancy',
        'gl_health_q16_relative_pep',
        'gl_health_q17_pending_reinstatement',
        'gl_health_q17_with_glapi',
        'gl_health_q17_with_other_companies',
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
        'dependent_child_1_relationship',
        'dependent_child_1_birthdate',
        'dependent_child_1_occupation',
        'dependent_child_1_cycle_status',
        'dependent_child_2_name',
        'dependent_child_2_relationship',
        'dependent_child_2_birthdate',
        'dependent_child_2_occupation',
        'dependent_child_2_cycle_status',
        'dependent_child_3_name',
        'dependent_child_3_relationship',
        'dependent_child_3_birthdate',
        'dependent_child_3_occupation',
        'dependent_child_3_cycle_status',
        'dependent_sibling_1_name',
        'dependent_sibling_1_relationship',
        'dependent_sibling_1_birthdate',
        'dependent_sibling_1_occupation',
        'dependent_sibling_1_cycle_status',
        'dependent_sibling_2_name',
        'dependent_sibling_2_relationship',
        'dependent_sibling_2_birthdate',
        'dependent_sibling_2_occupation',
        'dependent_sibling_2_cycle_status',
        'dependent_sibling_3_name',
        'dependent_sibling_3_relationship',
        'dependent_sibling_3_birthdate',
        'dependent_sibling_3_occupation',
        'dependent_sibling_3_cycle_status',
        'dependent_parent_1_name',
        'dependent_parent_1_relationship',
        'dependent_parent_1_birthdate',
        'dependent_parent_1_occupation',
        'dependent_parent_1_cycle_status',
        'dependent_parent_2_name',
        'dependent_parent_2_relationship',
        'dependent_parent_2_birthdate',
        'dependent_parent_2_occupation',
        'dependent_parent_2_cycle_status',
        'dependent_extended_1_name',
        'dependent_extended_1_relationship',
        'dependent_extended_1_birthdate',
        'dependent_extended_1_occupation',
        'dependent_extended_1_cycle_status',
        'dependent_extended_2_name',
        'dependent_extended_2_relationship',
        'dependent_extended_2_birthdate',
        'dependent_extended_2_occupation',
        'dependent_extended_2_cycle_status',
        'dependent_extended_3_name',
        'dependent_extended_3_relationship',
        'dependent_extended_3_birthdate',
        'dependent_extended_3_occupation',
        'dependent_extended_3_cycle_status',
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

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function dependentsRules(): array
    {
        $rules = [
            'dependents' => ['sometimes', 'array:'.implode(',', self::DEPENDENT_KEYS)],
        ];

        foreach (self::DEPENDENT_KEYS as $key) {
            $rules["dependents.{$key}"] = in_array($key, self::DEPENDENT_DATE_KEYS, true)
                ? ['sometimes', 'nullable', 'date']
                : ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function healthGlapiRules(): array
    {
        $rules = [
            'health_glapi' => ['sometimes', 'array:'.implode(',', self::HEALTH_GLAPI_KEYS)],
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

        return $user instanceof AppUser
            && $user->can('create', LoanRequest::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $loanTypeRules = ['required', 'string', 'max:255'];

        if (Schema::hasTable('wlntype')) {
            if (Schema::hasColumn('wlntype', 'typecode')) {
                $loanTypeRules[] = Rule::exists('wlntype', 'typecode');
            } elseif (Schema::hasColumn('wlntype', 'lntype')) {
                $loanTypeRules[] = Rule::exists('wlntype', 'lntype');
            }
        }

        return [
            'typecode' => $loanTypeRules,
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'requested_term' => ['required', 'integer', 'min:1', 'max:360'],
            'loan_purpose' => ['required', 'string', 'max:255'],
            'availment_status' => [
                'required',
                'string',
                Rule::in(['New', 'Re-Loan', 'Restructured']),
            ],
            'undertaking_accepted' => ['accepted'],
            'insurance' => ['required', 'array:beneficiary_primary_name,beneficiary_primary_relationship,beneficiary_primary_birthdate,beneficiary_secondary_name,beneficiary_secondary_relationship,beneficiary_secondary_birthdate'],
            'insurance.beneficiary_primary_name' => ['required', 'string', 'max:255'],
            'insurance.beneficiary_primary_relationship' => ['required', 'string', 'max:255'],
            'insurance.beneficiary_primary_birthdate' => ['required', 'date'],
            'insurance.beneficiary_secondary_name' => ['nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_relationship' => ['nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_birthdate' => ['nullable', 'date'],
            'health' => ['required', 'array:health_smoker,health_hypertension,health_diabetes,health_recent_hospitalization,health_declaration_notes'],
            'health.health_smoker' => ['required', 'boolean'],
            'health.health_hypertension' => ['required', 'boolean'],
            'health.health_diabetes' => ['required', 'boolean'],
            'health.health_recent_hospitalization' => ['required', 'boolean'],
            'health.health_declaration_notes' => ['nullable', 'string', 'max:1000'],
            ...$this->healthGlapiRules(),
            'banking' => ['required', 'array:payout_bank_name,payout_account_name,payout_account_number,payout_account_type,release_method,payout_atm_number,payout_bank_branch,payout_atm_holder_name'],
            'banking.payout_bank_name' => ['required', 'string', 'max:255'],
            'banking.payout_account_name' => ['required', 'string', 'max:255'],
            'banking.payout_account_number' => ['required', 'string', 'max:255'],
            'banking.payout_account_type' => ['required', 'string', 'max:255'],
            'banking.release_method' => ['required', 'string', 'max:255'],
            'banking.payout_atm_number' => ['nullable', 'string', 'max:255'],
            'banking.payout_bank_branch' => ['nullable', 'string', 'max:255'],
            'banking.payout_atm_holder_name' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'array:barangay_official_designation,barangay_agency_name,barangay_agency_address'],
            'barangay.barangay_official_designation' => ['nullable', 'string', 'max:255'],
            'barangay.barangay_agency_name' => ['nullable', 'string', 'max:255'],
            'barangay.barangay_agency_address' => ['nullable', 'string', 'max:255'],
            'declarations' => ['required', 'array:declaration_existing_loans,declaration_pending_cases,declaration_truth_confirmation,declaration_data_privacy_consent'],
            'declarations.declaration_existing_loans' => ['required', 'boolean'],
            'declarations.declaration_pending_cases' => ['required', 'boolean'],
            'declarations.declaration_truth_confirmation' => ['accepted'],
            'declarations.declaration_data_privacy_consent' => ['accepted'],
            ...$this->dependentsRules(),
            ...$this->personRules('applicant', true, true, true),
            ...$this->personRules('co_maker_1', false, false, false),
            ...$this->personRules('co_maker_2', false, false, false),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function messages(): array
    {
        return [
            'undertaking_accepted.accepted' => 'Please confirm the undertaking.',
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
    ): array {
        $isPensioner = trim((string) $this->input("{$prefix}.employment_type", '')) === 'Pensioner';
        $employerRule = $isPensioner ? 'nullable' : 'required';

        $rules = [
            "{$prefix}.first_name" => ['required', 'string', 'max:255'],
            "{$prefix}.last_name" => ['required', 'string', 'max:255'],
            "{$prefix}.middle_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.nickname" => ['nullable', 'string', 'max:255'],
            "{$prefix}.birthdate" => ['required', 'date'],
            "{$prefix}.birthplace_city" => ['required', 'string', 'max:255'],
            "{$prefix}.birthplace_province" => ['required', 'string', 'max:255'],
            "{$prefix}.address1" => ['required', 'string', 'max:255'],
            "{$prefix}.address2" => ['required', 'string', 'max:255'],
            "{$prefix}.address3" => ['required', 'string', 'max:255'],
            "{$prefix}.length_of_stay" => ['required', 'string', 'max:255'],
            "{$prefix}.cell_no" => ['required', 'string', 'digits:11'],
            "{$prefix}.educational_attainment" => ['required', 'string', 'max:255'],
            "{$prefix}.employment_type" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_name" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.employer_business_address1" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.employer_business_address2" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.employer_business_address3" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.telephone_no" => ['nullable', 'string', 'max:20'],
            "{$prefix}.current_position" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.nature_of_business" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.years_in_work_business" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.gross_monthly_income" => ['required', 'numeric', 'min:0'],
            "{$prefix}.payday" => [
                'required',
                'string',
                Rule::in(self::PAYDAY_OPTIONS),
            ],
        ];

        if ($includeCivilHousing) {
            $rules["{$prefix}.housing_status"] = [
                'required',
                'string',
                Rule::in(self::HOUSING_STATUS_OPTIONS),
            ];
            $rules["{$prefix}.civil_status"] = [
                'required',
                'string',
                Rule::in(self::CIVIL_STATUS_OPTIONS),
            ];
            $rules["{$prefix}.sex"] = [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::SEX_OPTIONS),
            ];
        }

        if ($includeChildren) {
            $rules["{$prefix}.number_of_children"] = [
                'required',
                'integer',
                'min:0',
            ];
        }

        if ($includeSpouse) {
            $rules["{$prefix}.spouse_name"] = ['nullable', 'string', 'max:255'];
            $rules["{$prefix}.spouse_age"] = ['nullable', 'integer', 'min:18', 'max:120'];
            $rules["{$prefix}.spouse_cell_no"] = ['nullable', 'string', 'digits:11'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function normalizePersonLocationFields(array $person): array
    {
        $birthplaceCity = $this->normalizeOptionalString(
            $person['birthplace_city'] ?? null,
        );
        $birthplaceProvince = $this->normalizeOptionalString(
            $person['birthplace_province'] ?? null,
        );
        $legacyBirthplace = $this->normalizeOptionalString(
            $person['birthplace'] ?? null,
        );

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
        $person['address2'] = $address2;
        $person['address3'] = $address3;

        $employerAddress1 = $this->normalizeOptionalString(
            $person['employer_business_address1'] ?? null,
        );
        $employerAddress2 = $this->normalizeOptionalString(
            $person['employer_business_address2'] ?? null,
        );
        $employerAddress3 = $this->normalizeOptionalString(
            $person['employer_business_address3'] ?? null,
        );
        $legacyEmployerAddress = $this->normalizeOptionalString(
            $person['employer_business_address'] ?? null,
        );

        if (
            $employerAddress1 === null
            && $employerAddress2 === null
            && $employerAddress3 === null
            && $legacyEmployerAddress !== null
        ) {
            $parsed = LocationComposer::parseLegacyAddress($legacyEmployerAddress);
            $employerAddress1 = $parsed['address1'];
            $employerAddress2 = $parsed['address2'];
            $employerAddress3 = $parsed['address3'];
        }

        $person['employer_business_address1'] = $employerAddress1;
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
