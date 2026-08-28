<?php

namespace Database\Factories;

use App\Models\MemberApplicationProfile;
use App\Models\MemberPaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberPaymentAccount>
 */
class MemberPaymentAccountFactory extends Factory
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
            'bank_name' => fake()->randomElement(['BDO', 'BPI', 'Metrobank', 'Landbank']),
            'account_name' => fake()->name(),
            'account_number' => fake()->numerify('##########'),
            'account_type' => fake()->randomElement(['Savings', 'Checking']),
            'atm_number' => fake()->optional()->numerify('################'),
            'bank_branch' => fake()->optional()->city(),
            'atm_holder_name' => fake()->optional()->name(),
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
