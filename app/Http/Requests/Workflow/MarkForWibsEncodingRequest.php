<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use Illuminate\Foundation\Http\FormRequest;

class MarkForWibsEncodingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser && $user->can('markForWibsEncoding', $this->loanRequest);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
