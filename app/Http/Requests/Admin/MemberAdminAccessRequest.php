<?php

namespace App\Http\Requests\Admin;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MemberAdminAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof AppUser) {
            return false;
        }

        $actor->loadMissing('adminProfile');

        return $actor->isSuperadmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
