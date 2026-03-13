<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;

test('admin can list members', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $otherAdmin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $otherAdmin->user_id,
    ]);

    $response = $this->actingAs($admin)->getJson('/spa/admin/members');

    $response->assertOk();

    $memberIds = collect($response->json('data.items'))->pluck('user_id');

    expect($memberIds)->toContain($member->user_id);
    expect($memberIds)->not->toContain($otherAdmin->user_id);
});

test('admin can approve pending members', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $member->user_id,
        'status' => 'pending',
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/approve");

    $response->assertOk();

    $member->refresh();

    expect($member->userProfile?->status)->toBe('active');
    expect($member->userProfile?->reviewed_by)->toBe($admin->user_id);
});

test('admin can suspend and reactivate members', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $suspendResponse = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/suspend");

    $suspendResponse->assertOk();
    expect($member->refresh()->userProfile?->status)->toBe('suspended');

    $reactivateResponse = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/reactivate");

    $reactivateResponse->assertOk();
    expect($member->refresh()->userProfile?->status)->toBe('active');
});

test('non-admin users cannot change member status', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson("/spa/admin/members/{$member->user_id}/suspend");

    $response->assertForbidden();
});
