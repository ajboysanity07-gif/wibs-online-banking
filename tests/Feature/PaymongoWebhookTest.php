<?php

use App\Models\PaymongoLoanPayment;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('marks a matched checkout session payment as paid', function () {
    Carbon::setTestNow('2026-05-06 10:00:00');

    $payment = PaymongoLoanPayment::query()->create([
        'user_id' => null,
        'acctno' => '000001',
        'loan_number' => 'LN-1001',
        'currency' => 'PHP',
        'payment_method' => 'gcash',
        'payment_method_label' => 'GCash',
        'payment_method_type' => 'gcash',
        'base_amount_cents' => 100000,
        'service_fee_cents' => 2562,
        'gross_amount_cents' => 102562,
        'status' => PaymongoLoanPayment::STATUS_PENDING,
        'provider' => 'paymongo',
        'provider_checkout_session_id' => 'cs_test_123',
        'metadata' => [],
    ]);

    $this->postJson(route('webhooks.paymongo'), [
        'data' => [
            'id' => 'evt_test_paid',
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'data' => [
                    'id' => 'cs_test_123',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'status' => 'paid',
                        'reference_number' => 'PM-REF-123',
                        'metadata' => [
                            'local_payment_id' => $payment->getKey(),
                        ],
                        'payment_intent' => [
                            'id' => 'pi_test_123',
                        ],
                        'payments' => [
                            [
                                'attributes' => [
                                    'paid_at' => 1778032800,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJson(['ok' => true]);

    $payment->refresh();

    expect($payment->status)->toBe(PaymongoLoanPayment::STATUS_PAID)
        ->and($payment->provider_payment_intent_id)->toBe('pi_test_123')
        ->and($payment->provider_reference_number)->toBe('PM-REF-123')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->metadata['last_webhook']['event_type'])
        ->toBe('checkout_session.payment.paid');
});

it('does not downgrade an already paid local payment', function () {
    $payment = PaymongoLoanPayment::query()->create([
        'user_id' => null,
        'acctno' => '000001',
        'loan_number' => 'LN-1001',
        'currency' => 'PHP',
        'payment_method' => 'gcash',
        'payment_method_label' => 'GCash',
        'payment_method_type' => 'gcash',
        'base_amount_cents' => 100000,
        'service_fee_cents' => 2562,
        'gross_amount_cents' => 102562,
        'status' => PaymongoLoanPayment::STATUS_PAID,
        'provider' => 'paymongo',
        'provider_checkout_session_id' => 'cs_test_123',
        'provider_payment_intent_id' => 'pi_test_123',
        'paid_at' => now(),
        'metadata' => [],
    ]);

    $this->postJson(route('webhooks.paymongo'), [
        'data' => [
            'id' => 'evt_test_failed',
            'type' => 'event',
            'attributes' => [
                'type' => 'payment.failed',
                'livemode' => false,
                'data' => [
                    'id' => 'pay_test_123',
                    'type' => 'payment',
                    'attributes' => [
                        'status' => 'failed',
                        'payment_intent_id' => 'pi_test_123',
                    ],
                ],
            ],
        ],
    ])->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymongoLoanPayment::STATUS_PAID);
});
