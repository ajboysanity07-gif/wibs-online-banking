<?php

use App\Models\AppUser as User;
use App\Services\Admin\MemberAccounts\MemberAccountsService;
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
            $table->decimal('mortuary', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('wbalance', 12, 2)->default(0);
            $table->dateTime('lastmove')->nullable();
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
        });
    }
});

test('member account summary aggregates balances', function () {
    $member = User::factory()->create([
        'acctno' => '000444',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-9',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 700,
        'lastmove' => Carbon::parse('2024-02-01 10:00:00')->toDateTimeString(),
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-9',
        'svtype' => 'Regular',
        'mortuary' => 150,
        'balance' => 650,
        'wbalance' => 500,
        'lastmove' => Carbon::parse('2024-02-03 10:00:00')->toDateTimeString(),
    ]);

    $service = app(MemberAccountsService::class);
    $summary = $service->getSummary($member);

    expect($summary['loanBalanceLeft'])->toBe(700.0);
    expect($summary['currentLoanSecurityBalance'])->toBe(650.0);
    expect($summary['currentLoanSecurityTotal'])->toBe(800.0);
    expect($summary['lastLoanTransactionDate'])->toBe('2024-02-01 10:00:00');
    expect($summary['lastLoanSecurityTransactionDate'])->toBe('2024-02-03 10:00:00');
});

test('dashboard summary favors the latest loan security ledger balance', function () {
    $member = User::factory()->create([
        'acctno' => '000445',
    ]);

    if (Schema::hasTable('wsavled')) {
        Schema::drop('wsavled');
    }

    Schema::create('wsavled', function (Blueprint $table) {
        $table->string('acctno');
        $table->string('svnumber');
        $table->string('svtype')->nullable();
        $table->string('typecode')->nullable();
        $table->dateTime('date_in')->nullable();
        $table->decimal('deposit', 12, 2)->default(0);
        $table->decimal('withdrawal', 12, 2)->default(0);
        $table->decimal('balance', 12, 2)->default(0);
    });

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-10',
        'svtype' => 'Regular',
        'mortuary' => 25,
        'balance' => 125,
        'wbalance' => 100,
        'lastmove' => Carbon::parse('2024-02-01 09:00:00')->toDateTimeString(),
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-10',
        'svtype' => 'Regular',
        'typecode' => '01',
        'date_in' => Carbon::parse('2024-02-02 10:00:00')->toDateTimeString(),
        'deposit' => 0,
        'withdrawal' => 0,
        'balance' => 250,
    ]);

    $service = app(MemberAccountsService::class);
    $summary = $service->getDashboardSummary($member);

    expect($summary['currentLoanSecurityBalance'])->toBe(250.0);
    expect($summary['currentLoanSecurityTotal'])->toBe(250.0);
    expect($summary['lastLoanSecurityTransactionDate'])->toBe('2024-02-02 10:00:00');
});
