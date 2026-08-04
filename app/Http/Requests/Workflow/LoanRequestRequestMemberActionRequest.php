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
                    'beneficiary_primary_birthdate',
                    'beneficiary_secondary_name',
                    'beneficiary_secondary_relationship',
                    'beneficiary_secondary_birthdate',
                    'health_smoking_status',
                    'health_hypertension',
                    'health_recent_hospitalization',
                    'release_method',
                    'payment_option',
                    'payout_bank_name',
                    'payout_account_name',
                    'payout_account_number',
                    'payout_account_type',
                    'payout_atm_number',
                    'release_uses_payout_account',
                    'release_bank_name',
                    'release_account_name',
                    'release_account_number',
                    'release_account_type',
                    'declaration_existing_loans',
                    'declaration_pending_cases',
                    'declaration_truth_confirmation',
                    'declaration_data_privacy_consent',
                    'applicant_pep_status',
                    'applicant_pep_status_details',
                    'applicant_cycle_status',
                    'applicant_cycle_number',
                    'dependent_spouse_cycle_status',
                    'dependent_spouse_cycle_number',
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
                ]),
            ],
        ];
    }
}
