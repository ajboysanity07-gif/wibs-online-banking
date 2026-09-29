<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use Illuminate\Support\Facades\DB;

test('an admin profile created without an access level gets the unprivileged staff default', function (): void {
    $user = AppUser::factory()->create();

    DB::table('admin_profiles')->insert([
        'user_id' => $user->user_id,
        'fullname' => 'Default Level',
    ]);

    $profile = AdminProfile::query()->where('user_id', $user->user_id)->firstOrFail();

    expect($profile->access_level)->toBe('staff')
        ->and($profile->isSuperadmin())->toBeFalse();
});
