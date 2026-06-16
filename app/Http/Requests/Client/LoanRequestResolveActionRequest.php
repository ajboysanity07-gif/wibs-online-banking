<?php

namespace App\Http\Requests\Client;

use App\Models\AppUser;
use App\Models\LoanRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestResolveActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $loanRequest = $this->resolvedLoanRequest();

        return $user instanceof AppUser
            && $loanRequest instanceof LoanRequest
            && $user->can('respondToMemberAction', $loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['sometimes', 'string', Rule::in(['accept', 'decline'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'insurance' => ['sometimes', 'array:beneficiary_primary_name,beneficiary_primary_relationship,beneficiary_secondary_name,beneficiary_secondary_relationship'],
            'insurance.beneficiary_primary_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_primary_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'insurance.beneficiary_secondary_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'health' => ['sometimes', 'array:health_smoker,health_hypertension,health_diabetes,health_recent_hospitalization,health_declaration_notes'],
            'health.health_smoker' => ['sometimes', 'boolean'],
            'health.health_hypertension' => ['sometimes', 'boolean'],
            'health.health_diabetes' => ['sometimes', 'boolean'],
            'health.health_recent_hospitalization' => ['sometimes', 'boolean'],
            'health.health_declaration_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'authorization' => ['sometimes', 'array:authorized_recipient_name,authorized_recipient_relationship,authorized_recipient_contact'],
            'authorization.authorized_recipient_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'authorization.authorized_recipient_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'authorization.authorized_recipient_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking' => ['sometimes', 'array:payout_bank_name,payout_account_name,payout_account_number,payout_account_type,payout_atm_number'],
            'banking.payout_bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_account_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'banking.payout_atm_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay' => ['sometimes', 'array:barangay_name,barangay_clearance_reference'],
            'barangay.barangay_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barangay.barangay_clearance_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations' => ['sometimes', 'array:declaration_existing_loans,declaration_pending_cases,declaration_truth_confirmation,declaration_data_privacy_consent'],
            'declarations.declaration_existing_loans' => ['sometimes', 'boolean'],
            'declarations.declaration_pending_cases' => ['sometimes', 'boolean'],
            'declarations.declaration_truth_confirmation' => ['sometimes', 'boolean'],
            'declarations.declaration_data_privacy_consent' => ['sometimes', 'boolean'],
        ];
    }

    private function resolvedLoanRequest(): ?LoanRequest
    {
        $loanRequest = $this->route('loanRequest');

        if ($loanRequest instanceof LoanRequest) {
            return $loanRequest;
        }

        if (! is_numeric($loanRequest)) {
            return null;
        }

        return LoanRequest::query()->find((int) $loanRequest);
    }
}
