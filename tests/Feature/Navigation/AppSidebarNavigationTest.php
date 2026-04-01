<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Inertia\Testing\AssertableInertia as Assert;

test('admin settings profile exposes admin navigation context', function () {
    $user = User::factory()->create();
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
        );
});

test('superadmin settings profile exposes superadmin navigation context', function () {
    $user = User::factory()->create();
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
        );
});

test('admin settings password exposes admin navigation context', function () {
    $user = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user-password.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.isAdmin', true)
            ->where('auth.isSuperadmin', false)
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
        );
});

test('admin dashboard exposes admin navigation context', function () {
    $user = User::factory()->create();
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
        );
});
