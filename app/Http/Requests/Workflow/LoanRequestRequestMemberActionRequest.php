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
                    'release_method',
                    'release_saved_account_id',
                    'payment_option',
                    'payment_saved_account_id',
                    'declaration_existing_loans',
                    'declaration_pending_cases',
                    'declaration_truth_confirmation',
                    'declaration_data_privacy_consent',
                    'applicant_pep_status',
                    'applicant_pep_status_details',
                    'dependent_child_1_name',
                    'dependent_child_1_birthdate',
                    'dependent_child_2_name',
                    'dependent_child_2_birthdate',
                    'dependent_child_3_name',
                    'dependent_child_3_birthdate',
                    'dependent_sibling_1_name',
                    'dependent_sibling_1_birthdate',
                    'dependent_sibling_2_name',
                    'dependent_sibling_2_birthdate',
                    'dependent_sibling_3_name',
                    'dependent_sibling_3_birthdate',
                    'dependent_parent_1_name',
                    'dependent_parent_1_birthdate',
                    'dependent_parent_2_name',
                    'dependent_parent_2_birthdate',
                    'dependent_extended_1_name',
                    'dependent_extended_1_birthdate',
                    'dependent_extended_2_name',
                    'dependent_extended_2_birthdate',
                    'dependent_extended_3_name',
                    'dependent_extended_3_birthdate',
                ]),
            ],
        ];
    }
}
