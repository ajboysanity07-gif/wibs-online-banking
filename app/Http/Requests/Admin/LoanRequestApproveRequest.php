<?php

namespace App\Http\Requests\Admin;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoanRequestApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('approve', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approved_amount' => ['required', 'numeric', 'min:1'],
            'approved_term' => ['required', 'integer', 'min:1'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
