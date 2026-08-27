<?php

namespace App\Http\Requests\Workflow;

use App\LoanPaymentOption;
use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestUpdatePayoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('updatePayoutDetails', $this->loanRequest);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_option' => [
                'required',
                'string',
                Rule::in(array_column(LoanPaymentOption::cases(), 'value')),
            ],
            'payout_atm_number' => [
                Rule::requiredIf(fn () => $this->input('payment_option') === LoanPaymentOption::AtmDeduction->value),
                'nullable',
                'string',
                'max:255',
            ],
            'payout_atm_holder_name' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
