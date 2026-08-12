<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\LoanPaydayOption;
use App\LoanReleaseMethod;
use App\Models\MemberApplicationProfile;
use App\Models\MemberDependentProfile;
use App\Rules\ValidPostalCode;
use App\Rules\ValidPsgcBarangay;
use App\Rules\ValidPsgcLocality;
use App\Rules\ValidPsgcProvince;
use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

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
        $birthplaceBarangay = $this->normalizeOptionalString(
            $this->input('birthplace_barangay'),
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
                'birthplace_barangay' => $birthplaceBarangay,
                'birthplace' => LocationComposer::composeBirthplace(
                    $birthplaceCity,
                    $birthplaceProvince,
                ),
            ]);
        }

        $employerAddress1 = $this->normalizeOptionalString(
            $this->input('employer_business_address1'),
        );
        $employerAddressBarangay = $this->normalizeOptionalString(
            $this->input('employer_business_address_barangay'),
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
                'employer_business_address_barangay' => $employerAddressBarangay,
                'employer_business_address2' => $employerAddress2,
                'employer_business_address3' => $employerAddress3,
                'employer_business_address' => LocationComposer::compose(
                    $employerAddress1,
                    $employerAddress2,
                    $employerAddress3,
                    $employerAddressBarangay,
                ),
            ]);
        }

        $homeAddress1 = $this->normalizeOptionalString(
            $this->input('home_address1'),
        );
        $homeAddressBarangay = $this->normalizeOptionalString(
            $this->input('home_address_barangay'),
        );
        $homeAddress2 = $this->normalizeOptionalString(
            $this->input('home_address2'),
        );
        $homeAddress3 = $this->normalizeOptionalString(
            $this->input('home_address3'),
        );
        $legacyHomeAddress = $this->normalizeOptionalString(
            $this->input('home_address'),
        );

        if (
            $homeAddress1 === null
            && $homeAddress2 === null
            && $homeAddress3 === null
            && $legacyHomeAddress !== null
        ) {
            $parsed = LocationComposer::parseLegacyAddress($legacyHomeAddress);
            $homeAddress1 = $parsed['address1'];
            $homeAddress2 = $parsed['address2'];
            $homeAddress3 = $parsed['address3'];
        }

        if ($homeAddress1 !== null || $homeAddress2 !== null || $homeAddress3 !== null) {
            $this->merge([
                'home_address1' => $homeAddress1,
                'home_address_barangay' => $homeAddressBarangay,
                'home_address2' => $homeAddress2,
                'home_address3' => $homeAddress3,
                'home_address' => LocationComposer::compose(
                    $homeAddress1,
                    $homeAddress2,
                    $homeAddress3,
                    $homeAddressBarangay,
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
        $skipMemberRequirements = $this->user()?->isAdminOnly() ?? false;
        $memberRequirement = fn (string $field): string => $this->memberProfileRequirement($field, $skipMemberRequirements);

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
                new ValidPsgcLocality,
            ],
            'birthplace_province' => [
                $memberRequirement('birthplace_province'),
                'string',
                'max:255',
                new ValidPsgcProvince,
            ],
            'birthplace_barangay' => [
                'nullable',
                'string',
                'max:255',
                new ValidPsgcBarangay($this->input('birthplace_city'), $this->input('birthplace_province')),
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
            'civil_status' => [
                $memberRequirement('civil_status'),
                Rule::in(['Single', 'Married', 'Separated', 'Widowed']),
            ],
            'housing_status' => [
                $memberRequirement('housing_status'),
                Rule::in(['OWNED', 'RENT']),
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
            'spouse_birthdate' => [
                $memberRequirement('spouse_birthdate'),
                'date',
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
            'employer_business_address_barangay' => [
                'nullable',
                'string',
                'max:255',
                new ValidPsgcBarangay($this->input('employer_business_address2'), $this->input('employer_business_address3')),
            ],
            'employer_business_address2' => [
                $memberRequirement('employer_business_address2'),
                'nullable',
                'string',
                'max:255',
                new ValidPsgcLocality,
            ],
            'employer_business_address3' => [
                $memberRequirement('employer_business_address3'),
                'nullable',
                'string',
                'max:255',
                new ValidPsgcProvince,
            ],
            'employer_business_address_zip' => [
                'nullable',
                'string',
                'max:20',
                new ValidPostalCode,
            ],
            'employer_business_address' => [
                'nullable',
                'string',
                'max:500',
            ],
            'home_address1' => [
                $memberRequirement('home_address1'),
                'string',
                'max:255',
            ],
            'home_address_barangay' => [
                'nullable',
                'string',
                'max:255',
                new ValidPsgcBarangay($this->input('home_address2'), $this->input('home_address3')),
            ],
            'home_address2' => [
                $memberRequirement('home_address2'),
                'nullable',
                'string',
                'max:255',
                new ValidPsgcLocality,
            ],
            'home_address3' => [
                $memberRequirement('home_address3'),
                'nullable',
                'string',
                'max:255',
                new ValidPsgcProvince,
            ],
            'home_address_zip' => [
                'nullable',
                'string',
                'max:20',
                new ValidPostalCode,
            ],
            'home_address' => [
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
                Rule::in(LoanPaydayOption::values()),
            ],
            'payout_bank_name' => [
                Rule::requiredIf(fn () => $this->input('release_method') === LoanReleaseMethod::BankTransfer->value),
                'nullable',
                'string',
                'max:255',
            ],
            'payout_account_name' => [
                Rule::requiredIf(fn () => $this->input('release_method') === LoanReleaseMethod::BankTransfer->value),
                'nullable',
                'string',
                'max:255',
            ],
            'payout_account_number' => [
                Rule::requiredIf(fn () => $this->input('release_method') === LoanReleaseMethod::BankTransfer->value),
                'nullable',
                'string',
                'max:255',
            ],
            'payout_account_type' => [
                Rule::requiredIf(fn () => $this->input('release_method') === LoanReleaseMethod::BankTransfer->value),
                'nullable',
                'string',
                'max:255',
            ],
            'release_method' => [
                $memberRequirement('release_method'),
                'string',
                'max:255',
                Rule::in(array_column(LoanReleaseMethod::cases(), 'value')),
            ],
            'payout_atm_number' => [
                Rule::requiredIf(fn () => $this->input('release_method') === LoanReleaseMethod::Atm->value),
                'nullable',
                'string',
                'max:255',
            ],
            'payout_bank_branch' => [
                'nullable',
                'string',
                'max:255',
            ],
            'payout_atm_holder_name' => [
                Rule::requiredIf(fn () => $this->input('release_method') === LoanReleaseMethod::Atm->value),
                'nullable',
                'string',
                'max:255',
            ],
            'source_of_fund_wealth' => [
                'nullable',
                'string',
                'max:255',
            ],
            'id_type' => [
                'nullable',
                'string',
                Rule::in(MemberApplicationProfile::ID_TYPE_OPTIONS),
            ],
            'id_type_other' => [
                Rule::requiredIf($this->input('id_type') === 'Others'),
                'nullable',
                'string',
                'max:255',
            ],
            'id_number' => [
                'nullable',
                'string',
                'max:100',
            ],
            'height_cm' => [
                'nullable',
                'string',
                'max:255',
            ],
            'weight_kg' => [
                'nullable',
                'string',
                'max:255',
            ],
            ...$this->dependentFieldRules(),
        ];
    }

    /**
     * Validation rules for the Settings > Dependents tab's fixed-slot
     * fields (dependent_{category}_{slot}_{attribute}). All optional --
     * dependents are never required to complete a member profile.
     *
     * Cycle status/number are intentionally excluded: they only make sense
     * at loan-application time (which insurance cycle applies to this
     * request), not as a standing profile fact captured at signup. The
     * loan-request wizard collects and persists them through its own
     * FormRequest/submission path, untouched by this one.
     *
     * @return array<string, array<int, string>>
     */
    private function dependentFieldRules(): array
    {
        $rules = [];

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                $prefix = "dependent_{$category}_{$slot}_";

                $rules[$prefix.'name'] = ['nullable', 'string', 'max:255'];
                $rules[$prefix.'birthdate'] = ['nullable', 'date'];
            }
        }

        return $rules;
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
            'employer_business_address1.required' => 'Employer or business address is required to complete your profile.',
            'home_address1.required' => 'Home address is required to complete your profile.',
            'home_address2.required' => 'Home address city or municipality is required to complete your profile.',
            'home_address3.required' => 'Home address province is required to complete your profile.',
            'current_position.required' => 'Current position is required to complete your profile.',
            'gross_monthly_income.required' => 'Gross monthly income is required to complete your profile.',
            'payday.required' => 'Payday is required to complete your profile.',
            'civil_status.required' => 'Civil status is required to complete your profile.',
            'housing_status.required' => 'Housing status is required to complete your profile.',
            'spouse_name.required' => 'Spouse name is required to complete your profile.',
            'spouse_birthdate.required' => 'Spouse birthdate is required to complete your profile.',
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
            'spouse_birthdate' => 'spouse birthdate',
            'civil_status' => 'civil status',
            'housing_status' => 'housing status',
            'gross_monthly_income' => 'gross monthly income',
            'birthplace_city' => 'birthplace city',
            'birthplace_province' => 'birthplace province',
            'birthplace_barangay' => 'birthplace barangay',
            'employer_business_address1' => 'employer or business address',
            'home_address1' => 'home address',
            'home_address_barangay' => 'home address barangay',
            'home_address2' => 'home address city or municipality',
            'home_address3' => 'home address province',
            'home_address_zip' => 'home address ZIP code',
            'years_in_work_business' => 'years in work or business',
        ];
    }

    private function memberProfileRequirement(string $field, bool $skipMemberRequirements): string
    {
        if ($skipMemberRequirements) {
            return 'nullable';
        }

        $isPensioner = trim((string) $this->input('employment_type', '')) === MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE;

        if ($isPensioner && in_array($field, MemberApplicationProfile::pensionerOptionalFields(), true)) {
            return 'nullable';
        }

        // civil_status/housing_status are disabled (and so never submitted)
        // once the core-banking record already has a value for them.
        if ($field === 'civil_status' && $this->wmasterFieldHasValue('civilstat')) {
            return 'nullable';
        }

        if ($field === 'housing_status' && $this->wmasterFieldHasValue('restype')) {
            return 'nullable';
        }

        // spouse_name is disabled (and so never submitted) once the
        // core-banking record already has a value for it -- mirrors the
        // civil_status/housing_status carve-outs above.
        if ($field === 'spouse_name' && $this->wmasterFieldHasValue('spouse')) {
            return 'nullable';
        }

        if (
            in_array($field, MemberApplicationProfile::spouseFieldsOptionalWhenSingle(), true)
            && $this->effectiveCivilStatusIsSingle()
        ) {
            return 'nullable';
        }

        return in_array($field, MemberApplicationProfile::completionRequiredFields(), true)
            ? 'required'
            : 'nullable';
    }

    private function wmasterFieldHasValue(string $wmasterColumn): bool
    {
        $wmaster = $this->user()?->wmaster;

        return $wmaster !== null && trim((string) $wmaster->{$wmasterColumn}) !== '';
    }

    /**
     * The spouse section is hidden (and its fields never submitted) once
     * civil status resolves to Single -- either from wmaster, when locked,
     * or from the submitted input, when self-reported.
     */
    private function effectiveCivilStatusIsSingle(): bool
    {
        $value = $this->wmasterFieldHasValue('civilstat')
            ? $this->user()?->wmaster?->civilstat
            : $this->input('civil_status');

        return strtoupper(trim((string) $value)) === 'SINGLE';
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
