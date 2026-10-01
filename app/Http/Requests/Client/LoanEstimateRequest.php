<?php

namespace App\Http\Requests\Client;

use App\LoanPaydayOption;
use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && ! $user->isAdminOnly()
            && $user->hasMemberAccess();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'term' => ['required', 'integer', 'min:1', 'max:'.config('loan_workflow.estimate.max_term_months')],
            'typecode' => ['nullable', 'string', 'max:255'],
            'payment_frequency' => ['nullable', 'string', Rule::in(LoanPaydayOption::values())],
            // Per-mille age-banded rate the staff panel would default to;
            // computed client-side from the shared loan-charge defaults.
            'insurance_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
