<?php

namespace App\Http\Requests\Client;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDraftRequest extends FormRequest
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
            'wizard_step' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'insurance' => ['sometimes', 'nullable', 'array'],
            'insurance.beneficiary_primary_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_primary_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_primary_birthdate' => ['sometimes', 'nullable', 'date'],
            'insurance.beneficiary_secondary_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_birthdate' => ['sometimes', 'nullable', 'date'],
            'health' => ['sometimes', 'nullable', 'array'],
            'health.health_smoker' => ['sometimes', 'nullable', 'boolean'],
            'health.health_hypertension' => ['sometimes', 'nullable', 'boolean'],
            'health.health_diabetes' => ['sometimes', 'nullable', 'boolean'],
            'health.health_recent_hospitalization' => ['sometimes', 'nullable', 'boolean'],
            'health.health_declaration_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'banking' => ['sometimes', 'nullable', 'array'],
            'banking.payout_bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.release_method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_atm_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_bank_branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_atm_holder_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay' => ['sometimes', 'nullable', 'array'],
            'barangay.barangay_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay.barangay_clearance_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay.barangay_locality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay.barangay_official_designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay.barangay_agency_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay.barangay_agency_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations' => ['sometimes', 'nullable', 'array'],
            'declarations.declaration_existing_loans' => ['sometimes', 'nullable', 'boolean'],
            'declarations.declaration_pending_cases' => ['sometimes', 'nullable', 'boolean'],
            'declarations.declaration_truth_confirmation' => ['sometimes', 'nullable', 'boolean'],
            'declarations.declaration_data_privacy_consent' => ['sometimes', 'nullable', 'boolean'],
            ...$this->personRules('applicant', true, true, true),
            ...$this->personRules('co_maker_1', false, false, false),
            ...$this->personRules('co_maker_2', false, false, false),
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
            "{$prefix}.address2" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.address3" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.length_of_stay" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.cell_no" => ['sometimes', 'nullable', 'string', 'max:20'],
            "{$prefix}.educational_attainment" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employment_type" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_name" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address1" => ['sometimes', 'nullable', 'string', 'max:255'],
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
                Rule::in(self::PAYDAY_OPTIONS),
            ],
        ];

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
                Rule::in(self::CIVIL_STATUS_OPTIONS),
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
