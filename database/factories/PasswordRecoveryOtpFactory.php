<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\PasswordRecoveryOtp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<PasswordRecoveryOtp>
 */
class PasswordRecoveryOtpFactory extends Factory
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
            'phone' => fake()->numerify('09#########'),
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
            'attempts' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now(),
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code_hash' => Hash::make($code),
        ]);
    }
}
