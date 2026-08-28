<?php

namespace App\Http\Requests\Client;

use App\LoanPaymentOption;
use App\LoanReleaseMethod;
use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLoanRequestPaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && ! $user->isAdminOnly()
            && $user->hasMemberAccess();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $releaseNeedsAccount = fn (): bool => in_array($this->input('release_method'), [
            LoanReleaseMethod::Atm->value,
            LoanReleaseMethod::BankTransfer->value,
        ], true);

        $paymentNeedsAccount = fn (): bool => $this->input('payment_option') === LoanPaymentOption::AtmDeduction->value;

        return [
            'release_method' => ['nullable', 'string', Rule::in(array_column(LoanReleaseMethod::cases(), 'value'))],
            'release_saved_account_id' => [Rule::requiredIf($releaseNeedsAccount), 'nullable', 'integer'],
            'payment_option' => ['nullable', 'string', Rule::in(array_column(LoanPaymentOption::cases(), 'value'))],
            'payment_saved_account_id' => [Rule::requiredIf($paymentNeedsAccount), 'nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('release_method') === null && $this->input('payment_option') === null) {
                $validator->errors()->add('release_method', 'Select a release method, a repayment method, or both.');
            }
        });
    }
}
