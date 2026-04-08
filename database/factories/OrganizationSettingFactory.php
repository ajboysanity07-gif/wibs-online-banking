<?php

namespace Database\Factories;

use App\Models\OrganizationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSetting>
 */
class OrganizationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'company_logo_path' => null,
            'logo_preset' => null,
            'logo_mark_path' => null,
            'logo_full_path' => null,
            'portal_label' => fake()->words(2, true),
            'favicon_path' => null,
            'brand_primary_color' => null,
            'brand_accent_color' => null,
            'support_email' => fake()->safeEmail(),
            'support_phone' => fake()->phoneNumber(),
            'support_contact_name' => fake()->name(),
            'loan_sms_approved_template' => null,
            'loan_sms_declined_template' => null,
            'report_header_title' => null,
            'report_header_tagline' => null,
            'report_header_show_logo' => true,
            'report_header_show_company_name' => true,
            'report_header_alignment' => 'center',
            'report_header_font_color' => null,
            'report_header_tagline_color' => null,
            'report_label_font_color' => null,
            'report_value_font_color' => null,
            'report_header_title_font_family' => null,
            'report_header_title_font_variant' => null,
            'report_header_title_font_weight' => null,
            'report_header_title_font_size' => null,
            'report_header_tagline_font_family' => null,
            'report_header_tagline_font_variant' => null,
            'report_header_tagline_font_weight' => null,
            'report_header_tagline_font_size' => null,
            'report_label_font_family' => null,
            'report_label_font_variant' => null,
            'report_label_font_weight' => null,
            'report_label_font_size' => null,
            'report_value_font_family' => null,
            'report_value_font_variant' => null,
            'report_value_font_weight' => null,
            'report_value_font_size' => null,
        ];
    }
}
