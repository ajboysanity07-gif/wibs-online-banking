<?php

namespace App\Http\Requests\Admin;

use App\Models\AppUser;
use Illuminate\Foundation\Http\FormRequest;

class MemberStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user();

        if ($actor?->adminProfile === null) {
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
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
