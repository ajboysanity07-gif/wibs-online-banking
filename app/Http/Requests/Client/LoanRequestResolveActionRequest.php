<?php

namespace App\Http\Requests\Client;

use App\LoanPaymentOption;
use App\LoanReleaseMethod;
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
            'health' => ['sometimes', 'array:health_smoking_status,health_hypertension'],
            'health.health_smoking_status' => ['sometimes', 'string', Rule::in(['none', 'light', 'heavy'])],
            'health.health_hypertension' => ['sometimes', 'boolean'],
            'banking' => ['sometimes', 'array:release_method,release_saved_account_id,payment_option,payment_saved_account_id'],
            'banking.release_saved_account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('member_payment_accounts', 'id')->where(
                    fn ($query) => $query->where('member_application_profile_id', $this->user()?->memberApplicationProfile?->id),
                ),
            ],
            'banking.payment_saved_account_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('member_payment_accounts', 'id')->where(
                    fn ($query) => $query->where('member_application_profile_id', $this->user()?->memberApplicationProfile?->id),
                ),
            ],
            'banking.release_method' => ['sometimes', 'nullable', 'string', 'max:255', Rule::in(array_column(LoanReleaseMethod::cases(), 'value'))],
            'banking.payment_option' => ['sometimes', 'nullable', 'string', 'max:255', Rule::in(array_column(LoanPaymentOption::cases(), 'value'))],
            'declarations' => ['sometimes', 'array:declaration_existing_loans,declaration_pending_cases,declaration_truth_confirmation,declaration_data_privacy_consent,existing_loan_1_date,existing_loan_1_type,existing_loan_1_amount,existing_loan_2_date,existing_loan_2_type,existing_loan_2_amount,existing_loan_3_date,existing_loan_3_type,existing_loan_3_amount'],
            'declarations.declaration_existing_loans' => ['sometimes', 'boolean'],
            'declarations.declaration_pending_cases' => ['sometimes', 'boolean'],
            'declarations.declaration_truth_confirmation' => ['sometimes', 'boolean'],
            'declarations.declaration_data_privacy_consent' => ['sometimes', 'boolean'],
            'declarations.existing_loan_1_date' => ['sometimes', 'nullable', 'date'],
            'declarations.existing_loan_1_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations.existing_loan_1_amount' => ['sometimes', 'nullable', 'numeric'],
            'declarations.existing_loan_2_date' => ['sometimes', 'nullable', 'date'],
            'declarations.existing_loan_2_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations.existing_loan_2_amount' => ['sometimes', 'nullable', 'numeric'],
            'declarations.existing_loan_3_date' => ['sometimes', 'nullable', 'date'],
            'declarations.existing_loan_3_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'declarations.existing_loan_3_amount' => ['sometimes', 'nullable', 'numeric'],
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
