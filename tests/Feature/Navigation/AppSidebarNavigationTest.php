<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

test('admin settings profile exposes admin navigation context', function () {
    $user = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.isAdmin', true)
            ->where('auth.isSuperadmin', false)
            ->where('auth.hasMemberAccess', false)
            ->where('auth.isAdminOnly', true)
            ->where('auth.isHybrid', false)
            ->where('auth.experience', 'admin-only')
        );
});

test('admin member settings profile exposes member navigation context', function () {
    $user = User::factory()->create([
        'acctno' => '000950',
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.isAdmin', true)
            ->where('auth.isSuperadmin', false)
            ->where('auth.hasMemberAccess', true)
            ->where('auth.isAdminOnly', false)
            ->where('auth.isHybrid', true)
            ->where('auth.experience', 'user-admin')
        );
});

test('superadmin settings profile exposes superadmin navigation context', function () {
    $user = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.isAdmin', true)
            ->where('auth.isSuperadmin', true)
            ->where('auth.hasMemberAccess', false)
            ->where('auth.experience', 'superadmin')
        );
});

test('admin settings password exposes admin navigation context', function () {
    $user = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user-password.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('auth.isAdmin', true)
            ->where('auth.isSuperadmin', false)
            ->where('auth.hasMemberAccess', false)
            ->where('auth.isAdminOnly', true)
            ->where('auth.isHybrid', false)
            ->where('auth.experience', 'admin-only')
        );
});

test('member settings profile exposes member navigation context', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.isAdmin', false)
            ->where('auth.isSuperadmin', false)
            ->where('auth.hasMemberAccess', true)
            ->where('auth.isAdminOnly', false)
            ->where('auth.isHybrid', false)
            ->where('auth.experience', 'user')
        );
});

test('admin dashboard exposes admin navigation context', function () {
    $user = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('admin.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.isAdmin', true)
            ->where('auth.isSuperadmin', false)
            ->where('auth.hasMemberAccess', false)
            ->where('auth.isAdminOnly', true)
            ->where('auth.isHybrid', false)
            ->where('auth.experience', 'admin-only')
        );
});
