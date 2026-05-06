<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymongoServiceFeeCalculator
{
    private const VAT_MULTIPLIER = 1.12;

    private const PAYMONGO_FIXED_FEE_CENTS = 1339;

    /**
     * @var array<string, array{label: string, rate: float, fixed_fee_cents: int, paymongo_type: string, uses_minimum?: bool}>
     */
    private const METHODS = [
        'gcash' => [
            'label' => 'GCash',
            'rate' => 0.0223,
            'fixed_fee_cents' => 0,
            'paymongo_type' => 'gcash',
        ],
        'maya' => [
            'label' => 'Maya',
            'rate' => 0.0179,
            'fixed_fee_cents' => 0,
            'paymongo_type' => 'paymaya',
        ],
        'qrph' => [
            'label' => 'QRPh',
            'rate' => 0.0134,
            'fixed_fee_cents' => 0,
            'paymongo_type' => 'qrph',
        ],
        'online_banking' => [
            'label' => 'Online Banking',
            'rate' => 0.0071,
            'fixed_fee_cents' => self::PAYMONGO_FIXED_FEE_CENTS,
            'paymongo_type' => 'dob',
            'uses_minimum' => true,
        ],
        'card' => [
            'label' => 'Card',
            'rate' => 0.03125,
            'fixed_fee_cents' => self::PAYMONGO_FIXED_FEE_CENTS,
            'paymongo_type' => 'card',
        ],
    ];

    /**
     * @return list<string>
     */
    public function supportedMethods(): array
    {
        return array_keys(self::METHODS);
    }

    /**
     * @return array{
     *     method: string,
     *     label: string,
     *     base_amount_cents: int,
     *     service_fee_cents: int,
     *     gross_amount_cents: int,
     *     rate: float,
     *     fixed_fee_cents: int
     * }
     */
    public function calculate(int $baseAmountCents, string $method): array
    {
        if ($baseAmountCents < 1) {
            throw new InvalidArgumentException('Base amount must be greater than zero.');
        }

        $method = $this->normalizeMethod($method);
        $definition = self::METHODS[$method] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException('Unsupported PayMongo payment method.');
        }

        $rate = $definition['rate'] * self::VAT_MULTIPLIER;
        $fixedFeeCents = $this->withVatCents($definition['fixed_fee_cents']);

        $serviceFeeCents = $definition['uses_minimum'] ?? false
            ? $this->calculateMinimumAwareFee($baseAmountCents, $rate, $fixedFeeCents)
            : $this->calculatePassOnFee($baseAmountCents, $rate, $fixedFeeCents);

        return [
            'method' => $method,
            'label' => $definition['label'],
            'base_amount_cents' => $baseAmountCents,
            'service_fee_cents' => $serviceFeeCents,
            'gross_amount_cents' => $baseAmountCents + $serviceFeeCents,
            'rate' => $rate,
            'fixed_fee_cents' => $fixedFeeCents,
        ];
    }

    public function paymongoPaymentMethodType(string $method): string
    {
        $method = $this->normalizeMethod($method);
        $definition = self::METHODS[$method] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException('Unsupported PayMongo payment method.');
        }

        return $definition['paymongo_type'];
    }

    private function calculateMinimumAwareFee(
        int $baseAmountCents,
        float $rate,
        int $minimumFeeCents,
    ): int {
        $percentageFeeCents = $this->calculatePassOnFee(
            $baseAmountCents,
            $rate,
            0,
        );

        return max($percentageFeeCents, $minimumFeeCents);
    }

    private function calculatePassOnFee(
        int $baseAmountCents,
        float $rate,
        int $fixedFeeCents,
    ): int {
        $grossAmountCents = ($baseAmountCents + $fixedFeeCents) / (1 - $rate);

        return (int) ceil($grossAmountCents - $baseAmountCents);
    }

    private function withVatCents(int $amountCents): int
    {
        if ($amountCents === 0) {
            return 0;
        }

        return (int) ceil($amountCents * self::VAT_MULTIPLIER);
    }

    private function normalizeMethod(string $method): string
    {
        return match (strtolower(trim($method))) {
            'paymaya' => 'maya',
            'dob' => 'online_banking',
            default => strtolower(trim($method)),
        };
    }
}
