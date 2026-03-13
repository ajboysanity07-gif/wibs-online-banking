<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
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
