<?php

namespace App\Http\Requests\Workflow;

use App\LoanPaydayOption;
use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestRecommendationPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('updateProcessingDetails', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recommended_amount' => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'recommended_term' => [
                Rule::requiredIf(fn (): bool => $this->input('recommended_payment_frequency') === LoanPaydayOption::DueDate->value),
                'nullable', 'integer', 'min:1', 'max:360',
            ],
            'recommended_interest_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'recommended_payment_frequency' => ['sometimes', 'nullable', 'string', Rule::in(LoanPaydayOption::values())],
            'service_charge_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'insurance_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'insurance_term' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:360'],
            'loan_security_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'savings_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'documentary_stamp_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'notarial_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'other_charges_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'other_charges_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'penalty_rate_per_month' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
