<?php

namespace App\Http\Requests\Superadmin;

use App\Models\AppUser;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $user->canManageStaff();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(Role::editableStaffNames())],
            'operation' => ['required', 'string', Rule::in(['assign', 'remove'])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
