<?php

namespace App\Http\Requests\Client;

use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LoanRequestDraftRequest extends FormRequest
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

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasMemberAccess();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $loanTypeRules = ['sometimes', 'string', 'max:255'];

        if (Schema::hasTable('wlntype')) {
            if (Schema::hasColumn('wlntype', 'typecode')) {
                $loanTypeRules[] = Rule::exists('wlntype', 'typecode');
            } elseif (Schema::hasColumn('wlntype', 'lntype')) {
                $loanTypeRules[] = Rule::exists('wlntype', 'lntype');
            }
        }

        return [
            'typecode' => $loanTypeRules,
            'requested_amount' => ['sometimes', 'numeric', 'min:0'],
            'requested_term' => ['sometimes', 'integer', 'min:0', 'max:360'],
            'loan_purpose' => ['sometimes', 'string', 'max:255'],
            'availment_status' => [
                'sometimes',
                'string',
                Rule::in(['New', 'Re-Loan', 'Restructured']),
            ],
            'undertaking_accepted' => ['sometimes', 'boolean'],
            ...$this->personRules('applicant', true, true),
            ...$this->personRules('co_maker_1', false, false),
            ...$this->personRules('co_maker_2', false, false),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function personRules(
        string $prefix,
        bool $includeSpouse,
        bool $includeChildren,
    ): array {
        $rules = [
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
            "{$prefix}.housing_status" => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::HOUSING_STATUS_OPTIONS),
            ],
            "{$prefix}.cell_no" => ['sometimes', 'nullable', 'string', 'max:20'],
            "{$prefix}.civil_status" => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::CIVIL_STATUS_OPTIONS),
            ],
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

        if ($includeChildren) {
            $rules["{$prefix}.number_of_children"] = [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ];
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
