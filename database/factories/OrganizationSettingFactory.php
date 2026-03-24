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
        ];
    }
}
