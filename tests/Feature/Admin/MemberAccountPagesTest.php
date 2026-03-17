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
            $table->integer('typecode')->default(0);
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

test('admin can view member savings page', function () {
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
        'typecode' => 4,
        'mortuary' => 0,
        'balance' => 0,
        'wbalance' => 0,
        'lastmove' => null,
    ]);

    DB::table('wsvmaster')->insert([
        'acctno' => $member->acctno,
        'svnumber' => 'SV-702',
        'svtype' => 'Regular',
        'typecode' => 2,
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
            ->where('summary.currentSavingsBalance', 800)
            ->where('summary.lastSavingsTransactionDate', '2024-02-12 08:00:00')
            ->where('savings.items.0.svnumber', 'SV-701')
            ->where('savings.items.0.date_in', '2024-02-12 08:00:00')
            ->where('savings.items.1.svnumber', 'SV-701'));
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
