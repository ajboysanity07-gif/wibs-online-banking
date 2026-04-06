<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
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
        });
    }
});

test('admin can view the admin dashboard', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('summary')
            ->has('summary.metrics')
            ->has('summary.requests'));
});

test('pending approvals page is no longer available', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->get('/admin/users/pending');

    $response->assertNotFound();
});

test('admin can view watchlist page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.watchlist.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/watchlist'));
});

test('admin can view requests page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.requests.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/requests'));
});

test('admin can view member profile page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000701',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

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

test('admin can view a member profile after admin access is granted', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000702',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $member->user_id,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.show', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-profile')
            ->where('member.user_id', $member->user_id)
            ->where('member.admin_access_level', 'admin'));
});

test('superadmin member profile payload includes admin access data', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000703',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $response = $this
        ->actingAs($superadmin)
        ->get(route('admin.members.show', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-profile')
            ->where('auth.isSuperadmin', true)
            ->where('member.admin_access_level', 'member'));
});

test('admin can view unregistered member profile page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '000888',
        'fname' => 'Maria',
        'lname' => 'Cruz',
        'bname' => 'Cruz, Maria',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.show', 'acct-000888'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-profile')
            ->has('member')
            ->where('member.registration_status', 'unregistered')
            ->where('member.acctno', '000888'));
});
