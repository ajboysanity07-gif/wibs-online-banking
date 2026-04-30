<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\OnlinePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlinePayment>
 */
class OnlinePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 500000);

        return [
            'user_id' => AppUser::factory(),
            'acctno' => fake()->numerify('######'),
            'loan_number' => fake()->bothify('LN-###'),
            'amount' => $amount,
            'currency' => 'PHP',
            'provider' => 'paymongo',
            'provider_checkout_id' => null,
            'provider_payment_id' => null,
            'reference_number' => null,
            'status' => OnlinePayment::STATUS_PENDING,
            'paid_at' => null,
            'posted_at' => null,
            'posted_by' => null,
            'raw_payload' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'provider_payment_id' => 'pay_'.fake()->regexify('[A-Za-z0-9]{24}'),
            'reference_number' => fake()->bothify('PM-########'),
            'status' => OnlinePayment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
