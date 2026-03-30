<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\MemberApplicationProfile;
use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

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
        $natureOfBusiness = trim((string) $this->input('nature_of_business', ''));
        $natureOfBusinessOther = trim((string) $this->input('nature_of_business_other', ''));

        if (($natureOfBusiness === '' || $natureOfBusiness === 'Other') && $natureOfBusinessOther !== '') {
            $this->merge([
                'nature_of_business' => $natureOfBusinessOther,
            ]);
        }

        $birthplaceCity = $this->normalizeOptionalString(
            $this->input('birthplace_city'),
        );
        $birthplaceProvince = $this->normalizeOptionalString(
            $this->input('birthplace_province'),
        );
        $legacyBirthplace = $this->normalizeOptionalString(
            $this->input('birthplace'),
        );

        if ($birthplaceCity === null && $birthplaceProvince === null && $legacyBirthplace !== null) {
            $parsed = LocationComposer::parseLegacyBirthplace($legacyBirthplace);
            $birthplaceCity = $parsed['city'];
            $birthplaceProvince = $parsed['province'];
        }

        if ($birthplaceCity !== null || $birthplaceProvince !== null) {
            $this->merge([
                'birthplace_city' => $birthplaceCity,
                'birthplace_province' => $birthplaceProvince,
                'birthplace' => LocationComposer::composeBirthplace(
                    $birthplaceCity,
                    $birthplaceProvince,
                ),
            ]);
        }

        $employerAddress1 = $this->normalizeOptionalString(
            $this->input('employer_business_address1'),
        );
        $employerAddress2 = $this->normalizeOptionalString(
            $this->input('employer_business_address2'),
        );
        $employerAddress3 = $this->normalizeOptionalString(
            $this->input('employer_business_address3'),
        );
        $legacyEmployerAddress = $this->normalizeOptionalString(
            $this->input('employer_business_address'),
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

        if ($employerAddress1 !== null || $employerAddress2 !== null || $employerAddress3 !== null) {
            $this->merge([
                'employer_business_address1' => $employerAddress1,
                'employer_business_address2' => $employerAddress2,
                'employer_business_address3' => $employerAddress3,
                'employer_business_address' => LocationComposer::compose(
                    $employerAddress1,
                    $employerAddress2,
                    $employerAddress3,
                ),
            ]);
        }

        $grossMonthlyIncome = $this->input('gross_monthly_income');

        if (is_string($grossMonthlyIncome)) {
            $normalizedIncome = preg_replace('/[^0-9.]/', '', $grossMonthlyIncome) ?? '';
            $normalizedIncome = trim($normalizedIncome);

            $this->merge([
                'gross_monthly_income' => $normalizedIncome !== '' ? $normalizedIncome : null,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isAdmin = $this->user()?->adminProfile !== null;
        $memberRequirement = fn (string $field): string => $this->memberProfileRequirement($field, $isAdmin);

        return [
            ...$this->profileRules($this->user()->id),
            'fullname' => [
                Rule::requiredIf($isAdmin),
                'string',
                'max:255',
            ],
            'profile_photo' => [
                'nullable',
                'image',
                'max:2048',
                'mimes:jpg,jpeg,png,webp',
            ],
            'nickname' => [
                $memberRequirement('nickname'),
                'string',
                'max:100',
            ],
            'birthplace_city' => [
                $memberRequirement('birthplace_city'),
                'string',
                'max:255',
            ],
            'birthplace_province' => [
                $memberRequirement('birthplace_province'),
                'string',
                'max:255',
            ],
            'birthplace' => [
                'nullable',
                'string',
                'max:255',
            ],
            'length_of_stay' => [
                $memberRequirement('length_of_stay'),
                'string',
                'max:100',
            ],
            'number_of_children' => [
                'nullable',
                'integer',
                'min:0',
                'max:255',
            ],
            'spouse_name' => [
                $memberRequirement('spouse_name'),
                'string',
                'max:255',
            ],
            'educational_attainment' => [
                $memberRequirement('educational_attainment'),
                'string',
                'max:150',
            ],
            'spouse_age' => [
                $memberRequirement('spouse_age'),
                'integer',
                'min:0',
                'max:120',
            ],
            'spouse_cell_no' => [
                $memberRequirement('spouse_cell_no'),
                'digits:11',
            ],
            'employment_type' => [
                $memberRequirement('employment_type'),
                'string',
                'max:100',
            ],
            'employer_business_name' => [
                $memberRequirement('employer_business_name'),
                'string',
                'max:255',
            ],
            'employer_business_address1' => [
                $memberRequirement('employer_business_address1'),
                'string',
                'max:255',
            ],
            'employer_business_address2' => [
                $memberRequirement('employer_business_address2'),
                'string',
                'max:255',
            ],
            'employer_business_address3' => [
                $memberRequirement('employer_business_address3'),
                'string',
                'max:255',
            ],
            'employer_business_address' => [
                'nullable',
                'string',
                'max:500',
            ],
            'telephone_no' => [
                $memberRequirement('telephone_no'),
                'string',
                'max:30',
            ],
            'current_position' => [
                $memberRequirement('current_position'),
                'string',
                'max:150',
            ],
            'nature_of_business' => [
                $memberRequirement('nature_of_business'),
                'string',
                'max:255',
            ],
            'years_in_work_business' => [
                $memberRequirement('years_in_work_business'),
                'string',
                'max:50',
            ],
            'gross_monthly_income' => [
                $memberRequirement('gross_monthly_income'),
                'numeric',
                'min:0',
            ],
            'payday' => [
                $memberRequirement('payday'),
                'string',
                Rule::in(self::PAYDAY_OPTIONS),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthplace_city.required' => 'Birthplace city is required to complete your profile.',
            'length_of_stay.required' => 'Length of stay is required to complete your profile.',
            'educational_attainment.required' => 'Educational attainment is required to complete your profile.',
            'employment_type.required' => 'Employment type is required to complete your profile.',
            'employer_business_name.required' => 'Employer or business name is required to complete your profile.',
            'current_position.required' => 'Current position is required to complete your profile.',
            'gross_monthly_income.required' => 'Gross monthly income is required to complete your profile.',
            'payday.required' => 'Payday is required to complete your profile.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phoneno' => 'phone number',
            'spouse_cell_no' => 'spouse cell number',
            'gross_monthly_income' => 'gross monthly income',
            'birthplace_city' => 'birthplace city',
            'birthplace_province' => 'birthplace province',
            'employer_business_address1' => 'employer or business address',
            'years_in_work_business' => 'years in work or business',
        ];
    }

    private function memberProfileRequirement(string $field, bool $isAdmin): string
    {
        if ($isAdmin) {
            return 'nullable';
        }

        return in_array($field, MemberApplicationProfile::completionRequiredFields(), true)
            ? 'required'
            : 'nullable';
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
