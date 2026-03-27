<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wmaster>
 */
class WmasterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lastName = Str::upper(fake()->lastName());
        $firstName = Str::upper(fake()->firstName());
        $middleInitial = fake()->optional()->randomLetter();
        $middleInitial = $middleInitial === null ? null : Str::upper($middleInitial);
        $street = Str::upper(fake()->streetAddress());
        $city = Str::upper(fake()->city());
        $province = Str::upper(fake()->state());

        return [
            'acctno' => str_pad((string) fake()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'lname' => $lastName,
            'fname' => $firstName,
            'mname' => $middleInitial,
            'bname' => $middleInitial === null
                ? sprintf('%s, %s', $lastName, $firstName)
                : sprintf('%s, %s, %s.', $lastName, $firstName, $middleInitial),
            'birthplace' => $city,
            'address' => sprintf('%s, %s, %s', $street, $city, $province),
            'address2' => $street,
            'address3' => $city,
            'address4' => $province,
            'phone' => fake()->optional()->numerify('09#########'),
        ];
    }
}
