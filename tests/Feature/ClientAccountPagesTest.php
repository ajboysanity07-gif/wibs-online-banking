<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\MemberApplicationProfile;
use App\Models\PaymongoLoanPayment;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
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

/**
 * @param  array<string, mixed>  $memberOverrides
 */
function createApprovedClientUser(
    string $acctno,
    array $memberOverrides = [],
): User {
    $user = User::factory()->create([
        'acctno' => $acctno,
    ]);

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert(array_merge([
        'acctno' => $acctno,
        'bname' => 'Member, Client',
        'fname' => 'Client',
        'lname' => 'Member',
        'birthday' => '1992-01-15',
        'address' => '100 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Staff',
    ], $memberOverrides));

    return $user;
}

/**
 * @param  array<string, mixed>  $loanOverrides
 * @param  array<string, mixed>  $securityOverrides
 */
function seedLoanSecurityPaymentAccounts(
    User $user,
    array $loanOverrides = [],
    array $securityOverrides = [],
): void {
    DB::table('wlnmaster')->insert(array_merge([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-SEC-001',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 1200,
        'lastmove' => Carbon::parse('2026-05-01 08:00:00')->toDateTimeString(),
        'initial' => 1500,
    ], $loanOverrides));

    DB::table('wsvmaster')->insert(array_merge([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-SEC-001',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 1000,
        'wbalance' => 1000,
        'lastmove' => Carbon::parse('2026-05-01 08:00:00')->toDateTimeString(),
    ], $securityOverrides));
}

test('approved client can view the dashboard profile page', function () {
    $user = User::factory()->create([
        'acctno' => '000700',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Ana',
        'fname' => 'Ana',
        'lname' => 'Member',
        'birthday' => '1991-05-10',
        'address' => '123 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Clerk',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->has('member')
            ->has('summary')
            ->has('recentAccountActions')
            ->where('member.acctno', '000700')
            ->where('member.status', 'active')
            ->where('member.reviewed_by', null)
            ->where('member.reviewed_at', null));
});

test('client dashboard summary uses latest loan security ledger balance', function () {
    $user = User::factory()->create([
        'acctno' => '000704',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Bea',
        'fname' => 'Bea',
        'lname' => 'Member',
        'birthday' => '1990-03-08',
        'address' => '456 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Staff',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-704',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 94.72,
        'wbalance' => 94.72,
        'lastmove' => null,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-704',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2025-07-18 00:00:00')->toDateTimeString(),
        'deposit' => 0,
        'withdrawal' => 0,
        'balance' => 37694.72,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->where('summary.currentLoanSecurityBalance', 37694.72)
            ->where('summary.currentLoanSecurityTotal', 37694.72));
});

test('client dashboard summary loads when wsvmaster lacks mortuary and wbalance', function () {
    $user = User::factory()->create([
        'acctno' => '000711',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Cara',
        'fname' => 'Cara',
        'lname' => 'Member',
        'birthday' => '1990-01-10',
        'address' => '101 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Staff',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    Schema::drop('wsvmaster');
    Schema::create('wsvmaster', function (Blueprint $table) {
        $table->string('acctno');
        $table->string('svnumber');
        $table->string('svtype')->nullable();
        $table->string('typecode')->nullable();
        $table->decimal('balance', 12, 2)->default(0);
        $table->dateTime('lastmove')->nullable();
    });

    DB::table('wsvmaster')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-711',
        'svtype' => 'Regular',
        'typecode' => '01',
        'balance' => 120.5,
        'lastmove' => Carbon::parse('2024-06-10 09:00:00')->toDateTimeString(),
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-711',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-06-11 09:00:00')->toDateTimeString(),
        'deposit' => 0,
        'withdrawal' => 0,
        'balance' => 120.5,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->where('summaryError', null)
            ->where('summary.currentLoanSecurityBalance', 120.5)
            ->where('summary.currentLoanSecurityTotal', 120.5)
            ->where('summary.lastLoanSecurityTransactionDate', '2024-06-11 09:00:00'));
});

test('client dashboard summary falls back when ledger balance and date are missing', function () {
    $user = User::factory()->create([
        'acctno' => '000712',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Drew',
        'fname' => 'Drew',
        'lname' => 'Member',
        'birthday' => '1991-02-12',
        'address' => '202 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Staff',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    Schema::drop('wsavled');
    Schema::create('wsavled', function (Blueprint $table) {
        $table->string('acctno');
        $table->string('svnumber');
        $table->string('svtype')->nullable();
        $table->decimal('deposit', 12, 2)->default(0);
        $table->decimal('withdrawal', 12, 2)->default(0);
    });

    DB::table('wsvmaster')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-712',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 500,
        'wbalance' => 500,
        'lastmove' => Carbon::parse('2024-06-12 10:00:00')->toDateTimeString(),
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-712',
        'svtype' => 'Regular',
        'deposit' => 50,
        'withdrawal' => 0,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->where('summaryError', null)
            ->where('summary.currentLoanSecurityBalance', 500)
            ->where('summary.currentLoanSecurityTotal', 500)
            ->where('summary.lastLoanSecurityTransactionDate', '2024-06-12 10:00:00'));
});

test('approved client can view the loans page', function () {
    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Chris',
        'fname' => 'Chris',
        'lname' => 'Member',
        'birthday' => '1989-11-02',
        'address' => '789 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Technician',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('client.loans'));
    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->where('member.acctno', '000701')
            ->has('summary')
            ->has('loans'));
});

test('approved client can view the loan security page', function () {
    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Dana',
        'fname' => 'Dana',
        'lname' => 'Member',
        'birthday' => '1993-07-22',
        'address' => '321 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Assistant',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-701',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 0,
        'wbalance' => 0,
        'lastmove' => null,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-701',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-11 08:00:00')->toDateTimeString(),
        'deposit' => 250,
        'withdrawal' => 0,
        'balance' => 250,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('client.savings'));
    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/savings')
            ->where('member.acctno', '000701')
            ->has('summary')
            ->has('summary.currentLoanSecurityBalance')
            ->has('savings')
            ->has('savings.items', 1)
            ->where('savings.items.0.svnumber', 'SV-701')
            ->where('savings.items.0.svtype', 'Regular')
            ->where('savings.items.0.date_in', '2024-02-11 08:00:00')
            ->where('savings.items.0.deposit', 250)
            ->where('savings.items.0.withdrawal', 0));
});

test('approved client can view the loan schedule page', function () {
    $user = User::factory()->create([
        'acctno' => '000702',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Eli',
        'fname' => 'Eli',
        'lname' => 'Member',
        'birthday' => '1994-01-14',
        'address' => '654 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Associate',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-702',
        'lntype' => 'Regular',
        'principal' => 1200,
        'balance' => 850,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-schedule', ['loanNumber' => 'LN-702']));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-schedule')
            ->has('member')
            ->has('summary')
            ->has('schedule')
            ->where('loan.lnnumber', 'LN-702'));
});

test('approved client can view the loan payments page', function () {
    $user = User::factory()->create([
        'acctno' => '000703',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Fran',
        'fname' => 'Fran',
        'lname' => 'Member',
        'birthday' => '1995-09-09',
        'address' => '987 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Coordinator',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-703',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 1200,
    ]);
    DB::table('wsvmaster')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV-703',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 950,
        'wbalance' => 950,
        'lastmove' => Carbon::parse('2026-05-03 09:00:00')->toDateTimeString(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-payments', ['loanNumber' => 'LN-703']));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-payments')
            ->has('member')
            ->has('summary')
            ->has('securityPayment')
            ->has('payments')
            ->where('loan.lnnumber', 'LN-703')
            ->where('securityPayment.svnumber', 'SV-703')
            ->where('securityPayment.currentBalance', 950)
            ->where('securityPayment.minimumBalance', 500)
            ->where('securityPayment.maxPayable', 450));
});

test('client can pay a loan from security while leaving exactly 500', function () {
    $user = createApprovedClientUser('000713', [
        'bname' => 'Member, Iris',
        'fname' => 'Iris',
    ]);

    seedLoanSecurityPaymentAccounts($user, [
        'lnnumber' => 'LN-713',
        'balance' => 800,
    ], [
        'svnumber' => 'SV-713',
        'balance' => 1000,
        'wbalance' => 1000,
    ]);

    $movedAt = Carbon::parse('2026-05-19 09:45:00');
    Carbon::setTestNow($movedAt);

    try {
        $response = $this
            ->actingAs($user)
            ->from(route('client.loan-payments', ['loanNumber' => 'LN-713']))
            ->post(route('client.loan-payments.security', [
                'loanNumber' => 'LN-713',
            ]), [
                'amount' => 500,
            ]);

        $response
            ->assertRedirect(route('client.loan-payments', ['loanNumber' => 'LN-713']))
            ->assertInertiaFlash(
                'status',
                'Loan payment of 500.00 from loan security was applied successfully.',
            );

        expect((float) DB::table('wlnmaster')
            ->where('acctno', $user->acctno)
            ->where('lnnumber', 'LN-713')
            ->value('balance'))->toBe(300.0);
        expect(DB::table('wlnmaster')
            ->where('acctno', $user->acctno)
            ->where('lnnumber', 'LN-713')
            ->value('lastmove'))->toBe($movedAt->toDateTimeString());
        expect((float) DB::table('wsvmaster')
            ->where('acctno', $user->acctno)
            ->where('svnumber', 'SV-713')
            ->value('balance'))->toBe(500.0);
        expect((float) DB::table('wsvmaster')
            ->where('acctno', $user->acctno)
            ->where('svnumber', 'SV-713')
            ->value('wbalance'))->toBe(500.0);
        expect(DB::table('wsvmaster')
            ->where('acctno', $user->acctno)
            ->where('svnumber', 'SV-713')
            ->value('lastmove'))->toBe($movedAt->toDateTimeString());

        $loanLedger = DB::table('wlnled')
            ->where('acctno', $user->acctno)
            ->where('lnnumber', 'LN-713')
            ->first();
        $securityLedger = DB::table('wsavled')
            ->where('acctno', $user->acctno)
            ->where('svnumber', 'SV-713')
            ->first();

        expect($loanLedger)->not->toBeNull();
        expect((float) $loanLedger->payments)->toBe(500.0);
        expect((float) $loanLedger->balance)->toBe(300.0);
        expect((float) $loanLedger->principal)->toBe(0.0);
        expect($loanLedger->date_in)->toBe($movedAt->toDateTimeString());

        expect($securityLedger)->not->toBeNull();
        expect((float) $securityLedger->withdrawal)->toBe(500.0);
        expect((float) $securityLedger->balance)->toBe(500.0);
        expect((float) $securityLedger->deposit)->toBe(0.0);
        expect($securityLedger->date_in)->toBe($movedAt->toDateTimeString());
    } finally {
        Carbon::setTestNow();
    }
});

test('client loan security payment is capped at the remaining loan balance', function () {
    $user = createApprovedClientUser('000714', [
        'bname' => 'Member, June',
        'fname' => 'June',
    ]);

    seedLoanSecurityPaymentAccounts($user, [
        'lnnumber' => 'LN-714',
        'balance' => 250,
    ], [
        'svnumber' => 'SV-714',
        'balance' => 1000,
        'wbalance' => 1000,
    ]);

    $movedAt = Carbon::parse('2026-05-19 10:15:00');
    Carbon::setTestNow($movedAt);

    try {
        $response = $this
            ->actingAs($user)
            ->from(route('client.loan-payments', ['loanNumber' => 'LN-714']))
            ->post(route('client.loan-payments.security', [
                'loanNumber' => 'LN-714',
            ]), [
                'amount' => 400,
            ]);

        $response
            ->assertRedirect(route('client.loan-payments', ['loanNumber' => 'LN-714']))
            ->assertInertiaFlash(
                'status',
                'Loan payment of 250.00 from loan security was applied successfully.',
            );

        expect((float) DB::table('wlnmaster')
            ->where('acctno', $user->acctno)
            ->where('lnnumber', 'LN-714')
            ->value('balance'))->toBe(0.0);
        expect((float) DB::table('wsvmaster')
            ->where('acctno', $user->acctno)
            ->where('svnumber', 'SV-714')
            ->value('wbalance'))->toBe(750.0);

        $loanLedger = DB::table('wlnled')
            ->where('acctno', $user->acctno)
            ->where('lnnumber', 'LN-714')
            ->first();
        $securityLedger = DB::table('wsavled')
            ->where('acctno', $user->acctno)
            ->where('svnumber', 'SV-714')
            ->first();

        expect($loanLedger)->not->toBeNull();
        expect((float) $loanLedger->payments)->toBe(250.0);
        expect((float) $loanLedger->balance)->toBe(0.0);
        expect($loanLedger->date_in)->toBe($movedAt->toDateTimeString());

        expect($securityLedger)->not->toBeNull();
        expect((float) $securityLedger->withdrawal)->toBe(250.0);
        expect((float) $securityLedger->balance)->toBe(750.0);
    } finally {
        Carbon::setTestNow();
    }
});

test('client cannot pay a loan from security below the 500 minimum', function () {
    $user = createApprovedClientUser('000715', [
        'bname' => 'Member, Kai',
        'fname' => 'Kai',
    ]);

    seedLoanSecurityPaymentAccounts($user, [
        'lnnumber' => 'LN-715',
        'balance' => 1000,
    ], [
        'svnumber' => 'SV-715',
        'balance' => 900,
        'wbalance' => 900,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('client.loan-payments', ['loanNumber' => 'LN-715']))
        ->post(route('client.loan-payments.security', [
            'loanNumber' => 'LN-715',
        ]), [
            'amount' => 450,
        ]);

    $response
        ->assertRedirect(route('client.loan-payments', ['loanNumber' => 'LN-715']))
        ->assertInvalid([
            'amount' => 'maximum payable from loan security is 400.00',
        ]);

    expect((float) DB::table('wlnmaster')
        ->where('acctno', $user->acctno)
        ->where('lnnumber', 'LN-715')
        ->value('balance'))->toBe(1000.0);
    expect((float) DB::table('wsvmaster')
        ->where('acctno', $user->acctno)
        ->where('svnumber', 'SV-715')
        ->value('wbalance'))->toBe(900.0);
    expect(DB::table('wlnled')
        ->where('acctno', $user->acctno)
        ->where('lnnumber', 'LN-715')
        ->count())->toBe(0);
    expect(DB::table('wsavled')
        ->where('acctno', $user->acctno)
        ->where('svnumber', 'SV-715')
        ->count())->toBe(0);
});

test('client loan security payment validates the amount field', function (
    string $acctno,
    array $payload,
    string $message,
) {
    $user = createApprovedClientUser($acctno);

    seedLoanSecurityPaymentAccounts($user, [
        'lnnumber' => 'LN-'.$acctno,
    ], [
        'svnumber' => 'SV-'.$acctno,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('client.loan-payments', ['loanNumber' => 'LN-'.$acctno]))
        ->post(route('client.loan-payments.security', [
            'loanNumber' => 'LN-'.$acctno,
        ]), $payload);

    $response
        ->assertRedirect(route('client.loan-payments', ['loanNumber' => 'LN-'.$acctno]))
        ->assertInvalid([
            'amount' => $message,
        ]);
})->with([
    'missing amount' => ['000716', [], 'Enter an amount to pay from loan security.'],
    'non-numeric amount' => ['000717', ['amount' => 'abc'], 'Amount must be a valid number.'],
    'non-positive amount' => ['000718', ['amount' => 0], 'Enter an amount greater than zero.'],
]);

test('client can start a paymongo checkout for a loan payment', function () {
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
                'id' => 'cs_test_719',
                'type' => 'checkout_session',
                'attributes' => [
                    'checkout_url' => 'https://checkout.paymongo.com/cs_test_719',
                    'status' => 'active',
                    'reference_number' => 'PM-719',
                    'payment_method_types' => ['paymaya'],
                    'payment_intent' => [
                        'id' => 'pi_test_719',
                    ],
                    'expires_at' => 1778036400,
                ],
            ],
        ]),
    ]);

    $user = createApprovedClientUser('000719');

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-719',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 1200,
        'lastmove' => Carbon::parse('2026-05-01 08:00:00')->toDateTimeString(),
        'initial' => 1500,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('client.loan-payments.paymongo.store', [
            'loanNumber' => 'LN-719',
        ]), [
            'amount' => '400.00',
            'payment_method' => 'maya',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath(
            'checkout_url',
            'https://checkout.paymongo.com/cs_test_719',
        )
        ->assertJsonPath('payment_method', 'maya')
        ->assertJsonPath('base_amount', 400);

    $payment = PaymongoLoanPayment::query()->first();

    expect($payment)->not->toBeNull();
    expect($payment?->loan_number)->toBe('LN-719');
    expect($payment?->payment_method)->toBe('maya');
    expect($payment?->status)->toBe(PaymongoLoanPayment::STATUS_PENDING);
    expect($payment?->provider_checkout_session_id)->toBe('cs_test_719');
    expect($payment?->provider_payment_intent_id)->toBe('pi_test_719');

    Http::assertSent(function (HttpRequest $request) use ($payment): bool {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && data_get($payload, 'data.attributes.payment_method_types.0') === 'paymaya'
            && data_get($payload, 'data.attributes.line_items.0.amount') === 40000
            && data_get($payload, 'data.attributes.metadata.local_payment_id') === $payment?->getKey();
    });
});

test('client paymongo checkout rejects amounts above the outstanding balance', function () {
    $user = createApprovedClientUser('000720');

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-720',
        'lntype' => 'Regular',
        'principal' => 900,
        'balance' => 300,
        'lastmove' => Carbon::parse('2026-05-01 08:00:00')->toDateTimeString(),
        'initial' => 900,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson(route('client.loan-payments.paymongo.store', [
            'loanNumber' => 'LN-720',
        ]), [
            'amount' => '400.00',
            'payment_method' => 'gcash',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    expect($response->json('errors.amount.0'))
        ->toBe('Loan payment amount cannot exceed the outstanding balance.');
    expect(PaymongoLoanPayment::query()->count())->toBe(0);
});

test('client paymongo success redirects back with a status message', function () {
    $user = createApprovedClientUser('000721');

    $payment = PaymongoLoanPayment::query()->create([
        'user_id' => $user->getKey(),
        'acctno' => $user->acctno,
        'loan_number' => 'LN-721',
        'currency' => 'PHP',
        'payment_method' => 'gcash',
        'payment_method_label' => 'GCash',
        'payment_method_type' => 'gcash',
        'base_amount_cents' => 100000,
        'service_fee_cents' => 2562,
        'gross_amount_cents' => 102562,
        'status' => PaymongoLoanPayment::STATUS_PAID,
        'provider' => 'paymongo',
        'metadata' => [],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-payments.paymongo.success', [
            'payment' => $payment,
        ]));

    $response
        ->assertRedirect(route('client.loan-payments', [
            'loanNumber' => 'LN-721',
        ]))
        ->assertInertiaFlash(
            'status',
            'Payment confirmed. It will be reconciled against your loan account.',
        );
});

test('client paymongo cancellation redirects back and marks the payment cancelled', function () {
    $user = createApprovedClientUser('000722');

    $payment = PaymongoLoanPayment::query()->create([
        'user_id' => $user->getKey(),
        'acctno' => $user->acctno,
        'loan_number' => 'LN-722',
        'currency' => 'PHP',
        'payment_method' => 'gcash',
        'payment_method_label' => 'GCash',
        'payment_method_type' => 'gcash',
        'base_amount_cents' => 100000,
        'service_fee_cents' => 2562,
        'gross_amount_cents' => 102562,
        'status' => PaymongoLoanPayment::STATUS_PENDING,
        'provider' => 'paymongo',
        'metadata' => [],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-payments.paymongo.cancel', [
            'payment' => $payment,
        ]));

    $response
        ->assertRedirect(route('client.loan-payments', [
            'loanNumber' => 'LN-722',
        ]))
        ->assertInertiaFlash('status', 'PayMongo checkout was cancelled.');

    expect($payment->refresh()->status)
        ->toBe(PaymongoLoanPayment::STATUS_CANCELLED);
});

test('client can export loan payments as csv', function () {
    $user = User::factory()->create([
        'acctno' => '000705',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Glen',
        'fname' => 'Glen',
        'lname' => 'Member',
        'birthday' => '1990-02-04',
        'address' => '222 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-705',
        'lntype' => 'Regular',
        'principal' => 2500,
        'balance' => 2000,
    ]);
    DB::table('wlnled')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-705',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2025-03-15 00:00:00')->toDateTimeString(),
        'principal' => 100,
        'payments' => 100,
        'balance' => 1900,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-payments.export', [
            'loanNumber' => 'LN-705',
            'format' => 'csv',
        ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
});

test('client can preview loan payments pdf inline', function () {
    $user = User::factory()->create([
        'acctno' => '000708',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Quinn',
        'fname' => 'Quinn',
        'lname' => 'Member',
        'birthday' => '1992-05-12',
        'address' => '444 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-708',
        'lntype' => 'Regular',
        'principal' => 1800,
        'balance' => 1300,
    ]);
    DB::table('wlnled')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-708',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2025-03-20 00:00:00')->toDateTimeString(),
        'principal' => 120,
        'payments' => 120,
        'balance' => 1180,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-payments.export', [
            'loanNumber' => 'LN-708',
            'format' => 'pdf',
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline;');
});

test('client can download loan payments pdf when flagged', function () {
    $user = User::factory()->create([
        'acctno' => '000709',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Riley',
        'fname' => 'Riley',
        'lname' => 'Member',
        'birthday' => '1991-04-18',
        'address' => '555 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Coordinator',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-709',
        'lntype' => 'Regular',
        'principal' => 2200,
        'balance' => 1700,
    ]);
    DB::table('wlnled')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-709',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2025-03-22 00:00:00')->toDateTimeString(),
        'principal' => 150,
        'payments' => 150,
        'balance' => 1550,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-payments.export', [
            'loanNumber' => 'LN-709',
            'format' => 'pdf',
            'download' => 1,
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
});

test('client can open loan payments print preview', function () {
    $user = User::factory()->create([
        'acctno' => '000710',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Taylor',
        'fname' => 'Taylor',
        'lname' => 'Member',
        'birthday' => '1993-02-10',
        'address' => '666 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-710',
        'lntype' => 'Regular',
        'principal' => 2400,
        'balance' => 1800,
    ]);
    DB::table('wlnled')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-710',
        'lntype' => 'Regular',
        'date_in' => Carbon::now()->toDateTimeString(),
        'principal' => 200,
        'payments' => 200,
        'balance' => 1600,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-payments.print', [
            'loanNumber' => 'LN-710',
            'range' => 'all',
        ]));

    $response->assertOk();
    $response->assertViewIs('reports.loan-payments');
    $response->assertSee('Loan Payment Transaction Report');
    $response->assertSee('window.print', false);
});

test('client cannot export loan payments for another member', function () {
    $owner = User::factory()->create([
        'acctno' => '000706',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $owner->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $owner->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $owner->acctno,
        'bname' => 'Member, Harper',
        'fname' => 'Harper',
        'lname' => 'Member',
        'birthday' => '1990-06-07',
        'address' => '111 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Clerk',
    ]);

    $viewer = User::factory()->create([
        'acctno' => '000707',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $viewer->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $viewer->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $viewer->acctno,
        'bname' => 'Member, Indigo',
        'fname' => 'Indigo',
        'lname' => 'Member',
        'birthday' => '1991-08-11',
        'address' => '333 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Staff',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $owner->acctno,
        'lnnumber' => 'LN-706',
        'lntype' => 'Regular',
        'principal' => 1800,
        'balance' => 1400,
    ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('client.loan-payments.export', [
            'loanNumber' => 'LN-706',
            'format' => 'csv',
        ]));

    $response->assertNotFound();
});

test('admins are redirected away from client account pages', function () {
    $admin = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $this->actingAs($admin);

    $this->get(route('client.loans'))
        ->assertRedirect(route('admin.dashboard'));
    $this->get(route('client.savings'))
        ->assertRedirect(route('admin.dashboard'));
    $this->get(route('client.loan-schedule', ['loanNumber' => 'LN-000']))
        ->assertRedirect(route('admin.dashboard'));
    $this->get(route('client.loan-payments', ['loanNumber' => 'LN-000']))
        ->assertRedirect(route('admin.dashboard'));
});

test('admin members can view client account pages', function () {
    $adminMember = User::factory()->create([
        'acctno' => '000901',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $adminMember->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $adminMember->user_id,
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $adminMember->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $adminMember->acctno,
        'bname' => 'Member, Admin',
        'fname' => 'Admin',
        'lname' => 'Member',
        'birthday' => '1991-01-15',
        'address' => '999 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Staff',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $adminMember->acctno,
        'lnnumber' => 'LN-901',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 900,
    ]);
    DB::table('wlnled')->insert([
        'acctno' => $adminMember->acctno,
        'lnnumber' => 'LN-901',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2025-04-10 00:00:00')->toDateTimeString(),
        'principal' => 100,
        'payments' => 100,
        'balance' => 800,
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $adminMember->acctno,
        'svnumber' => 'SV-901',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 0,
        'wbalance' => 0,
        'lastmove' => null,
    ]);
    DB::table('wsavled')->insert([
        'acctno' => $adminMember->acctno,
        'svnumber' => 'SV-901',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2025-04-11 00:00:00')->toDateTimeString(),
        'deposit' => 250,
        'withdrawal' => 0,
        'balance' => 250,
    ]);

    $this->actingAs($adminMember);

    $this->get(route('client.loans'))->assertOk();
    $this->get(route('client.savings'))->assertOk();
    $this->get(route('client.loan-schedule', ['loanNumber' => 'LN-901']))
        ->assertOk();
    $this->get(route('client.loan-payments', ['loanNumber' => 'LN-901']))
        ->assertOk();
});
