<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
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

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->decimal('principal', 12, 2)->nullable();
            $table->decimal('balance', 12, 2)->nullable();
            $table->decimal('initial', 12, 2)->nullable();
            $table->dateTime('lastmove')->nullable();
        });
    }

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
        });
    }

    if (! Schema::hasTable('Amortsched')) {
        Schema::create('Amortsched', function (Blueprint $table) {
            $table->string('lnnumber');
            $table->dateTime('Date_pay')->nullable();
            $table->decimal('Amortization', 12, 2)->nullable();
            $table->decimal('Interest', 12, 2)->nullable();
            $table->decimal('Balance', 12, 2)->nullable();
            $table->string('controlno')->nullable();
        });
    }
});

test('admin can view member profile page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.show', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-profile')
            ->has('member')
            ->where('member.user_id', $member->user_id));
});

test('admin can view unregistered member profile with account activity', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '000801',
        'lname' => 'Cruz',
        'fname' => 'Ana',
        'bname' => 'Cruz, Ana',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => '000801',
        'lnnumber' => 'LN-801',
        'lntype' => 'Regular',
        'principal' => 1000,
        'balance' => 700,
        'initial' => 1000,
        'lastmove' => Carbon::parse('2024-02-01 08:00:00')->toDateTimeString(),
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => '000801',
        'svnumber' => 'SV-801',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 200,
        'wbalance' => 200,
        'lastmove' => Carbon::parse('2024-02-02 08:00:00')->toDateTimeString(),
    ]);

    DB::table('wsavled')->insert([
        'acctno' => '000801',
        'svnumber' => 'SV-801',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-02 08:00:00')->toDateTimeString(),
        'deposit' => 200,
        'withdrawal' => 0,
        'balance' => 200,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.show', 'acct-000801'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-profile')
            ->where('member.user_id', null)
            ->where('member.registration_status', 'unregistered')
            ->where('accountsSummary.loanBalanceLeft', 700)
            ->where('recentAccountActions.items.0.source', 'SAV'));
});

test('admin can view member loans page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000701',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loans', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-loans')
            ->has('member')
            ->has('summary')
            ->has('loans')
            ->where('member.user_id', $member->user_id));
});

test('admin can view unregistered member loans page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '000802',
        'lname' => 'Lim',
        'fname' => 'Toni',
        'bname' => 'Lim, Toni',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => '000802',
        'lnnumber' => 'LN-802',
        'lntype' => 'Regular',
        'principal' => 900,
        'balance' => 450,
        'initial' => 900,
        'lastmove' => Carbon::parse('2024-02-05 08:00:00')->toDateTimeString(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loans', 'acct-000802'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-loans')
            ->where('member.user_id', null)
            ->where('member.member_id', 'acct-000802')
            ->where('member.acctno', '000802')
            ->has('loans.items', 1));
});

test('admin can view member loan schedule page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000703',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-100',
        'lntype' => 'Regular',
        'principal' => 1000,
        'balance' => 800,
        'initial' => 1000,
        'lastmove' => Carbon::parse('2024-01-15 08:00:00')->toDateTimeString(),
    ]);

    DB::table('Amortsched')->insert([
        'lnnumber' => 'LN-100',
        'Date_pay' => Carbon::parse('2024-02-15 08:00:00')->toDateTimeString(),
        'Amortization' => 200,
        'Interest' => 25,
        'Balance' => 800,
        'controlno' => 'CTRL-001',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loan-schedule', [
            'user' => $member->user_id,
            'loanNumber' => 'LN-100',
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-loan-schedule')
            ->has('member')
            ->has('loan')
            ->has('summary')
            ->has('schedule')
            ->where('member.user_id', $member->user_id));
});

test('admin can view member loan security page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000702',
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-701',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 50,
        'balance' => 700,
        'wbalance' => 700,
        'lastmove' => null,
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-702',
        'svtype' => 'Regular',
        'typecode' => '02',
        'mortuary' => 0,
        'balance' => 0,
        'wbalance' => 0,
        'lastmove' => null,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-702',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-11 08:00:00')->toDateTimeString(),
        'deposit' => 100,
        'withdrawal' => 0,
        'balance' => 100,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-701',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-10 08:00:00')->toDateTimeString(),
        'deposit' => 250,
        'withdrawal' => 0,
        'balance' => 500,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-701',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-12 08:00:00')->toDateTimeString(),
        'deposit' => 300,
        'withdrawal' => 0,
        'balance' => 800,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.savings', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-savings')
            ->has('member')
            ->has('summary')
            ->has('savings')
            ->has('savings.items', 2)
            ->where('member.user_id', $member->user_id)
            ->where('summary.currentLoanSecurityBalance', 800)
            ->where('summary.currentLoanSecurityTotal', 750)
            ->where('summary.lastLoanSecurityTransactionDate', '2024-02-12 08:00:00')
            ->where('savings.items.0.svnumber', 'SV-701')
            ->where('savings.items.0.svtype', 'Regular')
            ->where('savings.items.0.date_in', '2024-02-12 08:00:00')
            ->where('savings.items.0.deposit', 300)
            ->where('savings.items.0.withdrawal', 0)
            ->where('savings.items.1.svnumber', 'SV-701'));
});

test('admin can view unregistered member loan security page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '000803',
        'lname' => 'Garcia',
        'fname' => 'Mae',
        'bname' => 'Garcia, Mae',
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => '000803',
        'svnumber' => 'SV-803',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 600,
        'wbalance' => 600,
        'lastmove' => null,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => '000803',
        'svnumber' => 'SV-803',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-20 08:00:00')->toDateTimeString(),
        'deposit' => 600,
        'withdrawal' => 0,
        'balance' => 600,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.savings', 'acct-000803'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-savings')
            ->where('member.user_id', null)
            ->where('member.member_id', 'acct-000803')
            ->has('savings.items', 1));
});

test('admin can view member loan security page when ledger balance is missing', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000704',
    ]);

    Schema::drop('wsavled');
    Schema::create('wsavled', function (Blueprint $table) {
        $table->string('acctno');
        $table->string('svnumber');
        $table->string('svtype')->nullable();
        $table->dateTime('date_in')->nullable();
        $table->decimal('deposit', 12, 2)->default(0);
        $table->decimal('withdrawal', 12, 2)->default(0);
    });

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-704',
        'svtype' => 'Regular',
        'typecode' => '01',
        'mortuary' => 0,
        'balance' => 400,
        'wbalance' => 400,
        'lastmove' => null,
    ]);

    DB::table('wsavled')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-704',
        'svtype' => 'Regular',
        'date_in' => Carbon::parse('2024-02-15 08:00:00')->toDateTimeString(),
        'deposit' => 75,
        'withdrawal' => 0,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.savings', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-savings')
            ->has('summary')
            ->has('savings')
            ->where('savings.items.0.balance', 0));
});

test('non-admin users cannot access member account pages', function () {
    $user = User::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.members.loans', $member->user_id))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.members.savings', $member->user_id))
        ->assertForbidden();
});
