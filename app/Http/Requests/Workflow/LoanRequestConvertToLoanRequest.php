<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use App\Models\LoanRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoanRequestConvertToLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest instanceof LoanRequest
            && $user->can('convertToLoan', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
