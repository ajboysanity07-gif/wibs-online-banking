<?php

namespace App\Http\Requests\Workflow;

use App\LoanRequestDocumentKey;
use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestGenerateDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('generateDocuments', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_key' => ['sometimes', 'string', Rule::in(LoanRequestDocumentKey::values())],
        ];
    }
}
