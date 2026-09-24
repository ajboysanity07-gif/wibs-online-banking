<?php

namespace App\Http\Requests\Client;

use App\Models\AppUser;
use Illuminate\Foundation\Http\FormRequest;

class DiscardDraftLoanRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && ! $user->isAdminOnly()
            && $user->hasMemberAccess();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
