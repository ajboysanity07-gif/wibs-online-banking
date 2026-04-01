<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminProfile>
 */
class AdminProfileFactory extends Factory
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
            'fullname' => fake()->name(),
            'access_level' => AdminProfile::ACCESS_LEVEL_ADMIN,
            'profile_pic_path' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'access_level' => AdminProfile::ACCESS_LEVEL_ADMIN,
        ]);
    }

    public function superadmin(): static
    {
        return $this->state(fn () => [
            'access_level' => AdminProfile::ACCESS_LEVEL_SUPERADMIN,
        ]);
    }
}
