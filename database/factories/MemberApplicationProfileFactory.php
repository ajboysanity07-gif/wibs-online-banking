<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberApplicationProfile>
 */
class MemberApplicationProfileFactory extends Factory
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
            'nickname' => null,
            'birthplace' => null,
            'educational_attainment' => null,
            'length_of_stay' => null,
            'number_of_children' => null,
            'spouse_age' => null,
            'spouse_cell_no' => null,
            'employment_type' => null,
            'employer_business_name' => null,
            'employer_business_address' => null,
            'telephone_no' => null,
            'current_position' => null,
            'nature_of_business' => null,
            'years_in_work_business' => null,
            'gross_monthly_income' => null,
            'payday' => null,
            'profile_completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'birthplace' => fake()->city(),
            'educational_attainment' => fake()->randomElement(['High School', 'College', 'Vocational']),
            'length_of_stay' => fake()->randomElement(['1 year', '2 years', '5 years']),
            'employment_type' => fake()->randomElement(['Regular', 'Contract', 'Self-Employed']),
            'employer_business_name' => fake()->company(),
            'current_position' => fake()->jobTitle(),
            'gross_monthly_income' => fake()->randomFloat(2, 1000, 50000),
            'payday' => fake()->randomElement(['15', '30', '15/30']),
            'profile_completed_at' => now(),
        ]);
    }
}
