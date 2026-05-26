<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\PaymongoLoanPayment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->decimal('balance', 12, 2)->default(0);
        });
    }

    if (! Schema::hasTable('wlnled')) {
        Schema::create('wlnled', function (Blueprint $table): void {
            $table->string('acctno')->nullable();
            $table->string('lnnumber')->nullable();
            $table->decimal('payments', 12, 2)->default(0);
        });
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

test('admin can view paid paymongo payments by default', function () {
    $admin = adminUser();
    $paidPayment = paymongoPayment([
        'acctno' => '000101',
        'loan_number' => 'LN-101',
        'status' => PaymongoLoanPayment::STATUS_PAID,
        'provider_reference_number' => 'PM-PAID-101',
    ]);

    paymongoPayment([
        'acctno' => '000102',
        'loan_number' => 'LN-102',
        'status' => PaymongoLoanPayment::STATUS_PENDING,
        'provider_reference_number' => 'PM-PENDING-102',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.paymongo-reconciliation.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/paymongo-reconciliation')
            ->where('payments.filters.status', PaymongoLoanPayment::STATUS_PAID)
            ->has('payments.items', 1)
            ->where('payments.items.0.id', $paidPayment->getKey())
            ->where('payments.items.0.provider_reference_number', 'PM-PAID-101'));
});

test('admin reconciliation page includes paid payment summary totals', function () {
    $admin = adminUser();

    paymongoPayment([
        'status' => PaymongoLoanPayment::STATUS_PAID,
        'base_amount_cents' => 100000,
        'service_fee_cents' => 2562,
        'gross_amount_cents' => 102562,
    ]);

    paymongoPayment([
        'status' => PaymongoLoanPayment::STATUS_PAID,
        'reconciliation_status' => PaymongoLoanPayment::RECONCILIATION_RECONCILED,
        'base_amount_cents' => 50000,
        'service_fee_cents' => 1500,
        'gross_amount_cents' => 51500,
    ]);

    paymongoPayment([
        'status' => PaymongoLoanPayment::STATUS_PAID,
        'base_amount_cents' => 250000,
        'service_fee_cents' => 5000,
        'gross_amount_cents' => 255000,
    ]);

    paymongoPayment([
        'status' => PaymongoLoanPayment::STATUS_PENDING,
        'base_amount_cents' => 999999,
        'service_fee_cents' => 9999,
        'gross_amount_cents' => 1009998,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.paymongo-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('payments.summary.paid_unreconciled_count', 2)
            ->where('payments.summary.reconciled_count', 1)
            ->where('payments.summary.total_loan_payments', 4000)
            ->where('payments.summary.total_service_fees', 90.62));
});

test('paid paymongo payment can be marked reconciled by admin', function () {
    Carbon::setTestNow('2026-05-11 09:30:00');

    $admin = adminUser();
    $payment = paymongoPayment([
        'status' => PaymongoLoanPayment::STATUS_PAID,
    ]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.paymongo-reconciliation.update', $payment), [
            'desktop_reference_no' => 'DESK-1001',
            'official_receipt_no' => 'OR-88991',
            'reconciliation_notes' => 'Posted manually in Desktop WIBS.',
        ]);

    $response->assertRedirect();

    $payment->refresh();

    expect($payment->reconciliation_status)
        ->toBe(PaymongoLoanPayment::RECONCILIATION_RECONCILED)
        ->and($payment->reconciled_at?->toDateTimeString())
        ->toBe('2026-05-11 09:30:00')
        ->and($payment->reconciled_by)
        ->toBe($admin->getKey())
        ->and($payment->desktop_reference_no)
        ->toBe('DESK-1001')
        ->and($payment->official_receipt_no)
        ->toBe('OR-88991')
        ->and($payment->reconciliation_notes)
        ->toBe('Posted manually in Desktop WIBS.');
});

test('pending and failed paymongo payments cannot be reconciled', function (string $status) {
    $admin = adminUser();
    $payment = paymongoPayment([
        'status' => $status,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.paymongo-reconciliation.index', ['status' => $status]))
        ->patch(route('admin.paymongo-reconciliation.update', $payment), [
            'desktop_reference_no' => 'DESK-1002',
            'official_receipt_no' => 'OR-88992',
            'reconciliation_notes' => 'Should not save.',
        ])
        ->assertRedirect(route('admin.paymongo-reconciliation.index', ['status' => $status]))
        ->assertSessionHasErrors('payment');

    $payment->refresh();

    expect($payment->reconciliation_status)
        ->toBe(PaymongoLoanPayment::RECONCILIATION_UNRECONCILED)
        ->and($payment->reconciled_at)
        ->toBeNull()
        ->and($payment->reconciled_by)
        ->toBeNull()
        ->and($payment->desktop_reference_no)
        ->toBeNull()
        ->and($payment->official_receipt_no)
        ->toBeNull()
        ->and($payment->reconciliation_notes)
        ->toBeNull();
})->with([
    PaymongoLoanPayment::STATUS_PENDING,
    PaymongoLoanPayment::STATUS_FAILED,
]);

test('reconciliation does not post to wlnled or update wlnmaster balance', function () {
    $admin = adminUser();
    $payment = paymongoPayment([
        'acctno' => '000201',
        'loan_number' => 'LN-201',
        'status' => PaymongoLoanPayment::STATUS_PAID,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => '000201',
        'lnnumber' => 'LN-201',
        'balance' => 5000,
    ]);

    expect(DB::table('wlnled')->count())->toBe(0);

    $this->actingAs($admin)
        ->patch(route('admin.paymongo-reconciliation.update', $payment), [
            'desktop_reference_no' => 'DESK-2001',
            'official_receipt_no' => 'OR-2001',
            'reconciliation_notes' => 'Desktop posting only.',
        ])
        ->assertRedirect();

    expect(DB::table('wlnled')->count())->toBe(0)
        ->and((float) DB::table('wlnmaster')->where('lnnumber', 'LN-201')->value('balance'))
        ->toBe(5000.0);
});

test('non-admin users cannot access paymongo reconciliation routes', function () {
    $user = AppUser::factory()->create();
    $payment = paymongoPayment([
        'status' => PaymongoLoanPayment::STATUS_PAID,
    ]);

    $this->actingAs($user)
        ->get(route('admin.paymongo-reconciliation.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.paymongo-reconciliation.update', $payment), [
            'desktop_reference_no' => 'DESK-3001',
        ])
        ->assertForbidden();
});

function adminUser(): AppUser
{
    $admin = AppUser::factory()->create();

    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function paymongoPayment(array $overrides = []): PaymongoLoanPayment
{
    return PaymongoLoanPayment::query()->create([
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
        'provider_checkout_session_id' => 'cs_test_'.fake()->unique()->numerify('######'),
        'provider_payment_intent_id' => 'pi_test_'.fake()->unique()->numerify('######'),
        'provider_reference_number' => 'PM-REF-'.fake()->unique()->numerify('######'),
        'metadata' => [],
        'paid_at' => now(),
        ...$overrides,
    ]);
}
