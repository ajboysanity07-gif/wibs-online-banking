<?php

namespace App\Http\Requests\Client;

use App\Services\Payments\PaymongoServiceFeeCalculator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymongoLoanPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasMemberAccess();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supportedMethods = app(PaymongoServiceFeeCalculator::class)
            ->supportedMethods();

        return [
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'payment_method' => [
                'required',
                'string',
                Rule::in($supportedMethods),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Enter the loan payment amount.',
            'amount.numeric' => 'Loan payment amount must be a number.',
            'amount.min' => 'Loan payment amount must be at least PHP 1.00.',
            'amount.regex' => 'Loan payment amount may only include up to two decimal places.',
            'payment_method.required' => 'Choose a payment method.',
            'payment_method.in' => 'Choose a supported PayMongo payment method.',
        ];
    }
}
