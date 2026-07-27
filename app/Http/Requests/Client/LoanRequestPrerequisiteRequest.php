<?php

namespace App\Http\Requests\Client;

use App\LoanReleaseMethod;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestPrerequisiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $user->can('create', LoanRequest::class);
    }

    /**
     * Validation rules for the Bank & Payout + Source of Fund / Government ID
     * loan prerequisite checkpoint (entry-point modal and submit-time safety
     * net). Mirrors ProfileUpdateRequest's rules for the same fields, but
     * these are required here -- they're only optional at onboarding.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payout_bank_name' => ['required', 'string', 'max:255'],
            'payout_account_name' => ['required', 'string', 'max:255'],
            'payout_account_number' => ['required', 'string', 'max:255'],
            'payout_account_type' => ['required', 'string', 'max:255'],
            'release_method' => ['required', 'string', 'max:255', Rule::in(array_column(LoanReleaseMethod::cases(), 'value'))],
            'payout_atm_number' => ['nullable', 'string', 'max:255'],
            'payout_bank_branch' => ['nullable', 'string', 'max:255'],
            'payout_atm_holder_name' => ['nullable', 'string', 'max:255'],
            'source_of_fund_wealth' => ['required', 'string', 'max:255'],
            'id_type' => ['required', 'string', Rule::in(MemberApplicationProfile::ID_TYPE_OPTIONS)],
            'id_type_other' => [
                Rule::requiredIf($this->input('id_type') === 'Others'),
                'nullable',
                'string',
                'max:255',
            ],
            'id_number' => ['required', 'string', 'max:100'],
        ];
    }
}
