<?php

namespace App\Http\Requests\Admin;

use App\Models\AppUser;
use Illuminate\Foundation\Http\FormRequest;

class MemberResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof AppUser || ! $actor->isSuperadmin() || ! $actor->hasActiveStaffAccess()) {
            return false;
        }

        $target = $this->route('user');

        if ($target instanceof AppUser && $target->adminProfile !== null) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
