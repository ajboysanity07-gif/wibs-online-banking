<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;

test('user profile status backfill is idempotent', function () {
    $missingProfileUser = User::factory()->create();

    $nullStatusUser = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $nullStatusUser->user_id,
        'status' => '',
    ]);

    $suspendedUser = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $suspendedUser->user_id,
        'status' => 'suspended',
    ]);

    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $migration = require database_path(
        'migrations/2026_04_06_034804_backfill_user_profile_status_for_registered_members.php',
    );

    $migration->up();
    $migration->up();

    $missingProfile = UserProfile::query()
        ->where('user_id', $missingProfileUser->user_id)
        ->first();
    $nullStatusProfile = UserProfile::query()
        ->where('user_id', $nullStatusUser->user_id)
        ->first();
    $suspendedProfile = UserProfile::query()
        ->where('user_id', $suspendedUser->user_id)
        ->first();

    expect($missingProfile)->not->toBeNull();
    expect($missingProfile?->status)->toBe('active');
    expect($nullStatusProfile?->status)->toBe('active');
    expect($suspendedProfile?->status)->toBe('suspended');
    expect(UserProfile::query()->where('user_id', $admin->user_id)->exists())
        ->toBeFalse();
});
