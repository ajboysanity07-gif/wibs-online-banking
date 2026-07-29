<?php

namespace Database\Factories;

use App\Models\MemberApplicationProfile;
use App\Models\MemberCoMaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberCoMaker>
 */
class MemberCoMakerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_application_profile_id' => MemberApplicationProfile::factory(),
            'label' => fake()->optional()->word(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->optional()->firstName(),
            'nickname' => fake()->optional()->firstName(),
            'birthdate' => fake()->optional()->date(),
            'birthplace' => fake()->optional()->city(),
            'birthplace_city' => null,
            'birthplace_province' => null,
            'address' => fake()->optional()->address(),
            'address1' => null,
            'address2' => null,
            'address3' => null,
            'length_of_stay' => fake()->optional()->randomElement(['1 year', '2 years', '5 years']),
            'housing_status' => fake()->optional()->randomElement(['Owned', 'Rent']),
            'cell_no' => fake()->optional()->numerify('09#########'),
            'civil_status' => fake()->optional()->randomElement(['Single', 'Married', 'Widowed']),
            'sex' => fake()->optional()->randomElement(['Male', 'Female']),
            'educational_attainment' => fake()->optional()->randomElement([
                'Elementary',
                'High School',
                'Vocational',
                'College',
            ]),
            'number_of_children' => fake()->optional()->numberBetween(0, 5),
            'spouse_name' => fake()->optional()->name(),
            'spouse_age' => fake()->optional()->numberBetween(18, 65),
            'spouse_cell_no' => fake()->optional()->numerify('09#########'),
            'employment_type' => fake()->optional()->randomElement([
                'Private',
                'Government',
                'Self Employed',
                'Retired',
            ]),
            'employer_business_name' => fake()->optional()->company(),
            'employer_business_address' => fake()->optional()->address(),
            'employer_business_address1' => null,
            'employer_business_address2' => null,
            'employer_business_address3' => null,
            'telephone_no' => fake()->optional()->numerify('02#######'),
            'current_position' => fake()->optional()->jobTitle(),
            'nature_of_business' => fake()->optional()->word(),
            'years_in_work_business' => fake()->optional()->randomElement(['1 year', '3 years', '10 years']),
            'gross_monthly_income' => fake()->optional()->randomFloat(2, 1000, 50000),
            'payday' => fake()->optional()->randomElement(['15', '30', '15/30']),
            'last_used_at' => fake()->optional()->dateTime(),
        ];
    }

    public function forProfile(MemberApplicationProfile $profile): static
    {
        return $this->state(fn () => [
            'member_application_profile_id' => $profile->id,
        ]);
    }
}
