<?php

namespace App\Http\Requests\Superadmin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\AppUser;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

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
            ...$this->profileRules(requirePhone: false),
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
            'roles' => ['required', 'array', 'min:1', 'max:1'],
            'roles.*' => ['required', 'string', Rule::in(Role::editableStaffNames())],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
