<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;

test('admin profile photo backfill copies member profile photo and is idempotent', function () {
    $user = User::factory()->create([
        'acctno' => '001301',
    ]);
    $userProfile = UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
        'profile_pic_path' => "profile-photos/client/{$user->user_id}/avatar.jpg",
    ]);
    $adminProfile = AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'profile_pic_path' => null,
    ]);

    $migration = require database_path(
        'migrations/2026_04_08_021304_backfill_admin_profile_photos_for_members.php',
    );

    $migration->up();

    expect($adminProfile->refresh()->profile_pic_path)
        ->toBe($userProfile->profile_pic_path);

    $migration->up();

    expect($adminProfile->refresh()->profile_pic_path)
        ->toBe($userProfile->profile_pic_path);
});
