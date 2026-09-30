<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use App\Models\LoanRequestCondition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestConditionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('verifyConditions', $this->loanRequest);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['condition' => $this->route('condition')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'condition' => ['required', Rule::in(array_keys(LoanRequestCondition::LABELS))],
            'verified' => ['required', 'boolean'],
        ];
    }
}
