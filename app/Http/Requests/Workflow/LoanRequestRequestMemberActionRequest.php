<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestRequestMemberActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('requestMemberAction', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action_type' => [
                'required',
                'string',
                Rule::in(['needs_revision', 'awaiting_member_information']),
            ],
            'message' => ['required', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:1000'],
            'field_keys' => ['sometimes', 'array'],
            'field_keys.*' => [
                'string',
                Rule::in([
                    'beneficiary_primary_name',
                    'beneficiary_primary_relationship',
                    'beneficiary_secondary_name',
                    'beneficiary_secondary_relationship',
                    'health_smoking_status',
                    'health_hypertension',
                    'health_recent_hospitalization',
                    'release_method',
                    'payout_bank_name',
                    'payout_account_name',
                    'payout_account_number',
                    'payout_account_type',
                    'payout_atm_number',
                    'declaration_existing_loans',
                    'declaration_pending_cases',
                    'declaration_truth_confirmation',
                    'declaration_data_privacy_consent',
                ]),
            ],
        ];
    }
}
