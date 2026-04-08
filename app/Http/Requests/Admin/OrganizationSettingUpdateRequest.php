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
        return $this->user()?->isSuperadmin() ?? false;
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
            'loan_sms_approved_template' => ['nullable', 'string', 'max:1000'],
            'loan_sms_declined_template' => ['nullable', 'string', 'max:1000'],
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
            'report_header_title' => ['nullable', 'string', 'max:255'],
            'report_header_tagline' => ['nullable', 'string', 'max:255'],
            'report_header_show_logo' => ['nullable', 'boolean'],
            'report_header_show_company_name' => ['nullable', 'boolean'],
            'report_header_alignment' => [
                'nullable',
                'string',
                Rule::in(
                    app(OrganizationSettingsService::class)
                        ->reportHeaderAlignments(),
                ),
            ],
            'report_header_font_color' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^#[0-9a-fA-F]{6}$/',
            ],
            'report_header_tagline_color' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^#[0-9a-fA-F]{6}$/',
            ],
            'report_label_font_color' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^#[0-9a-fA-F]{6}$/',
            ],
            'report_value_font_color' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^#[0-9a-fA-F]{6}$/',
            ],
            'report_header_title_font_family' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\\s\\-+&.]+$/',
            ],
            'report_header_title_font_variant' => [
                'nullable',
                'string',
                Rule::in(['regular', 'italic']),
            ],
            'report_header_title_font_weight' => [
                'nullable',
                'string',
                Rule::in(['300', '400', '500', '600', '700', '800', '900']),
            ],
            'report_header_title_font_size' => [
                'nullable',
                'integer',
                'min:6',
                'max:24',
            ],
            'report_header_tagline_font_family' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\\s\\-+&.]+$/',
            ],
            'report_header_tagline_font_variant' => [
                'nullable',
                'string',
                Rule::in(['regular', 'italic']),
            ],
            'report_header_tagline_font_weight' => [
                'nullable',
                'string',
                Rule::in(['300', '400', '500', '600', '700', '800', '900']),
            ],
            'report_header_tagline_font_size' => [
                'nullable',
                'integer',
                'min:6',
                'max:24',
            ],
            'report_label_font_family' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\\s\\-+&.]+$/',
            ],
            'report_label_font_variant' => [
                'nullable',
                'string',
                Rule::in(['regular', 'italic']),
            ],
            'report_label_font_weight' => [
                'nullable',
                'string',
                Rule::in(['300', '400', '500', '600', '700', '800', '900']),
            ],
            'report_label_font_size' => [
                'nullable',
                'integer',
                'min:6',
                'max:24',
            ],
            'report_value_font_family' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9\\s\\-+&.]+$/',
            ],
            'report_value_font_variant' => [
                'nullable',
                'string',
                Rule::in(['regular', 'italic']),
            ],
            'report_value_font_weight' => [
                'nullable',
                'string',
                Rule::in(['300', '400', '500', '600', '700', '800', '900']),
            ],
            'report_value_font_size' => [
                'nullable',
                'integer',
                'min:6',
                'max:24',
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
            'report_header_font_color' => $this->normalizeHexColor(
                $this->input('report_header_font_color'),
            ),
            'report_header_tagline_color' => $this->normalizeHexColor(
                $this->input('report_header_tagline_color'),
            ),
            'report_label_font_color' => $this->normalizeHexColor(
                $this->input('report_label_font_color'),
            ),
            'report_value_font_color' => $this->normalizeHexColor(
                $this->input('report_value_font_color'),
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
            'loan_sms_approved_template.max' => 'Approved SMS template may not be greater than 1000 characters.',
            'loan_sms_declined_template.max' => 'Declined SMS template may not be greater than 1000 characters.',
            'brand_primary_color.regex' => 'Primary color must be a valid hex value (e.g., #1a2b3c).',
            'brand_accent_color.regex' => 'Accent color must be a valid hex value (e.g., #1a2b3c).',
            'report_header_font_color.regex' => 'Header color must be a valid hex value (e.g., #1a2b3c).',
            'report_header_tagline_color.regex' => 'Tagline color must be a valid hex value (e.g., #1a2b3c).',
            'report_label_font_color.regex' => 'Label color must be a valid hex value (e.g., #1a2b3c).',
            'report_value_font_color.regex' => 'Value color must be a valid hex value (e.g., #1a2b3c).',
            'report_header_alignment.in' => 'Report header alignment must be left, center, or right.',
        ];
    }
}
