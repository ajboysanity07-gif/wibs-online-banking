<?php

namespace App\Http\Requests\Superadmin;

use App\Models\AppUser;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteMemberToStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser && $user->canManageStaff();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string', Rule::exists('appusers', 'acctno')],
            'role' => ['required', 'string', Rule::in(Role::editableStaffNames())],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_number.exists' => 'No member account found with this account number.',
        ];
    }
}
