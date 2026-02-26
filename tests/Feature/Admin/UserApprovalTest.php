<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;

test('admin can approve pending users', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $user = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $user->user_id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.users.approve', $user));

    $response->assertRedirect();
    expect($user->refresh()->userProfile->status)->toBe('active');
});

test('non-admin users cannot approve pending users', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $pendingUser = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $pendingUser->user_id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->patch(route('admin.users.approve', $pendingUser));

    $response->assertForbidden();
});

test('admin can view pending users list', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.users.pending'));

    $response->assertOk();
});
