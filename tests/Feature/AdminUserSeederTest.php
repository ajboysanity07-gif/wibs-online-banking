<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\UserProfile;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Str;

test('admin user seeder provisions an admin account', function () {
    config([
        'portal.admin_username' => 'admin',
        'portal.admin_email' => 'admin@example.com',
        'portal.admin_password' => Str::random(32),
        'portal.admin_phoneno' => '09999999999',
        'portal.admin_fullname' => 'System Administrator',
    ]);

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $admin = AppUser::query()->where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->username)->toBe('admin');
    expect($admin->acctno)->toBeNull();
    expect($admin->email_verified_at)->not->toBeNull();
    expect(str_starts_with($admin->display_code, 'ADM-'))->toBeTrue();
    expect(preg_match('/^ADM-\d{6}$/', $admin->display_code))->toBe(1);

    expect(AdminProfile::query()->where('user_id', $admin->user_id)->count())
        ->toBe(1);
    expect(
        UserProfile::query()
            ->where('user_id', $admin->user_id)
            ->where('role', 'client')
            ->where('status', 'active')
            ->exists(),
    )->toBeTrue();

    $client = AppUser::factory()->create();

    expect(str_starts_with($client->display_code, 'USR-'))->toBeTrue();
    expect(preg_match('/^USR-\d{6}$/', $client->display_code))->toBe(1);
});
