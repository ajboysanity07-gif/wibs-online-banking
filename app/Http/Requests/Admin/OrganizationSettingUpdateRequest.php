<?php

namespace App\Http\Requests\Admin;

use App\Services\OrganizationSettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationSettingUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->adminProfile !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'portal_label' => ['nullable', 'string', 'max:255'],
            'logo_preset' => [
                'nullable',
                'string',
                Rule::in(app(OrganizationSettingsService::class)->logoPresets()),
            ],
            'logo_mark' => [
                'nullable',
                'file',
                'max:2048',
                'mimes:jpg,jpeg,png,webp',
            ],
            'logo_full' => [
                'nullable',
                'file',
                'max:2048',
                'mimes:jpg,jpeg,png,webp',
            ],
            'logo_mark_reset' => ['nullable', 'boolean'],
            'logo_full_reset' => ['nullable', 'boolean'],
            'favicon' => [
                'nullable',
                'file',
                'max:1024',
                'mimes:jpg,jpeg,png,webp,ico',
            ],
            'favicon_reset' => ['nullable', 'boolean'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:32'],
            'support_contact_name' => ['nullable', 'string', 'max:255'],
            'brand_primary_color' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^#[0-9a-fA-F]{6}$/',
            ],
            'brand_accent_color' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^#[0-9a-fA-F]{6}$/',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'brand_primary_color' => $this->normalizeHexColor(
                $this->input('brand_primary_color'),
            ),
            'brand_accent_color' => $this->normalizeHexColor(
                $this->input('brand_accent_color'),
            ),
        ]);
    }

    private function normalizeHexColor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return null;
        }

        if (! str_starts_with($normalized, '#')) {
            $normalized = '#'.$normalized;
        }

        if (preg_match('/^#([0-9a-f]{3})$/', $normalized, $matches) === 1) {
            $expanded = '';

            foreach (str_split($matches[1]) as $char) {
                $expanded .= $char.$char;
            }

            return '#'.$expanded;
        }

        return $normalized;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required.',
            'company_name.max' => 'Company name may not be greater than 255 characters.',
            'portal_label.max' => 'Portal label may not be greater than 255 characters.',
            'logo_mark.max' => 'Logo mark must be 2MB or smaller.',
            'logo_mark.mimes' => 'Logo mark must be a JPG, PNG, or WebP image.',
            'logo_full.max' => 'Logo full must be 2MB or smaller.',
            'logo_full.mimes' => 'Logo full must be a JPG, PNG, or WebP image.',
            'favicon.max' => 'Favicon must be 1MB or smaller.',
            'favicon.mimes' => 'Favicon must be a JPG, PNG, WebP, or ICO image.',
            'support_email.email' => 'Support email must be a valid email address.',
            'support_email.max' => 'Support email may not be greater than 255 characters.',
            'support_phone.max' => 'Support phone may not be greater than 32 characters.',
            'support_contact_name.max' => 'Support contact name may not be greater than 255 characters.',
            'brand_primary_color.regex' => 'Primary color must be a valid hex value (e.g., #1a2b3c).',
            'brand_accent_color.regex' => 'Accent color must be a valid hex value (e.g., #1a2b3c).',
        ];
    }
}
