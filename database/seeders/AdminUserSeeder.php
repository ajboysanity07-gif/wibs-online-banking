<?php

namespace Database\Seeders;

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $username = (string) config('portal.admin_username', 'admin');
        $email = (string) config('portal.admin_email', 'admin@example.com');
        $password = config('portal.admin_password');
        $phoneno = config('portal.admin_phoneno');
        $fullname = (string) config('portal.admin_fullname', 'System Administrator');

        if ($username === '' || $email === '') {
            throw new RuntimeException('Admin username and email are required.');
        }

        if (! $password) {
            throw new RuntimeException('PORTAL_ADMIN_PASSWORD must be set.');
        }

        if (! $phoneno) {
            if (! App::environment(['local', 'testing'])) {
                throw new RuntimeException('PORTAL_ADMIN_PHONENO must be set outside local/testing environments.');
            }

            $phoneno = '00000000000';
        }

        $phoneno = (string) $phoneno;

        if (strlen($phoneno) !== 11) {
            throw new RuntimeException('PORTAL_ADMIN_PHONENO must be exactly 11 characters.');
        }

        $admin = AppUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'username' => $username,
                'acctno' => null,
                'password' => Hash::make((string) $password),
                'phoneno' => $phoneno,
            ],
        );

        $admin->forceFill([
            'email_verified_at' => now(),
        ])->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $admin->user_id],
            ['role' => 'client', 'status' => 'active'],
        );

        AdminProfile::query()->updateOrCreate(
            ['user_id' => $admin->user_id],
            ['fullname' => $fullname],
        );

        $this->command?->info(
            sprintf(
                'Admin ready: %s (%s, %s)',
                $admin->email,
                $admin->username,
                $admin->display_code,
            ),
        );
    }
}
