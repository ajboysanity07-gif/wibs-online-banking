<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\PaymongoLoanPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymongoLoanPayment>
 */
class PaymongoLoanPaymentFactory extends Factory
{
    protected $model = PaymongoLoanPayment::class;

    public function definition(): array
    {
        $baseAmountCents = fake()->numberBetween(10000, 250000);
        $serviceFeeCents = fake()->numberBetween(0, 5000);
        $method = fake()->randomElement([
            'gcash',
            'maya',
            'qrph',
            'online_banking',
        ]);

        return [
            'user_id' => AppUser::factory(),
            'acctno' => str_pad(
                (string) fake()->numberBetween(1, 999999),
                6,
                '0',
                STR_PAD_LEFT,
            ),
            'loan_number' => 'LN-'.fake()->unique()->numerify('####'),
            'currency' => 'PHP',
            'payment_method' => $method,
            'payment_method_label' => match ($method) {
                'gcash' => 'GCash',
                'maya' => 'Maya',
                'qrph' => 'QRPh',
                default => 'Online Banking',
            },
            'payment_method_type' => match ($method) {
                'gcash' => 'gcash',
                'maya' => 'paymaya',
                'qrph' => 'qrph',
                default => 'dob',
            },
            'base_amount_cents' => $baseAmountCents,
            'service_fee_cents' => $serviceFeeCents,
            'gross_amount_cents' => $baseAmountCents + $serviceFeeCents,
            'status' => PaymongoLoanPayment::STATUS_PENDING,
            'provider' => 'paymongo',
            'provider_checkout_session_id' => 'cs_'.fake()->unique()->bothify('??????######'),
            'provider_payment_intent_id' => 'pi_'.fake()->unique()->bothify('??????######'),
            'provider_reference_number' => 'PM-'.fake()->unique()->numerify('######'),
            'checkout_url' => fake()->url(),
            'metadata' => [],
            'paid_at' => null,
            'expires_at' => now()->addHour(),
        ];
    }
}
