<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
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
});

test('approved client can view the loans page', function () {
    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
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

test('approved client can view the savings page', function () {
    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('client.savings'));
    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/savings')
            ->where('member.acctno', '000701')
            ->has('summary')
            ->has('summary.currentPersonalSavings')
            ->has('savings'));
});

test('approved client can view the loan schedule page', function () {
    $user = User::factory()->create([
        'acctno' => '000702',
    ]);
    UserProfile::factory()->approved()->create([
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

    DB::table('wlnmaster')->insert([
        'acctno' => $user->acctno,
        'lnnumber' => 'LN-703',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 1200,
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
            ->where('loan.lnnumber', 'LN-703'));
});

test('admins are redirected away from client account pages', function () {
    $admin = User::factory()->create();
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
