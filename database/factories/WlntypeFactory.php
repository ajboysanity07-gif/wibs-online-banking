<?php

namespace Database\Factories;

use App\Models\Wlntype;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wlntype>
 */
class WlntypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'typecode' => fake()->bothify('LN-###'),
            'lntype' => fake()->randomElement([
                'Salary/Pension',
                'Personal',
                'Livelihood',
                'Additional Capital',
            ]),
        ];
    }
}
