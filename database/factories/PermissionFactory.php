<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = Str::snake(fake()->unique()->word());
        $action = Str::snake(fake()->word());

        return [
            'name' => sprintf('%s.%s', $domain, $action),
            'display_name' => fake()->words(2, true),
        ];
    }
}
