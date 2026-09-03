<?php

namespace App\Http\Requests\Client;

use App\Concerns\ResolvesPsgcFields;
use App\LoanCivilStatus;
use App\LoanInstitutionalEmployerCategory;
use App\LoanPaydayOption;
use App\LoanPaymentOption;
use App\LoanReleaseMethod;
use App\LoanSex;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Wlntype;
use App\Rules\ValidPostalCode;
use App\Rules\ValidPsgcBarangay;
use App\Rules\ValidPsgcLocality;
use App\Rules\ValidPsgcProvince;
use App\Services\LoanRequests\InstitutionalEmployerCategoryResolver;
use App\Support\DisplayText;
use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class LoanRequestStoreRequest extends FormRequest
{
    use ResolvesPsgcFields;

    private const HOUSING_STATUS_OPTIONS = ['OWNED', 'RENT'];

    /**
     * wlntype.typecode for "Other Loan" -- the only loan type eligible for a
     * member-requested Lumpsum payment frequency.
     */
    private const OTHER_LOAN_TYPECODE = '01';

    /**
     * wlntype.lntype label match for "Micro Business Loan" -- the only loan
     * type that collects a Regular/Emergency kind_of_loan.
     */
    private const MICRO_BUSINESS_LOAN_LABEL = 'MICRO BUSINESS LOAN';

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
     * extended x3. Names/birthdates/cycle numbers are never required on
     * submit -- see LoanRequestDataService. Cycle status is conditionally
     * required -- see cycleStatusRequiredRule().
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
     * True when the member requested Due date repayment over a single month --
     * the only combination that skips the insurance/health wizard steps.
     */
    private function isDueDateNoInsuranceRequested(): bool
    {
        return $this->input('requested_payment_frequency') === LoanPaydayOption::DueDate->value
            && (int) $this->input('requested_term') === 1;
    }

    /**
     * Emergency (Micro Business Loan) requests also skip the insurance/health
     * wizard steps -- there is no insurance premium to underwrite.
     */
    private function isEmergencyLoanRequested(): bool
    {
        return $this->input('kind_of_loan') === 'Emergency';
    }

    /**
     * Checks the submitted typecode against wlntype's "Other Loan" row,
     * falling back to a label match in case typecode differs across
     * environments (wlntype is external WIBS-desktop-managed data).
     */
    private function isOtherLoanType(): bool
    {
        $typecode = $this->input('typecode');

        if ($typecode === null) {
            return false;
        }

        if ((string) $typecode === self::OTHER_LOAN_TYPECODE) {
            return true;
        }

        if (! Schema::hasTable('wlntype')
            || ! Schema::hasColumn('wlntype', 'typecode')
            || ! Schema::hasColumn('wlntype', 'lntype')) {
            return false;
        }

        $label = Wlntype::query()->where('typecode', $typecode)->value('lntype');

        return $label !== null && strtoupper(trim((string) $label)) === 'OTHER LOAN';
    }

    /**
     * Checks the submitted typecode against wlntype's "Micro Business Loan"
     * row by label -- there is no fixed typecode for it like OTHER_LOAN_TYPECODE.
     */
    private function isMicroBusinessLoanType(): bool
    {
        $typecode = $this->input('typecode');

        if ($typecode === null
            || ! Schema::hasTable('wlntype')
            || ! Schema::hasColumn('wlntype', 'typecode')
        ) {
            return false;
        }

        $label = Wlntype::query()->where('typecode', $typecode)->value('lntype');

        return $label !== null && strtoupper(trim((string) $label)) === self::MICRO_BUSINESS_LOAN_LABEL;
    }

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
                $rules["dependents.{$key}"] = [
                    'nullable',
                    'string',
                    Rule::in(['New', 'Old']),
                    ...$this->cycleStatusRequiredRule($key),
                ];

                continue;
            }

            if (in_array($key, self::DEPENDENT_CYCLE_NUMBER_KEYS, true)) {
                $statusKey = str_replace('_cycle_number', '_cycle_status', $key);

                // Cycle number is auto-computed server-side by
                // LoanRequestCycleComputeService and locked, so client
                // submissions treat it as optional (the backend always
                // overwrites).
                $rules["dependents.{$key}"] = [
                    'nullable',
                    'integer',
                    'min:1',
                ];

                continue;
            }

            $rules["dependents.{$key}"] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Cycle status becomes required once the thing it describes is actually
     * on the request: a dependent slot with a name filled in, or a spouse
     * when married. Left optional otherwise so an empty/inapplicable slot
     * doesn't block submission. The applicant's cycle is auto-computed
     * server-side (LoanRequestCycleStateService) and never submitted by the
     * wizard, so it carries no required rule here.
     *
     * @return array<int, ValidationRule|string>
     */
    private function cycleStatusRequiredRule(string $key): array
    {
        if ($key === 'applicant_cycle_status') {
            return [];
        }

        if ($key === 'dependent_spouse_cycle_status') {
            return [Rule::requiredIf($this->input('applicant.civil_status') === 'Married')];
        }

        $nameKey = str_replace('_cycle_status', '_name', $key);

        return [Rule::requiredIf(filled($this->input("dependents.{$nameKey}")))];
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

    /**
     * Free-text person fields users routinely type in ALL CAPS. Normalized to
     * title case on save; enum/dropdown-backed fields (civil_status,
     * housing_status, employment_type) and PSGC location fields are excluded
     * since they're already constrained to fixed values.
     */
    private const NORMALIZED_PERSON_TEXT_FIELDS = [
        'first_name',
        'middle_name',
        'last_name',
        'nickname',
        'spouse_name',
        'employer_business_name',
        'current_position',
        'nature_of_business',
        'address1',
        'employer_business_address1',
    ];

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        foreach (['applicant', 'co_maker_1', 'co_maker_2'] as $key) {
            $person = $this->input($key);

            if (! is_array($person)) {
                continue;
            }

            $person = $this->normalizePersonLocationFields($person);
            $payload[$key] = DisplayText::normalizeFields(
                $person,
                self::NORMALIZED_PERSON_TEXT_FIELDS,
            );
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

        $insuranceRequired = $this->isDueDateNoInsuranceRequested() || $this->isEmergencyLoanRequested()
            ? 'sometimes'
            : 'required';

        return [
            'typecode' => $loanTypeRules,
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'requested_term' => ['required', 'integer', 'min:1', 'max:360'],
            'loan_purpose' => ['required', 'string', 'max:255'],
            'other_loan_type_name' => [
                Rule::requiredIf(fn () => $this->isOtherLoanType()),
                'nullable',
                'string',
                'max:255',
            ],
            'availment_status' => [
                'required',
                'string',
                Rule::in(['New', 'Re-Loan', 'Restructured']),
            ],
            'undertaking_accepted' => ['accepted'],
            'requested_payment_frequency' => [
                'nullable',
                'string',
                Rule::in(LoanPaydayOption::values()),
            ],
            'kind_of_loan' => [
                Rule::requiredIf(fn () => $this->isMicroBusinessLoanType()),
                'nullable',
                'string',
                Rule::in(['Regular', 'Emergency']),
            ],
            'insurance' => [$insuranceRequired, 'array:beneficiary_primary_name,beneficiary_primary_relationship,beneficiary_primary_birthdate,beneficiary_secondary_name,beneficiary_secondary_relationship,beneficiary_secondary_birthdate'],
            'insurance.beneficiary_primary_name' => [$insuranceRequired, 'string', 'max:255'],
            'insurance.beneficiary_primary_relationship' => [$insuranceRequired, 'string', 'max:255'],
            'insurance.beneficiary_primary_birthdate' => [$insuranceRequired, 'date', 'before:today', 'after:1900-01-01'],
            'insurance.beneficiary_secondary_name' => ['nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_relationship' => ['nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_birthdate' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'health' => [$insuranceRequired, 'array:health_smoking_status,health_hypertension'],
            'health.health_smoking_status' => [$insuranceRequired, 'string', Rule::in(['none', 'light', 'heavy'])],
            'health.health_hypertension' => [$insuranceRequired, 'boolean'],
            ...$this->healthGlapiRules(),
            'banking' => ['required', 'array:release_method,release_saved_account_id,payment_option,payment_saved_account_id'],
            'banking.release_saved_account_id' => [
                Rule::requiredIf(fn () => in_array($this->input('banking.release_method'), [LoanReleaseMethod::Atm->value, LoanReleaseMethod::BankTransfer->value], true)),
                'nullable',
                'integer',
                Rule::exists('member_payment_accounts', 'id')->where(
                    fn ($query) => $query->where('member_application_profile_id', $this->user()?->memberApplicationProfile?->id),
                ),
            ],
            'banking.payment_saved_account_id' => [
                Rule::requiredIf(fn () => $this->input('banking.payment_option') === LoanPaymentOption::AtmDeduction->value),
                'nullable',
                'integer',
                Rule::exists('member_payment_accounts', 'id')->where(
                    fn ($query) => $query->where('member_application_profile_id', $this->user()?->memberApplicationProfile?->id),
                ),
            ],
            'banking.release_method' => ['required', 'string', 'max:255', Rule::in(array_column(LoanReleaseMethod::cases(), 'value'))],
            'banking.payment_option' => ['required', 'string', 'max:255', Rule::in(array_column(LoanPaymentOption::cases(), 'value'))],
            'declarations' => ['required', 'array:declaration_existing_loans,declaration_pending_cases,declaration_truth_confirmation,declaration_data_privacy_consent,existing_loan_1_date,existing_loan_1_type,existing_loan_1_amount,existing_loan_2_date,existing_loan_2_type,existing_loan_2_amount,existing_loan_3_date,existing_loan_3_type,existing_loan_3_amount'],
            'declarations.declaration_existing_loans' => ['required', 'boolean'],
            'declarations.declaration_pending_cases' => ['required', 'boolean'],
            'declarations.declaration_truth_confirmation' => ['accepted'],
            'declarations.declaration_data_privacy_consent' => ['accepted'],
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
            ...$this->personRules(
                'applicant',
                true,
                true,
                true,
                true,
                requirePsgcLocations: false,
            ),
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
    public function messages(): array
    {
        return [
            'undertaking_accepted.accepted' => 'Please confirm the undertaking.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('banking.payment_option') !== LoanPaymentOption::SalaryDeduction->value) {
                return;
            }

            $explicitCategory = LoanInstitutionalEmployerCategory::tryFrom(
                (string) $this->input('applicant.institutional_employer_category'),
            );

            $isInstitutionalPayroll = $explicitCategory instanceof LoanInstitutionalEmployerCategory
                ? $explicitCategory->isInstitutionalPayrollCategory()
                : InstitutionalEmployerCategoryResolver::resolve(
                    $this->input('applicant.employer_business_name'),
                    $this->input('applicant.employment_type'),
                    $this->input('applicant.nature_of_business'),
                ) !== null;

            if (! $isInstitutionalPayroll) {
                $validator->errors()->add(
                    'banking.payment_option',
                    'Salary Deduction is only available for BLGU, LGU, Healthcare, or MRDINC employees.',
                );
            }
        });

        $validator->after(function (Validator $validator): void {
            foreach (['applicant', 'co_maker_1', 'co_maker_2'] as $prefix) {
                $employmentType = $this->input("{$prefix}.employment_type");

                if (! MemberApplicationProfile::employmentTypeMatches(
                    $employmentType,
                    MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE,
                )) {
                    continue;
                }

                if (filled($this->input("{$prefix}.nature_of_business"))) {
                    continue;
                }

                $validator->errors()->add(
                    "{$prefix}.nature_of_business",
                    'Nature of business is required for self-employed applicants.',
                );
            }
        });
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
        bool $requirePsgcLocations = true,
    ): array {
        $employmentType = $this->input("{$prefix}.employment_type");
        $isPensioner = MemberApplicationProfile::employmentTypeMatches(
            $employmentType,
            MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
        );
        $isSelfEmployed = MemberApplicationProfile::employmentTypeMatches(
            $employmentType,
            MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE,
        );
        $employerRule = $isPensioner ? 'nullable' : 'required';
        $dateEmployedRule = ($isPensioner || $isSelfEmployed) ? 'nullable' : 'required';

        $rules = [
            "{$prefix}.first_name" => ['required', 'string', 'max:255'],
            "{$prefix}.last_name" => ['required', 'string', 'max:255'],
            "{$prefix}.middle_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.nickname" => ['nullable', 'string', 'max:255'],
            "{$prefix}.birthdate" => ['required', 'date', 'before:today', 'after:1900-01-01'],
            "{$prefix}.birthplace_city" => ['required', 'string', 'max:255'],
            "{$prefix}.birthplace_province" => ['required', 'string', 'max:255'],
            "{$prefix}.address1" => ['required', 'string', 'max:255'],
            "{$prefix}.address_barangay" => ['nullable', 'string', 'max:255'],
            "{$prefix}.address2" => ['required', 'string', 'max:255'],
            "{$prefix}.address3" => ['required', 'string', 'max:255'],
            "{$prefix}.address_zip" => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            // length_of_stay/educational_attainment/employment_type are not
            // tightened to integer/Rule::in -- real profile data (synced in
            // from settings) carries free-text and legacy values outside the
            // current frontend option sets; enforcing this needs a data
            // audit/backfill first (see ProfileUpdateRequest for the same
            // note).
            "{$prefix}.length_of_stay" => ['required', 'string', 'max:255'],
            "{$prefix}.cell_no" => ['required', 'string', 'regex:/^09\d{9}$/'],
            "{$prefix}.educational_attainment" => ['required', 'string', 'max:255'],
            "{$prefix}.employment_type" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_name" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.employer_business_address1" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.employer_business_address_barangay" => ['nullable', 'string', 'max:255'],
            // Never actually enforced as required prior to this comment (the
            // old rule combined $employerRule with 'nullable', which always
            // wins) -- kept intentionally nullable rather than newly
            // requiring it, since neither the wizard nor the correction
            // dialog UI ever marks employer city/province as required.
            "{$prefix}.employer_business_address2" => ['nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address3" => ['nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address_zip" => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            "{$prefix}.telephone_no" => ['nullable', 'string', 'max:20'],
            "{$prefix}.current_position" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.nature_of_business" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.institutional_employer_category" => ['nullable', new Enum(LoanInstitutionalEmployerCategory::class)],
            "{$prefix}.years_in_work_business" => [$employerRule, 'string', 'max:255'],
            "{$prefix}.gross_monthly_income" => ['required', 'numeric', 'min:1'],
            "{$prefix}.payday" => [
                'required',
                'string',
                Rule::in(LoanPaydayOption::values()),
            ],
        ];

        if ($requirePsgcLocations) {
            array_push(
                $rules["{$prefix}.birthplace_city"],
                new ValidPsgcLocality,
            );
            array_push(
                $rules["{$prefix}.birthplace_province"],
                new ValidPsgcProvince,
            );
            array_push(
                $rules["{$prefix}.address_barangay"],
                new ValidPsgcBarangay(
                    $this->input("{$prefix}.address2"),
                    $this->input("{$prefix}.address3"),
                ),
            );
            array_push(
                $rules["{$prefix}.address2"],
                new ValidPsgcLocality,
            );
            array_push(
                $rules["{$prefix}.address3"],
                new ValidPsgcProvince,
            );
            array_push(
                $rules["{$prefix}.employer_business_address_barangay"],
                new ValidPsgcBarangay(
                    $this->input("{$prefix}.employer_business_address2"),
                    $this->input("{$prefix}.employer_business_address3"),
                ),
            );
            array_push(
                $rules["{$prefix}.employer_business_address2"],
                new ValidPsgcLocality,
            );
            array_push(
                $rules["{$prefix}.employer_business_address3"],
                new ValidPsgcProvince,
            );
        }

        if ($includeDateEmployed) {
            $rules["{$prefix}.employer_date_employed"] = [$dateEmployedRule, 'date'];
        }

        if ($includeCivilHousing) {
            $rules["{$prefix}.housing_status"] = [
                'required',
                'string',
                Rule::in(self::HOUSING_STATUS_OPTIONS),
            ];
            $rules["{$prefix}.civil_status"] = [
                'required',
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
            $rules["{$prefix}.number_of_children"] = [
                'required',
                'integer',
                'min:0',
            ];
        }

        if ($includeSpouse) {
            $rules["{$prefix}.spouse_name"] = ['nullable', 'string', 'max:255'];
            $rules["{$prefix}.spouse_birthdate"] = ['nullable', 'date', 'before:today', 'after:1900-01-01'];
            $rules["{$prefix}.spouse_cell_no"] = ['nullable', 'string', 'regex:/^09\d{9}$/'];
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
        $person['address_barangay'] = $this->normalizeOptionalString(
            $person['address_barangay'] ?? null,
        );
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
        $person['employer_business_address_barangay'] = $this->normalizeOptionalString(
            $person['employer_business_address_barangay'] ?? null,
        );
        $person['employer_business_address2'] = $employerAddress2;
        $person['employer_business_address3'] = $employerAddress3;

        $person = $this->resolvePsgcPersonFields($person);

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
