<?php

namespace App\Http\Requests\Client;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LoanRequestDraftRequest extends FormRequest
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
            "{$prefix}.birthplace" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.address" => ['sometimes', 'nullable', 'string', 'max:500'],
            "{$prefix}.length_of_stay" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.housing_status" => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::HOUSING_STATUS_OPTIONS),
            ],
            "{$prefix}.cell_no" => ['sometimes', 'nullable', 'string', 'max:20'],
            "{$prefix}.civil_status" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.educational_attainment" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employment_type" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_name" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.employer_business_address" => ['sometimes', 'nullable', 'string', 'max:500'],
            "{$prefix}.telephone_no" => ['sometimes', 'nullable', 'string', 'max:20'],
            "{$prefix}.current_position" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.nature_of_business" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.years_in_work_business" => ['sometimes', 'nullable', 'string', 'max:255'],
            "{$prefix}.gross_monthly_income" => ['sometimes', 'nullable', 'numeric', 'min:0'],
            "{$prefix}.payday" => ['sometimes', 'nullable', 'string', 'max:255'],
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
}
