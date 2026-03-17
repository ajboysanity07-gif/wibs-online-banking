<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('approved users can visit the dashboard', function () {
    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
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
            ->has('summary.currentPersonalSavings')
            ->has('summary.currentSavingsBalance')
            ->has('summary.lastLoanTransactionDate')
            ->has('summary.lastSavingsTransactionDate')
            ->has('summary.recentLoans')
            ->has('summary.recentSavings')
            ->has('recentAccountActions')
            ->where('recentAccountActions.meta.page', 1));
});

test('client dashboard sanitizes legacy member payloads', function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('bname')->nullable();
        });
    }

    $user = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $legacyName = "Legacy\xB1Name";

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => $legacyName,
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

test('pending users are redirected to pending approval', function () {
    $user = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $user->user_id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('pending-approval'));
});
