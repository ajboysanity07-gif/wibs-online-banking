<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
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
        });
    }
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('active users can visit the dashboard', function () {
    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Rivera, Ana',
        'fname' => 'Ana',
        'lname' => 'Rivera',
        'birthday' => '1992-06-15',
        'address' => '123 Mabini Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('client.dashboard'));

    $clientResponse = $this->get(route('client.dashboard'));
    $clientResponse
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->where('member.username', $user->username)
            ->where('member.acctno', '000701')
            ->has('summary')
            ->has('summary.loanBalanceLeft')
            ->has('summary.currentLoanSecurityBalance')
            ->has('summary.currentLoanSecurityTotal')
            ->has('summary.lastLoanTransactionDate')
            ->has('summary.lastLoanSecurityTransactionDate')
            ->has('summary.recentLoans')
            ->has('summary.recentLoanSecurity')
            ->has('recentAccountActions')
            ->where('recentAccountActions.meta.page', 1));
});

test('active users without a completed profile are redirected to onboarding', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Cruz, Jose',
        'fname' => 'Jose',
        'lname' => 'Cruz',
        'birthday' => '1988-02-20',
        'address' => '456 Pilar Street',
        'civilstat' => 'Married',
        'occupation' => 'Supervisor',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('client.dashboard'));
    $response->assertRedirect(route('profile.edit', ['onboarding' => 1]));
});

test('active users with incomplete member profiles are redirected to onboarding', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Delos Reyes, Anna',
        'fname' => 'Anna',
        'lname' => 'Delos Reyes',
        'birthday' => '1991-03-10',
        'address' => '789 Mabini Street',
        'civilstat' => 'Single',
        'occupation' => 'Clerk',
    ]);
    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'birthplace' => 'Cebu City',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('client.dashboard'));
    $response->assertRedirect(route('profile.edit', ['onboarding' => 1]));
});

test('client dashboard sanitizes legacy member payloads', function () {
    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $legacyName = "Legacy\xB1Name";

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => $legacyName,
        'fname' => 'Legacy',
        'lname' => 'Member',
        'birthday' => '1990-01-01',
        'address' => 'Legacy Address',
        'civilstat' => 'Single',
        'occupation' => 'Member',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('client.dashboard'));
    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/dashboard')
            ->where('member.acctno', '000701')
            ->where('member.name', fn ($value) => is_string($value))
            ->has('summary')
            ->has('recentAccountActions')
            ->where('recentAccountActions.meta.page', 1));
});

test('admins are redirected away from client dashboard', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $this->actingAs($admin);

    $response = $this->get(route('client.dashboard'));
    $response->assertRedirect(route('admin.dashboard'));
});

test('suspended users are redirected to account unavailable', function () {
    $user = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $user->user_id,
        'status' => 'suspended',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('pending-approval'));
});
