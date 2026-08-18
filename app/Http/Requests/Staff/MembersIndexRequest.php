<?php

namespace App\Http\Requests\Staff;

use App\Models\AppUser;
use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembersIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $user->hasPermission(Permission::MEMBER_VIEW);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'registration' => ['nullable', 'string', Rule::in(['registered', 'unregistered'])],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
