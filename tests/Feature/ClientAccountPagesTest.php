<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            $table->binary('bsignature')->nullable();
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
            $table->decimal('int_rate', 8, 2)->nullable();
            $table->decimal('term_mons', 8, 2)->nullable();
            $table->decimal('amortization', 12, 2)->nullable();
            $table->decimal('pen_rate', 8, 2)->nullable();
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

test('client loans page exposes schedule derived due date and installment', function () {
    $user = User::factory()->create([
        'acctno' => '000781',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Nico',
        'fname' => 'Nico',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-781',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 28500,
        'initial' => 60000,
        'int_rate' => 2.5,
        'term_mons' => 24,
    ]);

    $today = Carbon::now()->startOfDay();
    $nextDue = $today->copy()->addMonth()->startOfMonth();

    DB::table('Amortsched')->insert([
        [
            'lnnumber' => 'LN-781',
            'Date_pay' => $today->copy()->subDays(5),
            'Amortization' => 3000,
            'Interest' => 150,
            'Balance' => 30000,
            'controlno' => 'AS-781-0',
        ],
        [
            'lnnumber' => 'LN-781',
            'Date_pay' => $nextDue,
            'Amortization' => 2500,
            'Interest' => 250,
            'Balance' => 27500,
            'controlno' => 'AS-781-1',
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->has('loans.items', 1)
            ->where('loans.items.0.lnnumber', 'LN-781')
            ->where('loans.items.0.monthlyDue', 2500)
            ->where('loans.items.0.intRate', 2.5)
            ->where('loans.items.0.monthlyRate', 0.21)
            ->where('loans.items.0.termMonths', 24)
            ->where('loans.items.0.dueDate', $nextDue->format('Y-m-d')));
});

test('client loans page exposes recent loan payments', function () {
    $user = User::factory()->create([
        'acctno' => '000782',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Pia',
        'fname' => 'Pia',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-782',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 28500,
        'initial' => 60000,
    ]);

    DB::table('wlnled')->insert([
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-782',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-09-15 10:00:00'),
            'principal' => 2387.5,
            'payments' => 3150,
            'balance' => 28500,
            'accruedint' => 762.5,
            'lnstatus' => 'P',
        ],
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-782',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-09-01 10:00:00'),
            'principal' => 0,
            'payments' => 0,
            'balance' => 31650,
            'accruedint' => 0,
            'lnstatus' => 'P',
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->where('paymentsError', null)
            ->has('payments', 1)
            ->where('payments.0.lnnumber', 'LN-782')
            ->where('payments.0.lntype', 'Salary Loan')
            ->where('payments.0.amount', 3150)
            ->where('payments.0.principal', 2387.5)
            ->where('payments.0.interest', 762.5)
            ->where('payments.0.date', '2026-09-15 10:00:00'));
});

test('client loans page leaves schedule derived fields null without a schedule', function () {
    $user = User::factory()->create([
        'acctno' => '000783',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Quin',
        'fname' => 'Quin',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-783',
        'lntype' => 'Regular',
        'principal' => 1000,
        'balance' => 400,
        'initial' => 1000,
    ]);

    $this->actingAs($user);

    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->has('loans.items', 1)
            ->where('loans.items.0.lnnumber', 'LN-783')
            ->where('loans.items.0.monthlyDue', null)
            ->where('loans.items.0.dueDate', null)
            ->where('payments', []));
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
    DB::table('Amortsched')->insert([
        [
            'lnnumber' => 'LN-703',
            'Date_pay' => Carbon::parse('2025-01-10 00:00:00')->toDateTimeString(),
            'Amortization' => 300,
            'Interest' => 20,
            'Balance' => 1200,
            'controlno' => 'AS-1',
        ],
        [
            'lnnumber' => 'LN-703',
            'Date_pay' => Carbon::parse('2025-02-10 00:00:00')->toDateTimeString(),
            'Amortization' => 300,
            'Interest' => 15,
            'Balance' => 900,
            'controlno' => 'AS-2',
        ],
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
            ->has('payments')
            ->has('schedule.items', 2)
            ->where('schedule.items.0.amortization', 300)
            ->where('schedule.items.1.balance', 900)
            ->where('loan.lnnumber', 'LN-703'));
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

test('client can fetch a monthly statement of account for their own loan', function () {
    $user = User::factory()->create([
        'acctno' => '000784',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Sam',
        'address' => '123 Member Street',
        'fname' => 'Sam',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-784',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 28500,
        'initial' => 60000,
    ]);

    DB::table('wlnled')->insert([
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-784',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-08-15 10:00:00'),
            'principal' => 2300,
            'payments' => 3150,
            'balance' => 31650,
            'accruedint' => 850,
            'lnstatus' => 'P',
            'controlno' => 'CN-784-01',
        ],
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-784',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-09-15 10:00:00'),
            'principal' => 2387.5,
            'payments' => 3150,
            'balance' => 28500,
            'accruedint' => 762.5,
            'lnstatus' => 'P',
            'controlno' => 'CN-784-02',
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('client.loan-statement', ['loanNumber' => 'LN-784']).'?month=2026-09')
        ->assertOk()
        ->assertJsonPath('month', '2026-09')
        ->assertJsonPath('periodLabel', 'September 2026')
        ->assertJsonPath('totals.principal', 2387.5)
        ->assertJsonPath('totals.interest', 762.5)
        ->assertJsonPath('totals.payments', 3150)
        ->assertJsonPath('totals.count', 1)
        ->assertJsonPath('closingBalance', 28500)
        ->assertJsonPath('movements.0.reference', 'CN-784-02')
        ->assertJsonPath('movements.0.description', 'Payment')
        ->assertJsonPath('memberAddress', '123 Member Street');
});

test('statement endpoint rejects a malformed month', function () {
    $user = User::factory()->create([
        'acctno' => '000786',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Uma',
        'fname' => 'Uma',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-786',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 28500,
        'initial' => 60000,
    ]);

    $this->actingAs($user);

    $this->get(route('client.loan-statement', ['loanNumber' => 'LN-786']).'?month=2026-9')
        ->assertUnprocessable();
});

test('statement endpoint 404s for another members loan', function () {
    $owner = User::factory()->create([
        'acctno' => '000787',
    ]);
    $other = User::factory()->create([
        'acctno' => '000788',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $other->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $other->acctno,
        'bname' => 'Member, Vic',
        'fname' => 'Vic',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $other->user_id,
    ]);
    DB::table('wlnmaster')->insert([
        'acctno' => $owner->acctno,
        'lnnumber' => 'LN-787',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 0,
        'initial' => 60000,
    ]);

    $this->actingAs($other);

    $this->get(route('client.loan-statement', ['loanNumber' => 'LN-787']).'?month=2026-09')
        ->assertNotFound();
});

test('loan payments page exposes statement months and certificate eligibility', function () {
    $user = User::factory()->create([
        'acctno' => '000789',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Wren',
        'fname' => 'Wren',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-789',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 0,
        'initial' => 60000,
    ]);

    DB::table('wlnled')->insert([
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-789',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-08-15 10:00:00'),
            'principal' => 2300,
            'payments' => 3150,
            'balance' => 31650,
            'accruedint' => 850,
            'lnstatus' => 'P',
            'controlno' => 'CN-789-01',
        ],
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-789',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-09-15 10:00:00'),
            'principal' => 31650,
            'payments' => 33000,
            'balance' => 0,
            'accruedint' => 1350,
            'lnstatus' => 'P',
            'controlno' => 'CN-789-02',
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('client.loan-payments', ['loanNumber' => 'LN-789']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-payments')
            ->where('documents.certificateEligible', true)
            ->where('securityBalance', 0)
            ->where('documents.soaMonths', [
                ['month' => '2026-09', 'transactions' => 1],
                ['month' => '2026-08', 'transactions' => 1],
            ]));
});

test('loan payments page exposes loan manager, rate and term from core banking rows', function () {
    $user = User::factory()->create([
        'acctno' => '000790',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'Rina',
        'mname' => '',
        'lname' => 'Bautista',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    Role::attachNamedRole($user, Role::LOAN_MANAGER);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-790',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 12000,
        'initial' => 60000,
        'int_rate' => 2.5,
        'term_mons' => 36,
    ]);

    $this->actingAs($user);

    $this->get(route('client.loan-payments', ['loanNumber' => 'LN-790']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-payments')
            ->where('loanManager.name', 'Annabelle M. Amora')
            ->where('loanManager.role', 'Loan Manager')
            ->where('loan.intRate', 2.5)
            ->where('loan.monthlyRate', 0.21)
            ->where('loan.termMonths', 36)
            ->where('documents.certificateEligible', false));
});

test('member signature endpoint streams the stored signature image', function () {
    $user = User::factory()->create([
        'acctno' => '000791',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'Sig',
        'lname' => 'Member',
        'bsignature' => testPngSignatureBinary(),
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('client.member-signature', ['acctno' => $user->acctno]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png');
    expect($response->getContent())->toBe(testPngSignatureBinary());
});

test('member signature endpoint 404s without a stored signature', function () {
    $user = User::factory()->create([
        'acctno' => '000792',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'No',
        'lname' => 'Signature',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $this->actingAs($user);

    $this->get(route('client.member-signature', ['acctno' => $user->acctno]))
        ->assertNotFound();
});

test('member signature endpoint forbids another members signature', function () {
    $owner = User::factory()->create([
        'acctno' => '000793',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $owner->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $owner->acctno,
        'fname' => 'Owner',
        'lname' => 'Member',
        'bsignature' => testPngSignatureBinary(),
    ]);

    $other = User::factory()->create([
        'acctno' => '000794',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $other->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $other->acctno,
        'fname' => 'Other',
        'lname' => 'Member',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $other->user_id,
    ]);

    $this->actingAs($other);

    $this->get(route('client.member-signature', ['acctno' => $owner->acctno]))
        ->assertForbidden();
});

test('loan payments page defaults to all transactions without a range', function () {
    $user = User::factory()->create([
        'acctno' => '000795',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'All',
        'lname' => 'Transactions',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-795',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 12000,
        'initial' => 60000,
    ]);

    DB::table('wlnled')->insert([
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-795',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-08-15 10:00:00'),
            'principal' => 2300,
            'payments' => 3150,
            'balance' => 31650,
            'accruedint' => 850,
            'lnstatus' => 'P',
            'controlno' => 'CN-795-01',
        ],
        [
            'acctno' => $user->acctno,
            'lnnumber' => 'LN-795',
            'lntype' => 'Salary Loan',
            'date_in' => Carbon::parse('2026-09-15 10:00:00'),
            'principal' => 2400,
            'payments' => 3250,
            'balance' => 29250,
            'accruedint' => 850,
            'lnstatus' => 'P',
            'controlno' => 'CN-795-02',
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('client.loan-payments', ['loanNumber' => 'LN-795']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-payments')
            ->where('payments.filters.range', 'all')
            ->has('payments.items', 2));
});

test('loans page next payment follows paid progress instead of the calendar', function () {
    $user = User::factory()->create([
        'acctno' => '000796',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'Paid',
        'lname' => 'Ahead',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-796',
        'lntype' => 'Salary Loan',
        'principal' => 12000,
        'balance' => 6000,
        'initial' => 12000,
        'pen_rate' => 60,
    ]);

    $due = Carbon::now()->addDays(10)->startOfDay();

    DB::table('Amortsched')->insert([
        [
            'lnnumber' => 'LN-796',
            'Date_pay' => Carbon::now()->subDays(60),
            'Amortization' => 3000,
            'Interest' => 500,
            'Balance' => 9000,
            'controlno' => 'AS-796-0',
        ],
        [
            'lnnumber' => 'LN-796',
            'Date_pay' => Carbon::now()->subDays(30),
            'Amortization' => 3000,
            'Interest' => 400,
            'Balance' => 6000,
            'controlno' => 'AS-796-1',
        ],
        [
            'lnnumber' => 'LN-796',
            'Date_pay' => $due,
            'Amortization' => 3000,
            'Interest' => 300,
            'Balance' => 3000,
            'controlno' => 'AS-796-2',
        ],
        [
            'lnnumber' => 'LN-796',
            'Date_pay' => $due->copy()->addMonth(),
            'Amortization' => 3000,
            'Interest' => 200,
            'Balance' => 0,
            'controlno' => 'AS-796-3',
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->where('loans.items.0.monthlyDue', 3000)
            ->where('loans.items.0.dueDate', $due->format('Y-m-d'))
            ->where('loans.items.0.penalty', null));
});

test('loans page penalizes an overdue next installment from pen_rate', function () {
    $user = User::factory()->create([
        'acctno' => '000797',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'Late',
        'lname' => 'Payer',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-797',
        'lntype' => 'Salary Loan',
        'principal' => 12000,
        'balance' => 9000,
        'initial' => 12000,
        'pen_rate' => 60,
    ]);

    $due = Carbon::now()->subDays(45)->startOfDay();

    DB::table('Amortsched')->insert([
        [
            'lnnumber' => 'LN-797',
            'Date_pay' => Carbon::now()->subDays(75),
            'Amortization' => 3000,
            'Interest' => 500,
            'Balance' => 9000,
            'controlno' => 'AS-797-0',
        ],
        [
            'lnnumber' => 'LN-797',
            'Date_pay' => $due,
            'Amortization' => 3000,
            'Interest' => 400,
            'Balance' => 6000,
            'controlno' => 'AS-797-1',
        ],
        [
            'lnnumber' => 'LN-797',
            'Date_pay' => Carbon::now()->addDays(15),
            'Amortization' => 3000,
            'Interest' => 300,
            'Balance' => 3000,
            'controlno' => 'AS-797-2',
        ],
    ]);

    $this->actingAs($user);

    // 45 days late = 2 started months at 60% / 12 = 5% of the installment.
    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->where('loans.items.0.monthlyDue', 3300)
            ->where('loans.items.0.dueDate', $due->format('Y-m-d'))
            ->where('loans.items.0.penalty', 300));
});

test('loans page falls back to wlnmaster amortization without a schedule', function () {
    $user = User::factory()->create([
        'acctno' => '000798',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'No',
        'lname' => 'Schedule',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-798',
        'lntype' => 'Salary Loan',
        'principal' => 12000,
        'balance' => 9000,
        'initial' => 12000,
        'amortization' => 2500,
    ]);

    $this->actingAs($user);

    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->where('loans.items.0.monthlyDue', 2500)
            ->where('loans.items.0.dueDate', null)
            ->where('loans.items.0.penalty', null));
});

test('loans page derives the payment split from the schedule', function () {
    $user = User::factory()->create([
        'acctno' => '000799',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'Split',
        'lname' => 'Derived',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-799',
        'lntype' => 'Salary Loan',
        'principal' => 60000,
        'balance' => 56850,
        'initial' => 60000,
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-799',
        'lntype' => 'Salary Loan',
        'date_in' => Carbon::parse('2026-09-15 10:00:00'),
        'principal' => 0,
        'payments' => 3150,
        'balance' => 56850,
        'accruedint' => 0,
        'lnstatus' => 'P',
        'controlno' => 'CN-799-01',
    ]);

    DB::table('Amortsched')->insert([
        'lnnumber' => 'LN-799',
        'Date_pay' => Carbon::parse('2026-09-10 00:00:00'),
        'Amortization' => 3150,
        'Interest' => 762.50,
        'Balance' => 56850,
        'controlno' => 'AS-799-0',
    ]);

    $this->actingAs($user);

    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->where('payments.0.principal', 2387.5)
            ->where('payments.0.interest', 762.5));
});

test('loans page combines penalty and derived split in one payload', function () {
    $user = User::factory()->create([
        'acctno' => '000800',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'fname' => 'Padded',
        'lname' => 'Number',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-800',
        'lntype' => 'Salary Loan',
        'principal' => 12000,
        'balance' => 9000,
        'initial' => 12000,
        'pen_rate' => 60,
    ]);

    $due = Carbon::now()->subDays(45)->startOfDay();

    // Note: sqlite matches loan numbers exactly while SQL Server ignores
    // trailing spaces, so padded production rows cannot be simulated here.
    // The trim-tolerant PHP grouping is covered by the LoanPaymentSplit unit
    // test; this test pins the combined overdue + derived-split payload.
    DB::table('Amortsched')->insert([
        [
            'lnnumber' => 'LN-800',
            'Date_pay' => Carbon::now()->subDays(75),
            'Amortization' => 3000,
            'Interest' => 500,
            'Balance' => 9000,
            'controlno' => 'AS-800-0',
        ],
        [
            'lnnumber' => 'LN-800',
            'Date_pay' => $due,
            'Amortization' => 3000,
            'Interest' => 400,
            'Balance' => 6000,
            'controlno' => 'AS-800-1',
        ],
        [
            'lnnumber' => 'LN-800',
            'Date_pay' => Carbon::now()->addDays(15),
            'Amortization' => 3000,
            'Interest' => 300,
            'Balance' => 3000,
            'controlno' => 'AS-800-2',
        ],
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-800',
        'lntype' => 'Salary Loan',
        'date_in' => Carbon::parse('2026-09-15 10:00:00'),
        'principal' => 0,
        'payments' => 3150,
        'balance' => 9000,
        'accruedint' => 0,
        'lnstatus' => 'P',
        'controlno' => 'CN-800-01',
    ]);

    $this->actingAs($user);

    $this->get(route('client.loans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->where('loans.items.0.monthlyDue', 3300)
            ->where('loans.items.0.dueDate', $due->format('Y-m-d'))
            ->where('loans.items.0.penalty', 300)
            ->where('payments.0.principal', 2750)
            ->where('payments.0.interest', 400));
});

test('client dashboard account activity filters by source, search and rows', function () {
    $user = User::factory()->create(['acctno' => '000720']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Dina',
        'fname' => 'Dina',
        'lname' => 'Member',
        'birthday' => '1990-01-10',
        'address' => '1 Member Street',
        'civilstat' => 'Single',
        'occupation' => 'Staff',
    ]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $user->user_id]);

    foreach (range(1, 7) as $day) {
        DB::table('wlnled')->insert([
            'acctno' => $user->acctno,
            'lnnumber' => 'LN0102-00'.$day,
            'lntype' => $day === 1 ? 'SALARY LOAN' : 'MICRO BUSINESS LOAN',
            'date_in' => Carbon::parse("2026-08-0{$day} 00:00:00")->toDateTimeString(),
            'principal' => 1000,
            'balance' => 1000,
        ]);
    }
    DB::table('wsvmaster')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV0101-000059',
        'svtype' => 'LOAN SECURITY',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 417,
        'wbalance' => 417,
        'lastmove' => null,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $user->acctno,
        'svnumber' => 'SV0101-000059',
        'svtype' => 'LOAN SECURITY',
        'date_in' => Carbon::parse('2026-08-09 00:00:00')->toDateTimeString(),
        'deposit' => 417,
        'withdrawal' => 0,
        'balance' => 417,
    ]);

    $get = fn (array $query) => $this->actingAs($user)->get(route('client.dashboard', $query));

    $get([])->assertInertia(fn (Assert $page) => $page
        ->where('recentAccountActions.meta.total', 8)
        ->has('recentAccountActions.items', 5));

    $get(['actions_per_page' => 10])->assertInertia(fn (Assert $page) => $page
        ->where('recentAccountActions.meta.perPage', 10)
        ->has('recentAccountActions.items', 8));

    $get(['actions_source' => 'SAV'])->assertInertia(fn (Assert $page) => $page
        ->where('recentAccountActions.meta.total', 1)
        ->where('recentAccountActions.items.0.source', 'SAV'));

    $get(['actions_source' => 'LOAN', 'actions_search' => 'salary'])->assertInertia(fn (Assert $page) => $page
        ->where('recentAccountActions.meta.total', 1)
        ->where('recentAccountActions.items.0.transaction_type', 'SALARY LOAN'));
});
