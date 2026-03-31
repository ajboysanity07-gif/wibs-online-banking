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
