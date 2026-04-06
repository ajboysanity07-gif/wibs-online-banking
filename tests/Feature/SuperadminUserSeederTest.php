<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\UserProfile;
use Database\Seeders\SuperadminUserSeeder;
use Illuminate\Support\Str;

test('superadmin user seeder provisions a superadmin account', function () {
    config([
        'portal.superadmin_username' => 'superadmin',
        'portal.superadmin_email' => 'superadmin@example.com',
        'portal.superadmin_password' => Str::random(32),
        'portal.superadmin_phoneno' => '09999999999',
        'portal.superadmin_fullname' => 'System Super Administrator',
    ]);

    $this->seed(SuperadminUserSeeder::class);
    $this->seed(SuperadminUserSeeder::class);

    $superadmin = AppUser::query()->where('email', 'superadmin@example.com')->first();

    expect($superadmin)->not->toBeNull();
    expect($superadmin->username)->toBe('superadmin');
    expect($superadmin->acctno)->toBeNull();
    expect($superadmin->email_verified_at)->not->toBeNull();
    expect(str_starts_with($superadmin->display_code, 'ADM-'))->toBeTrue();
    expect(preg_match('/^ADM-\d{6}$/', $superadmin->display_code))->toBe(1);

    expect(AdminProfile::query()->where('user_id', $superadmin->user_id)->count())
        ->toBe(1);
    expect(
        AdminProfile::query()
            ->where('user_id', $superadmin->user_id)
            ->where('access_level', AdminProfile::ACCESS_LEVEL_SUPERADMIN)
            ->exists(),
    )->toBeTrue();
    expect(
        UserProfile::query()
            ->where('user_id', $superadmin->user_id)
            ->where('role', 'client')
            ->where('status', 'active')
            ->exists(),
    )->toBeTrue();
});
