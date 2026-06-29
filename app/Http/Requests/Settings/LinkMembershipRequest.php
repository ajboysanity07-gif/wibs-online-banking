<?php

namespace App\Http\Requests\Settings;

use App\Models\AppUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof AppUser) {
            return false;
        }

        // GUARD 3: already-linked users are excluded — acctno must be null/empty
        $acctno = $user->acctno;

        return $acctno === null || trim((string) $acctno) === '';
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accntno' => [
                'required',
                'string',
                'max:30',
                // GUARD 1: acctno must not already belong to another AppUser
                Rule::unique('appusers', 'acctno'),
            ],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accntno.unique' => 'That account number is already linked to another portal account.',
        ];
    }
}
