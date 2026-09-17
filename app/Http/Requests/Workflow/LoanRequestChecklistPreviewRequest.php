<?php

namespace App\Http\Requests\Workflow;

use App\LoanInstitutionalEmployerCategory;
use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class LoanRequestChecklistPreviewRequest extends FormRequest
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
            'institutional_employer_category' => ['sometimes', 'nullable', new Enum(LoanInstitutionalEmployerCategory::class)],
        ];
    }
}
