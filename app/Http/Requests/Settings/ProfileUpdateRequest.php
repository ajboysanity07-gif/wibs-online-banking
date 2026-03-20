<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\MemberApplicationProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

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
            'birthplace' => [
                $memberRequirement('birthplace'),
                'string',
                'max:255',
            ],
            'length_of_stay' => [
                $memberRequirement('length_of_stay'),
                'string',
                'max:100',
            ],
            'housing_status' => [
                $memberRequirement('housing_status'),
                'string',
                'max:100',
            ],
            'educational_attainment' => [
                $memberRequirement('educational_attainment'),
                'string',
                'max:150',
            ],
            'number_of_children' => [
                $memberRequirement('number_of_children'),
                'integer',
                'min:0',
                'max:30',
            ],
            'spouse_name' => [
                $memberRequirement('spouse_name'),
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
            'employer_business_address' => [
                $memberRequirement('employer_business_address'),
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
                'max:50',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthplace.required' => 'Birthplace is required to complete your profile.',
            'length_of_stay.required' => 'Length of stay is required to complete your profile.',
            'housing_status.required' => 'Housing status is required to complete your profile.',
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
            'employer_business_address' => 'employer or business address',
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
}
