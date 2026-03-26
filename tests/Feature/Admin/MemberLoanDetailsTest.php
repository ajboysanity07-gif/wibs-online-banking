<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Repositories\Admin\MemberLoansRepository;
use App\Services\Admin\MemberLoans\MemberLoanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->dateTime('lastmove')->nullable();
        });
    }

    if (! Schema::hasTable('wlnled')) {
        Schema::create('wlnled', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->dateTime('date_in')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('payments', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('accruedint', 12, 2)->default(0);
            $table->string('lnstatus')->nullable();
            $table->string('controlno')->nullable();
            $table->string('transno')->nullable();
        });
    }

    if (! Schema::hasTable('Amortsched')) {
        Schema::create('Amortsched', function (Blueprint $table) {
            $table->string('lnnumber');
            $table->dateTime('Date_pay')->nullable();
            $table->decimal('Amortization', 12, 2)->default(0);
            $table->decimal('Interest', 12, 2)->default(0);
            $table->decimal('Balance', 12, 2)->default(0);
            $table->string('controlno')->nullable();
        });
    }
});

test('admin can view loan schedule page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000901']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-901',
        'lntype' => 'Regular',
        'principal' => 1200,
        'balance' => 850,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loan-schedule', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-901',
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-loan-schedule')
            ->has('member')
            ->has('summary')
            ->has('schedule')
            ->where('loan.lnnumber', 'LN-901'));
});

test('admin can view loan payments page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000902']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-902',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 1200,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loan-payments', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-902',
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-loan-payments')
            ->has('member')
            ->has('summary')
            ->has('payments')
            ->where('loan.lnnumber', 'LN-902'));
});

test('admin can view loan payments print preview', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000912']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-912',
        'lntype' => 'Regular',
        'principal' => 1800,
        'balance' => 1400,
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-912',
        'lntype' => 'Regular',
        'date_in' => Carbon::now()->toDateTimeString(),
        'principal' => 150,
        'payments' => 150,
        'balance' => 1250,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loan-payments-print', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-912',
            'range' => 'all',
        ]));

    $response->assertOk();
    $response->assertViewIs('reports.loan-payments');
    $response->assertSee('Loan Payment Transaction Report');
    $response->assertSee('Loan Payment Report');
    $response->assertSee('This document summarizes recorded loan payment transactions');
    $response->assertSee('Balances shown are based on records available at the time this report was generated.');
    $response->assertSee('Certification');
    $response->assertSee('This is a system-generated report prepared from the loan payment records');
    $response->assertSee('otherwise required by policy, this document is valid without handwritten signature.');
    $response->assertSee('Prepared by');
    $response->assertSee('Checked by');
    $response->assertSee('Noted by');
    $response->assertSee('Received by / Borrower');
    $response->assertSee('Date');
    $response->assertSee('window.print', false);
});

test('non-admin users cannot access loan detail pages', function () {
    $user = User::factory()->create();
    $member = User::factory()->create(['acctno' => '000903']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-903',
    ]);

    $this->actingAs($user)
        ->get(route('admin.members.loan-schedule', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-903',
        ]))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.members.loan-payments', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-903',
        ]))
        ->assertForbidden();
});

test('loan ownership is enforced for loan detail routes', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000904']);
    $other = User::factory()->create(['acctno' => '000905']);

    DB::table('wlnmaster')->insert([
        'acctno' => $other->acctno,
        'lnnumber' => 'LN-905',
        'balance' => 300,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.members.loan-schedule', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-905',
        ]))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.members.loan-payments', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-905',
        ]))
        ->assertNotFound();
});

test('loan summary uses balance, next scheduled payment, and last payment', function () {
    $member = User::factory()->create(['acctno' => '000906']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-906',
        'balance' => 500,
    ]);

    DB::table('Amortsched')->insert([
        'lnnumber' => 'LN-906',
        'Date_pay' => Carbon::parse('2024-03-01 00:00:00')->toDateTimeString(),
        'Amortization' => 120,
        'Interest' => 15,
        'Balance' => 380,
        'controlno' => 'SCH-1',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-906',
        'date_in' => Carbon::parse('2024-02-01 00:00:00')->toDateTimeString(),
        'payments' => 0,
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-906',
        'date_in' => Carbon::parse('2024-02-10 00:00:00')->toDateTimeString(),
        'payments' => 200,
    ]);

    Carbon::setTestNow(Carbon::parse('2024-02-15 00:00:00'));

    $service = app(MemberLoanService::class);
    $payload = $service->getSchedulePageData($member, 'LN-906');

    expect($payload['summary']['balance'])->toBe(500.0);
    expect($payload['summary']['nextPaymentDate'])->toBe('2024-03-01 00:00:00');
    expect($payload['summary']['lastPaymentDate'])->toBe('2024-02-10 00:00:00');

    Carbon::setTestNow();
});

test('schedule api returns ordered entries', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000907']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-907',
    ]);

    DB::table('Amortsched')->insert([
        'lnnumber' => 'LN-907',
        'Date_pay' => Carbon::parse('2024-04-15 00:00:00')->toDateTimeString(),
        'Amortization' => 100,
        'Interest' => 10,
        'Balance' => 900,
    ]);

    DB::table('Amortsched')->insert([
        'lnnumber' => 'LN-907',
        'Date_pay' => Carbon::parse('2024-03-15 00:00:00')->toDateTimeString(),
        'Amortization' => 90,
        'Interest' => 9,
        'Balance' => 990,
    ]);

    $response = $this->actingAs($admin)->getJson(
        "/admin/api/members/{$member->user_id}/loans/LN-907/schedule",
    );

    $response->assertOk()->assertJsonStructure([
        'ok',
        'data' => ['items'],
    ]);

    expect($response->json('data.items.0.date_pay'))->toBe('2024-03-15 00:00:00');
});

test('payments api respects date filters', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000908']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-908',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-908',
        'date_in' => Carbon::parse('2024-01-10 00:00:00')->toDateTimeString(),
        'payments' => 100,
        'balance' => 900,
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-908',
        'date_in' => Carbon::parse('2024-02-10 00:00:00')->toDateTimeString(),
        'payments' => 150,
        'balance' => 750,
    ]);

    $response = $this->actingAs($admin)->getJson(
        "/admin/api/members/{$member->user_id}/loans/LN-908/payments?range=custom&start=2024-02-01&end=2024-02-28",
    );

    $response->assertOk();

    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0.date_in'))->toBe('2024-02-10 00:00:00');
});

test('payments api excludes rows without principal or payments', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000920']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-920',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-920',
        'date_in' => Carbon::parse('2024-01-10 00:00:00')->toDateTimeString(),
        'principal' => 0,
        'payments' => 0,
        'debit' => 200,
        'credit' => 0,
        'transno' => 'TR-EXCLUDE',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-920',
        'date_in' => Carbon::parse('2024-01-11 00:00:00')->toDateTimeString(),
        'principal' => 250,
        'payments' => 0,
        'transno' => 'TR-PRINCIPAL',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-920',
        'date_in' => Carbon::parse('2024-01-12 00:00:00')->toDateTimeString(),
        'principal' => 0,
        'payments' => 175,
        'transno' => 'TR-PAYMENT',
    ]);

    $response = $this->actingAs($admin)->getJson(
        "/admin/api/members/{$member->user_id}/loans/LN-920/payments?range=all",
    );

    $response->assertOk();

    $references = collect($response->json('data.items'))
        ->pluck('reference_no')
        ->all();

    expect($references)
        ->toHaveCount(2)
        ->toContain('TR-PRINCIPAL', 'TR-PAYMENT')
        ->not->toContain('TR-EXCLUDE');
});

test('export payments exclude rows without principal or payments', function () {
    $member = User::factory()->create(['acctno' => '000921']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-921',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-921',
        'date_in' => Carbon::parse('2024-01-10 00:00:00')->toDateTimeString(),
        'principal' => 0,
        'payments' => 0,
        'debit' => 200,
        'credit' => 0,
        'transno' => 'TR-EXCLUDE',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-921',
        'date_in' => Carbon::parse('2024-01-11 00:00:00')->toDateTimeString(),
        'principal' => 250,
        'payments' => 0,
        'transno' => 'TR-PRINCIPAL',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-921',
        'date_in' => Carbon::parse('2024-01-12 00:00:00')->toDateTimeString(),
        'principal' => 0,
        'payments' => 175,
        'transno' => 'TR-PAYMENT',
    ]);

    $repository = app(MemberLoansRepository::class);
    $payments = $repository->getPaymentsForExport($member->acctno, 'LN-921', null, null);

    $references = $payments->pluck('transno')->all();

    expect($references)
        ->toHaveCount(2)
        ->toContain('TR-PRINCIPAL', 'TR-PAYMENT')
        ->not->toContain('TR-EXCLUDE');
});

test('export request validates format and date range', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '000909']);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-909',
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.members.loan-payments-export', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-909',
            'format' => 'docx',
        ]))
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->getJson(route('admin.members.loan-payments-export', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-909',
            'format' => 'csv',
            'range' => 'custom',
        ]))
        ->assertUnprocessable();
});

test('export filename uses member lastname and range', function () {
    $admin = User::factory()->create(['username' => 'Admin User']);
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create([
        'acctno' => '000910',
        'username' => 'Doe',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-910',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-910',
        'date_in' => Carbon::parse('2024-02-10 00:00:00')->toDateTimeString(),
        'payments' => 100,
        'balance' => 900,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.members.loan-payments-export', [
        'user' => $member->user_id,
        'loanNumber' => 'LN-910',
        'format' => 'csv',
        'range' => 'custom',
        'start' => '2024-02-01',
        'end' => '2024-02-28',
    ]));

    $response->assertOk();

    $disposition = $response->headers->get('content-disposition');

    expect($disposition)->toContain('doe-lnpayment-2024-02-01-2024-02-28.csv');
});

test('loan payment report template uses simplified columns', function () {
    $payments = collect([
        (object) [
            'date_in' => '2024-02-10 00:00:00',
            'mreference' => 'REF-123',
            'transno' => null,
            'controlno' => null,
            'principal' => 200,
            'payments' => 150,
            'balance' => 850,
        ],
    ]);

    $html = view('reports.loan-payments', [
        'logoData' => null,
        'companyName' => 'WIBS Cooperative',
        'memberName' => 'Jane Doe',
        'memberAccountNo' => '000001',
        'loanNumber' => 'LN-100',
        'reportStart' => Carbon::parse('2024-02-01'),
        'reportEnd' => Carbon::parse('2024-02-28'),
        'generatedAt' => Carbon::parse('2024-03-01 10:00:00'),
        'generatedBy' => 'Admin User',
        'payments' => $payments,
        'openingBalance' => 1000,
        'closingBalance' => 900,
    ])->render();

    expect($html)
        ->toContain('Loan Payment Report')
        ->toContain('This document summarizes recorded loan payment transactions')
        ->toContain('Balances shown are based on records available at the time this report was generated.')
        ->toContain('Certification')
        ->toContain('This is a system-generated report prepared from the loan payment records')
        ->toContain('Prepared by')
        ->toContain('Checked by')
        ->toContain('Noted by')
        ->toContain('Received by / Borrower')
        ->toContain('Date')
        ->toContain('Transaction Date')
        ->toContain('Reference No')
        ->toContain('Principal')
        ->toContain('Payment')
        ->toContain('Balance')
        ->not->toContain('Loan Type')
        ->not->toContain('Debit')
        ->not->toContain('Credit')
        ->not->toContain('Accrued Interest')
        ->not->toContain('Status')
        ->not->toContain('Control No');
});
