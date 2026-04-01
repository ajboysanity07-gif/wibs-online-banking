<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    if (! Schema::hasTable('wsvmaster')) {
        Schema::create('wsvmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('svnumber');
            $table->string('svtype')->nullable();
            $table->string('typecode')->nullable();
            $table->decimal('mortuary', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('wbalance', 12, 2)->default(0);
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
            $table->string('controlno')->nullable();
        });
    }

    if (! Schema::hasTable('wsavled')) {
        Schema::create('wsavled', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('svnumber');
            $table->string('svtype')->nullable();
            $table->dateTime('date_in')->nullable();
            $table->decimal('deposit', 12, 2)->default(0);
            $table->decimal('withdrawal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('controlno')->nullable();
        });
    }
});

test('admin can view member accounts summary', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000111',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-1',
        'lntype' => 'Regular',
        'principal' => 1200,
        'balance' => 600,
        'lastmove' => Carbon::parse('2024-01-15 10:00:00')->toDateTimeString(),
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-2',
        'lntype' => 'Short',
        'principal' => 800,
        'balance' => 300,
        'lastmove' => Carbon::parse('2024-02-10 10:00:00')->toDateTimeString(),
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => '999999',
        'lnnumber' => 'LN-OTHER',
        'lntype' => 'Other',
        'principal' => 900,
        'balance' => 900,
        'lastmove' => Carbon::parse('2024-03-10 10:00:00')->toDateTimeString(),
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-1',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 100,
        'balance' => 500,
        'wbalance' => 400,
        'lastmove' => Carbon::parse('2024-02-12 09:00:00')->toDateTimeString(),
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-2',
        'svtype' => 'Regular',
        'typecode' => '02',
        'mortuary' => 50,
        'balance' => 900,
        'wbalance' => 800,
        'lastmove' => Carbon::parse('2024-03-01 09:00:00')->toDateTimeString(),
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-1',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-11 09:00:00')->toDateTimeString(),
        'deposit' => 200,
        'withdrawal' => 0,
        'balance' => 450,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-1',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-15 09:00:00')->toDateTimeString(),
        'deposit' => 100,
        'withdrawal' => 0,
        'balance' => 550,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/admin/api/members/{$member->user_id}/accounts/summary");

    $response->assertOk()->assertJsonStructure([
        'ok',
        'data' => [
            'summary' => [
                'loanBalanceLeft',
                'currentLoanSecurityBalance',
                'currentLoanSecurityTotal',
                'lastLoanTransactionDate',
                'lastLoanSecurityTransactionDate',
                'recentLoans',
                'recentLoanSecurity',
            ],
        ],
    ]);

    expect((float) $response->json('data.summary.loanBalanceLeft'))->toBe(900.0);
    expect((float) $response->json('data.summary.currentLoanSecurityBalance'))->toBe(550.0);
    expect((float) $response->json('data.summary.currentLoanSecurityTotal'))->toBe(600.0);
    expect($response->json('data.summary.lastLoanTransactionDate'))->toBe(
        '2024-02-10 10:00:00'
    );
    expect($response->json('data.summary.lastLoanSecurityTransactionDate'))->toBe(
        '2024-02-15 09:00:00'
    );
    expect($response->json('data.summary.recentLoanSecurity'))->toHaveCount(1);
    expect($response->json('data.summary.recentLoanSecurity.0.svnumber'))->toBe('SV-1');

    $recentLoan = $response->json('data.summary.recentLoans.0');

    expect($recentLoan['lnnumber'])->toBe('LN-2');
    expect((float) $recentLoan['initial'])->toBe(800.0);
});

test('admin summary tolerates missing wbalance on loan security master', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000113',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    Schema::drop('wsvmaster');
    Schema::create('wsvmaster', function (Blueprint $table) {
        $table->string('acctno');
        $table->string('svnumber');
        $table->string('svtype')->nullable();
        $table->string('typecode')->nullable();
        $table->decimal('mortuary', 12, 2)->default(0);
        $table->decimal('balance', 12, 2)->default(0);
        $table->dateTime('lastmove')->nullable();
    });

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-113',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 20,
        'balance' => 150,
        'lastmove' => Carbon::parse('2024-03-05 09:00:00')->toDateTimeString(),
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-113',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-03-06 09:00:00')->toDateTimeString(),
        'deposit' => 0,
        'withdrawal' => 0,
        'balance' => 150,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/admin/api/members/{$member->user_id}/accounts/summary");

    $response->assertOk();
    expect((float) $response->json('data.summary.currentLoanSecurityBalance'))
        ->toBe(150.0);
    expect((float) $response->json('data.summary.currentLoanSecurityTotal'))
        ->toBe(170.0);
    expect((float) $response->json('data.summary.recentLoanSecurity.0.wbalance'))
        ->toBe(0.0);
});

test('recent account actions are paginated and ordered', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000222',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => '001',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-15 08:00:00')->toDateTimeString(),
        'principal' => 1000,
        'payments' => 0,
        'balance' => 1000,
        'debit' => 0,
        'controlno' => 'CTRL-101',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => '004',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-15 08:00:00')->toDateTimeString(),
        'principal' => 0,
        'payments' => 50,
        'balance' => 950,
        'debit' => 0,
        'controlno' => 'CTRL-202',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => '002',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-10 08:00:00')->toDateTimeString(),
        'principal' => 0,
        'payments' => 200,
        'balance' => 800,
        'debit' => 0,
        'controlno' => 'CTRL-050',
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => '003',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-05 08:00:00')->toDateTimeString(),
        'principal' => 0,
        'payments' => 150,
        'balance' => 650,
        'debit' => 0,
        'controlno' => 'CTRL-001',
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => '100',
        'svtype' => 'Regular',
        'typecode' => '02',
        'mortuary' => 0,
        'balance' => 500,
        'wbalance' => 500,
        'lastmove' => Carbon::parse('2024-02-14 08:00:00')->toDateTimeString(),
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => '101',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 300,
        'wbalance' => 300,
        'lastmove' => Carbon::parse('2024-02-12 08:00:00')->toDateTimeString(),
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => '102',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 400,
        'wbalance' => 400,
        'lastmove' => Carbon::parse('2024-02-01 08:00:00')->toDateTimeString(),
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => '100',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-14 08:00:00')->toDateTimeString(),
        'deposit' => 500,
        'withdrawal' => 0,
        'balance' => 500,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => '101',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-12 08:00:00')->toDateTimeString(),
        'deposit' => 0,
        'withdrawal' => 200,
        'balance' => 300,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => '102',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-01 08:00:00')->toDateTimeString(),
        'deposit' => 100,
        'withdrawal' => 0,
        'balance' => 400,
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/admin/api/members/{$member->user_id}/accounts/actions");

    $response->assertOk();

    expect($response->json('data.items'))->toHaveCount(5);
    expect($response->json('data.meta.perPage'))->toBe(5);
    expect($response->json('data.meta.total'))->toBe(6);
    expect($response->json('data.items.0.ln_sv_number'))->toBe('LN004');
    expect($response->json('data.items.0.source'))->toBe('LOAN');
    expect($response->json('data.items.0.control_no'))->toBe('CTRL-202');
    $numbers = collect($response->json('data.items'))->pluck('ln_sv_number')->all();

    expect($numbers)->not->toContain('SV100');
    expect($numbers)->toContain('SV101');
    expect($numbers)->not->toContain('SV102');
});

test('member loans endpoint is paginated', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000555',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    foreach (range(1, 7) as $index) {
        DB::table('wlnmaster')->insert([
            'acctno' => $member->acctno,
            'lnnumber' => sprintf('LN-%02d', $index),
            'lntype' => 'Regular',
            'principal' => 1000 + $index,
            'balance' => 500 + $index,
            'lastmove' => Carbon::parse("2024-02-{$index} 10:00:00")
                ->toDateTimeString(),
        ]);
    }

    $response = $this->actingAs($admin)->getJson(
        "/admin/api/members/{$member->user_id}/accounts/loans?perPage=5&page=1",
    );

    $response->assertOk();

    expect($response->json('data.items'))->toHaveCount(5);
    expect($response->json('data.meta.total'))->toBe(7);
    expect($response->json('data.meta.lastPage'))->toBe(2);
});

test('member loan security endpoint is paginated', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000556',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    foreach (range(1, 6) as $index) {
        $typecode = $index < 3 ? '02' : '01';

        DB::table('wsvmaster')->insert([
            'acctno' => $member->acctno,
            'svnumber' => sprintf('SV-%02d', $index),
            'svtype' => 'Regular',
            'typecode' => $typecode,
            'mortuary' => 0,
            'balance' => 0,
            'wbalance' => 0,
            'lastmove' => null,
        ]);

        DB::table('wsavled')->insert([
            'acctno' => $member->acctno,
            'svnumber' => sprintf('SV-%02d', $index),
            'svtype' => 'Regular',
            'date_in' => Carbon::parse("2024-03-{$index} 09:00:00")
                ->toDateTimeString(),
            'deposit' => 100 + $index,
            'withdrawal' => 50 + $index,
            'balance' => 500 + $index,
        ]);
    }

    $response = $this->actingAs($admin)->getJson(
        "/admin/api/members/{$member->user_id}/accounts/savings?perPage=5&page=1",
    );

    $response->assertOk();

    $response->assertJsonStructure([
        'data' => [
            'items' => [
                [
                    'svnumber',
                    'svtype',
                    'date_in',
                    'deposit',
                    'withdrawal',
                    'balance',
                ],
            ],
        ],
    ]);

    $response->assertJsonMissingPath('data.items.0.mortuary');
    $response->assertJsonMissingPath('data.items.0.wbalance');
    $response->assertJsonMissingPath('data.items.0.lastmove');

    expect($response->json('data.items'))->toHaveCount(4);
    expect($response->json('data.meta.total'))->toBe(4);
    expect($response->json('data.meta.lastPage'))->toBe(1);
    expect($response->json('data.items.0.svnumber'))->toBe('SV-06');
    expect($response->json('data.items.0.date_in'))->toBe(
        '2024-03-06 09:00:00'
    );

    $numbers = collect($response->json('data.items'))->pluck('svnumber')->all();
    expect($numbers)->not->toContain('SV-01');
    expect($numbers)->not->toContain('SV-02');
});

test('non-admin users cannot access member account endpoints', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000333',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/admin/api/members/{$member->user_id}/accounts/summary");

    $response->assertForbidden();
});
