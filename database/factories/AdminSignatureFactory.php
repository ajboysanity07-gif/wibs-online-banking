<?php

namespace Database\Factories;

use App\Models\AdminSignature;
use App\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminSignature>
 */
class AdminSignatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory(),
            'signature_path' => 'loan-manager-signatures/'.fake()->uuid().'.png',
            'is_active' => true,
            'created_ip' => fake()->ipv4(),
            'created_user_agent' => fake()->userAgent(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
