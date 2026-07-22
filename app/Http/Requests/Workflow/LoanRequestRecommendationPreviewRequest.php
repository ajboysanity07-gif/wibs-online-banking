<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'recommended_term' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:360'],
            'recommended_interest_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'recommended_payment_frequency' => ['sometimes', 'nullable', 'string', 'in:Weekly,15th,30th,15th & 30th,Bi-Weekly,Monthly'],
            'service_charge_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'insurance_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'insurance_term' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:360'],
            'loan_security_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'savings_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'documentary_stamp_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notarial_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'penalty_rate_per_month' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
