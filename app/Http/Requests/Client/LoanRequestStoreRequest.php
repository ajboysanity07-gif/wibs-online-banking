<?php

namespace App\Http\Requests\Client;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LoanRequestStoreRequest extends FormRequest
{
    private const HOUSING_STATUS_OPTIONS = ['OWNED', 'RENT'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->adminProfile === null;
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
            ...$this->personRules('applicant', true, true),
            ...$this->personRules('co_maker_1', false, false),
            ...$this->personRules('co_maker_2', false, false),
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
    ): array {
        $rules = [
            "{$prefix}.first_name" => ['required', 'string', 'max:255'],
            "{$prefix}.last_name" => ['required', 'string', 'max:255'],
            "{$prefix}.middle_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.nickname" => ['nullable', 'string', 'max:255'],
            "{$prefix}.birthdate" => ['required', 'date'],
            "{$prefix}.birthplace" => ['required', 'string', 'max:255'],
            "{$prefix}.address" => ['required', 'string', 'max:500'],
            "{$prefix}.length_of_stay" => ['required', 'string', 'max:255'],
            "{$prefix}.housing_status" => [
                'required',
                'string',
                Rule::in(self::HOUSING_STATUS_OPTIONS),
            ],
            "{$prefix}.cell_no" => ['required', 'string', 'max:20'],
            "{$prefix}.civil_status" => ['required', 'string', 'max:255'],
            "{$prefix}.educational_attainment" => ['required', 'string', 'max:255'],
            "{$prefix}.employment_type" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_name" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_address" => ['required', 'string', 'max:500'],
            "{$prefix}.telephone_no" => ['nullable', 'string', 'max:20'],
            "{$prefix}.current_position" => ['required', 'string', 'max:255'],
            "{$prefix}.nature_of_business" => ['required', 'string', 'max:255'],
            "{$prefix}.years_in_work_business" => ['required', 'string', 'max:255'],
            "{$prefix}.gross_monthly_income" => ['required', 'numeric', 'min:0'],
            "{$prefix}.payday" => ['required', 'string', 'max:255'],
        ];

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
            $rules["{$prefix}.spouse_cell_no"] = ['nullable', 'string', 'max:20'];
        }

        return $rules;
    }
}
