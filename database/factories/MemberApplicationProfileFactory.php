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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => null,
            'nickname' => null,
            'birthdate' => null,
            'birthplace' => null,
            'age' => null,
            'address' => null,
            'length_of_stay' => null,
            'housing_status' => null,
            'civil_status' => null,
            'educational_attainment' => null,
            'number_of_children' => null,
            'spouse_name' => null,
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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birthdate' => fake()->date(),
            'birthplace' => fake()->city(),
            'address' => fake()->streetAddress(),
            'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed']),
            'employment_type' => fake()->randomElement(['Regular', 'Contract', 'Self-Employed']),
            'employer_business_name' => fake()->company(),
            'current_position' => fake()->jobTitle(),
            'gross_monthly_income' => fake()->randomFloat(2, 1000, 50000),
            'payday' => fake()->randomElement(['15', '30', '15/30']),
            'profile_completed_at' => now(),
        ]);
    }
}
