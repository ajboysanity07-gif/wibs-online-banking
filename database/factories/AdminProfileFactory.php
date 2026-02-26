<?php

namespace Database\Factories;

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
            'profile_pic_path' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
