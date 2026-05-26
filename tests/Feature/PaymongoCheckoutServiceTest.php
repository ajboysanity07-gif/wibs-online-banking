<?php

use App\Models\AppUser;
use App\Models\PaymongoLoanPayment;
use App\Models\Wlnmaster;
use App\Services\Payments\PaymongoCheckoutService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('creates a local pending payment and checkout session request', function () {
    config()->set('services.paymongo.secret_key', 'paymongo_test_secret');
    config()->set('services.paymongo.base_url', 'https://api.paymongo.com/v1');
    config()->set(
        'services.paymongo.success_url',
        'https://example.test/client/payments/paymongo/{payment}/success',
    );
    config()->set(
        'services.paymongo.cancel_url',
        'https://example.test/client/payments/paymongo/{payment}/cancel',
    );

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_123',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_123',
                    'status' => 'active',
                    'reference_number' => 'PM-123',
                    'payment_method_types' => ['paymaya'],
                    'payment_intent' => [
                        'id' => 'pi_test_123',
                    ],
                    'expires_at' => 1778036400,
                ],
            ],
        ]),
    ]);

    $user = AppUser::factory()->create([
        'acctno' => '000001',
    ]);
    $loan = new Wlnmaster([
        'acctno' => '000001',
        'lnnumber' => 'LN-1001',
    ]);

    $result = app(PaymongoCheckoutService::class)->create(
        $user,
        $loan,
        '000001',
        100000,
        'maya',
    );

    $payment = $result['payment'];

    expect($payment)->toBeInstanceOf(PaymongoLoanPayment::class)
        ->and($result['checkout_url'])->toBe('https://checkout.paymongo.com/cs_test_123')
        ->and($payment->status)->toBe(PaymongoLoanPayment::STATUS_PENDING)
        ->and($payment->payment_method)->toBe('maya')
        ->and($payment->payment_method_type)->toBe('paymaya')
        ->and($payment->base_amount_cents)->toBe(100000)
        ->and($payment->service_fee_cents)->toBe(2046)
        ->and($payment->gross_amount_cents)->toBe(102046)
        ->and($payment->provider_checkout_session_id)->toBe('cs_test_123')
        ->and($payment->provider_payment_intent_id)->toBe('pi_test_123');

    Http::assertSent(function (Request $request) use ($payment): bool {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && $request->hasHeader(
                'Authorization',
                'Basic '.base64_encode('paymongo_test_secret:'),
            )
            && data_get($payload, 'data.attributes.payment_method_types.0') === 'paymaya'
            && data_get($payload, 'data.attributes.line_items.0.name') === 'Loan Payment'
            && data_get($payload, 'data.attributes.line_items.0.amount') === 100000
            && data_get($payload, 'data.attributes.line_items.1.name') === 'Service Fee'
            && data_get($payload, 'data.attributes.line_items.1.amount') === 2046
            && data_get($payload, 'data.attributes.metadata.local_payment_id') === $payment->getKey()
            && data_get($payload, 'data.attributes.metadata.payment_method') === 'maya'
            && data_get($payload, 'data.attributes.success_url') === 'https://example.test/client/payments/paymongo/'.$payment->getKey().'/success'
            && data_get($payload, 'data.attributes.cancel_url') === 'https://example.test/client/payments/paymongo/'.$payment->getKey().'/cancel';
    });
});
