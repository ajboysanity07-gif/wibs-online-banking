<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\MemberApplicationProfile;
use App\Models\OnlinePayment;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
        });
    }

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->dateTime('lastmove')->nullable();
            $table->decimal('initial', 12, 2)->default(0);
        });
    }

    config([
        'paymongo.secret_key' => 'sk_test_online_payments',
        'paymongo.webhook_secret' => 'whsec_online_payments',
        'paymongo.mode' => 'test',
        'paymongo.base_url' => 'https://api.paymongo.com/v1',
    ]);
});

test('online payments table and model store centavo amounts', function () {
    expect(Schema::hasTable('online_payments'))->toBeTrue()
        ->and(Schema::hasColumn('online_payments', 'loan_number'))->toBeTrue()
        ->and(Schema::hasColumn('online_payments', 'raw_payload'))->toBeTrue();

    $payment = OnlinePayment::factory()->create([
        'amount' => 12345,
        'raw_payload' => ['status' => 'stored'],
        'paid_at' => now(),
    ]);

    expect($payment->amount)->toBe(12345)
        ->and($payment->raw_payload)->toBe(['status' => 'stored'])
        ->and($payment->paid_at)->not->toBeNull();
});

test('PayMongo config parses comma separated payment methods from environment', function () {
    $originalPaymentMethods = env('PAYMONGO_PAYMENT_METHODS');

    try {
        setPaymongoPaymentMethodsEnvironment('gcash,paymaya,unsupported,qrph,dob');
        $configured = require base_path('config/paymongo.php');

        expect($configured['payment_methods'])->toBe(['gcash', 'paymaya', 'qrph', 'dob']);

        setPaymongoPaymentMethodsEnvironment(null);
        $defaulted = require base_path('config/paymongo.php');

        expect($defaulted['payment_methods'])->toBe(['gcash']);
    } finally {
        setPaymongoPaymentMethodsEnvironment(
            is_scalar($originalPaymentMethods) ? (string) $originalPaymentMethods : null,
        );
    }
});

test('client can create a pending PayMongo checkout for their loan', function () {
    $user = createOnlinePaymentsClient('000801');
    createOnlinePaymentsLoan($user, 'LN-801', 1200);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => [
                'id' => 'cs_test_801',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.test/cs_test_801',
                ],
            ],
        ]),
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-payments.paymongo.checkout', [
            'loanNumber' => 'LN-801',
        ]), [
            'amount' => '150.75',
        ]);

    $response->assertRedirect('https://checkout.paymongo.test/cs_test_801');

    $this->assertDatabaseHas('online_payments', [
        'user_id' => $user->user_id,
        'acctno' => '000801',
        'loan_number' => 'LN-801',
        'amount' => 15075,
        'status' => OnlinePayment::STATUS_PENDING,
        'provider_checkout_id' => 'cs_test_801',
    ]);

    Http::assertSent(fn ($request): bool => data_get(
        $request->data(),
        'data.attributes.payment_method_types',
    ) === ['gcash']);
});

test('failed PayMongo checkout logs response details and shows local exception message', function () {
    Log::spy();

    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ]);

    config(['paymongo.payment_methods' => ['gcash', 'paymaya', 'unsupported']]);

    $user = createOnlinePaymentsClient('000808');
    createOnlinePaymentsLoan($user, 'LN-808', 1200);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'errors' => [
                [
                    'code' => 'parameter_invalid',
                    'detail' => 'payment_method_types contains an unavailable payment method.',
                ],
            ],
        ], 422),
    ]);

    $originalEnvironment = app()->environment();
    app()->detectEnvironment(static fn (): string => 'local');

    try {
        $response = $this
            ->actingAs($user)
            ->post(route('client.loan-payments.paymongo.checkout', [
                'loanNumber' => 'LN-808',
            ]), [
                'amount' => '150.75',
            ]);
    } finally {
        app()->detectEnvironment(static fn (): string => $originalEnvironment);
    }

    $response->assertSessionHasErrors('amount');
    expect(session('errors')->get('amount')[0])
        ->toContain('HTTP request returned status code 422');

    $this->assertDatabaseHas('online_payments', [
        'user_id' => $user->user_id,
        'acctno' => '000808',
        'loan_number' => 'LN-808',
        'amount' => 15075,
        'status' => OnlinePayment::STATUS_FAILED,
    ]);

    Http::assertSent(fn ($request): bool => data_get(
        $request->data(),
        'data.attributes.payment_method_types',
    ) === ['gcash', 'paymaya']);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'PayMongo checkout request failed.'
                && ($context['status'] ?? null) === 422
                && str_contains((string) ($context['body'] ?? ''), 'parameter_invalid')
                && ($context['payment_methods'] ?? null) === ['gcash', 'paymaya']
                && ! array_key_exists('secret_key', $context)
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'sk_test_online_payments');
        });
});

test('failed PayMongo checkout keeps production validation error generic', function () {
    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ]);

    $user = createOnlinePaymentsClient('000809');
    createOnlinePaymentsLoan($user, 'LN-809', 1200);

    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'errors' => [
                [
                    'code' => 'parameter_invalid',
                    'detail' => 'payment_method_types contains an unavailable payment method.',
                ],
            ],
        ], 422),
    ]);

    $originalEnvironment = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $response = $this
            ->actingAs($user)
            ->post(route('client.loan-payments.paymongo.checkout', [
                'loanNumber' => 'LN-809',
            ]), [
                'amount' => '150.75',
            ]);
    } finally {
        app()->detectEnvironment(static fn (): string => $originalEnvironment);
    }

    $response->assertSessionHasErrors([
        'amount' => 'Online checkout is temporarily unavailable. Please try again later.',
    ]);
});

test('success redirect does not mark an online payment as paid', function () {
    $user = createOnlinePaymentsClient('000802');
    $payment = OnlinePayment::factory()->create([
        'user_id' => $user->user_id,
        'acctno' => '000802',
        'loan_number' => 'LN-802',
        'status' => OnlinePayment::STATUS_PENDING,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.online-payments.success', $payment));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/online-payment-status')
            ->where('message', 'Payment submitted. We are waiting for PayMongo confirmation.'));

    expect($payment->refresh()->status)->toBe(OnlinePayment::STATUS_PENDING);
});

test('valid PayMongo webhook marks a payment paid', function () {
    $payment = OnlinePayment::factory()->create([
        'provider_checkout_id' => 'cs_test_803',
        'status' => OnlinePayment::STATUS_PENDING,
    ]);
    $payload = paymongoCheckoutPaidPayload($payment, 'cs_test_803', 'pay_test_803');
    $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        route('webhooks.paymongo'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => paymongoSignature($rawPayload),
        ],
        $rawPayload,
    );

    $response
        ->assertOk()
        ->assertJson(['message' => 'SUCCESS']);

    $payment->refresh();

    expect($payment->status)->toBe(OnlinePayment::STATUS_PAID)
        ->and($payment->provider_payment_id)->toBe('pay_test_803')
        ->and($payment->reference_number)->toBe('PM-'.$payment->id)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->posted_at)->toBeNull();
});

test('invalid PayMongo webhook signature is rejected', function () {
    $payload = json_encode(['data' => ['type' => 'event']], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        route('webhooks.paymongo'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't=1496734173,te=invalid,li=',
        ],
        $payload,
    );

    $response->assertUnauthorized();
});

test('duplicate PayMongo webhook is idempotent', function () {
    $payment = OnlinePayment::factory()->create([
        'provider_checkout_id' => 'cs_test_804',
        'status' => OnlinePayment::STATUS_PENDING,
    ]);
    $payload = paymongoCheckoutPaidPayload($payment, 'cs_test_804', 'pay_test_804');
    $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = paymongoSignature($rawPayload);

    foreach ([1, 2] as $attempt) {
        $this->call(
            'POST',
            route('webhooks.paymongo'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYMONGO_SIGNATURE' => $signature,
            ],
            $rawPayload,
        )->assertOk();
    }

    expect(OnlinePayment::query()->whereKey($payment->id)->count())->toBe(1)
        ->and($payment->refresh()->status)->toBe(OnlinePayment::STATUS_PAID)
        ->and($payment->provider_payment_id)->toBe('pay_test_804');
});

test('client cannot create checkout for another members loan', function () {
    $owner = createOnlinePaymentsClient('000805');
    $viewer = createOnlinePaymentsClient('000806');
    createOnlinePaymentsLoan($owner, 'LN-805', 900);

    Http::fake();

    $response = $this
        ->actingAs($viewer)
        ->post(route('client.loan-payments.paymongo.checkout', [
            'loanNumber' => 'LN-805',
        ]), [
            'amount' => '100.00',
        ]);

    $response->assertNotFound();
    $this->assertDatabaseMissing('online_payments', [
        'loan_number' => 'LN-805',
        'user_id' => $viewer->user_id,
    ]);
    Http::assertNothingSent();
});

test('admin can view online payments for review', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);
    OnlinePayment::factory()->paid()->create([
        'acctno' => '000807',
        'loan_number' => 'LN-807',
        'amount' => 25000,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.online-payments.index', [
            'status' => OnlinePayment::STATUS_PAID,
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/online-payments')
            ->has('payments.items', 1)
            ->where('payments.items.0.loan_number', 'LN-807')
            ->where('payments.items.0.status', OnlinePayment::STATUS_PAID));
});

function createOnlinePaymentsClient(string $acctno): User
{
    $user = User::factory()->create([
        'acctno' => $acctno,
    ]);

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $acctno,
        'bname' => 'Member, Online',
        'fname' => 'Online',
        'lname' => 'Member',
    ]);

    return $user;
}

function createOnlinePaymentsLoan(
    User $user,
    string $loanNumber,
    int|float $balance,
): void {
    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => $loanNumber,
        'lntype' => 'Regular',
        'principal' => $balance,
        'balance' => $balance,
        'initial' => 0,
    ]);
}

/**
 * @return array<string, mixed>
 */
function paymongoCheckoutPaidPayload(
    OnlinePayment $payment,
    string $checkoutId,
    string $providerPaymentId,
): array {
    return [
        'data' => [
            'id' => 'evt_test_'.$payment->id,
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => [
                    'id' => $checkoutId,
                    'type' => 'checkout_session',
                    'attributes' => [
                        'reference_number' => 'PM-'.$payment->id,
                        'metadata' => [
                            'online_payment_id' => (string) $payment->id,
                            'loan_number' => $payment->loan_number,
                            'acctno' => $payment->acctno,
                            'user_id' => (string) $payment->user_id,
                        ],
                        'payments' => [
                            [
                                'id' => $providerPaymentId,
                                'type' => 'payment',
                                'attributes' => [
                                    'amount' => $payment->amount,
                                    'currency' => 'PHP',
                                    'status' => 'paid',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function paymongoSignature(string $payload): string
{
    $timestamp = '1496734173';
    $signature = hash_hmac(
        'sha256',
        $timestamp.'.'.$payload,
        'whsec_online_payments',
    );

    return 't='.$timestamp.',te='.$signature.',li=';
}

function setPaymongoPaymentMethodsEnvironment(?string $paymentMethods): void
{
    if ($paymentMethods === null) {
        putenv('PAYMONGO_PAYMENT_METHODS');
        unset($_ENV['PAYMONGO_PAYMENT_METHODS'], $_SERVER['PAYMONGO_PAYMENT_METHODS']);

        return;
    }

    putenv('PAYMONGO_PAYMENT_METHODS='.$paymentMethods);
    $_ENV['PAYMONGO_PAYMENT_METHODS'] = $paymentMethods;
    $_SERVER['PAYMONGO_PAYMENT_METHODS'] = $paymentMethods;
}
