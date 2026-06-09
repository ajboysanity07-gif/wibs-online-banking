<?php

namespace App\Http\Requests\Admin;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoanRequestDeclineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('decline', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
