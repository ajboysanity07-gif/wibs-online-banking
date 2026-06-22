<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use Illuminate\Foundation\Http\FormRequest;

class RecordWibsReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser && $user->can('recordWibsReference', $this->loanRequest);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'wibs_loan_reference' => ['required', 'string', 'max:100'],
        ];
    }
}
