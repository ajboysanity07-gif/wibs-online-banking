<?php

use App\Services\Payments\PaymongoServiceFeeCalculator;

it('calculates pass on service fees with vat', function () {
    $calculator = new PaymongoServiceFeeCalculator;

    expect($calculator->calculate(100000, 'gcash'))
        ->toMatchArray([
            'method' => 'gcash',
            'label' => 'GCash',
            'base_amount_cents' => 100000,
            'service_fee_cents' => 2562,
            'gross_amount_cents' => 102562,
            'fixed_fee_cents' => 0,
        ])
        ->and($calculator->calculate(100000, 'maya'))
        ->toMatchArray([
            'method' => 'maya',
            'label' => 'Maya',
            'base_amount_cents' => 100000,
            'service_fee_cents' => 2046,
            'gross_amount_cents' => 102046,
            'fixed_fee_cents' => 0,
        ])
        ->and($calculator->calculate(100000, 'qrph'))
        ->toMatchArray([
            'method' => 'qrph',
            'label' => 'QRPh',
            'base_amount_cents' => 100000,
            'service_fee_cents' => 1524,
            'gross_amount_cents' => 101524,
            'fixed_fee_cents' => 0,
        ]);
});

it('uses the vat inclusive online banking minimum fee when it is higher', function () {
    $calculator = new PaymongoServiceFeeCalculator;

    expect($calculator->calculate(100000, 'online_banking'))
        ->toMatchArray([
            'method' => 'online_banking',
            'label' => 'Online Banking',
            'base_amount_cents' => 100000,
            'service_fee_cents' => 1500,
            'gross_amount_cents' => 101500,
            'fixed_fee_cents' => 1500,
        ])
        ->and($calculator->calculate(500000, 'online_banking'))
        ->toMatchArray([
            'method' => 'online_banking',
            'service_fee_cents' => 4008,
            'gross_amount_cents' => 504008,
        ]);
});

it('maps internal method names to paymongo enum values', function () {
    $calculator = new PaymongoServiceFeeCalculator;

    expect($calculator->paymongoPaymentMethodType('gcash'))->toBe('gcash')
        ->and($calculator->paymongoPaymentMethodType('maya'))->toBe('paymaya')
        ->and($calculator->paymongoPaymentMethodType('qrph'))->toBe('qrph')
        ->and($calculator->paymongoPaymentMethodType('online_banking'))->toBe('dob');
});
