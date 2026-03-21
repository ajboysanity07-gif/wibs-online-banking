<?php

namespace Database\Factories;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanRequest>
 */
class LoanRequestFactory extends Factory
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
            'acctno' => fake()->numerify('######'),
            'typecode' => fake()->bothify('LN-###'),
            'loan_type_label_snapshot' => fake()->randomElement([
                'Salary/Pension',
                'Personal',
                'Livelihood',
            ]),
            'requested_amount' => fake()->randomFloat(2, 1000, 100000),
            'requested_term' => fake()->numberBetween(6, 60),
            'loan_purpose' => fake()->sentence(3),
            'availment_status' => fake()->randomElement([
                'New',
                'Re-Loan',
                'Restructured',
            ]),
            'status' => LoanRequestStatus::Submitted,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_amount' => null,
            'approved_term' => null,
            'decision_notes' => null,
        ];
    }

    public function forUser(AppUser $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->user_id,
            'acctno' => $user->acctno ?? fake()->numerify('######'),
        ]);
    }
}
