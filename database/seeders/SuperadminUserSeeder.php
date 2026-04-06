<?php

namespace Database\Seeders;

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperadminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $username = (string) config('portal.superadmin_username', 'superadmin');
        $email = (string) config('portal.superadmin_email', 'superadmin@example.com');
        $password = config('portal.superadmin_password');
        $phoneno = config('portal.superadmin_phoneno');
        $fullname = (string) config('portal.superadmin_fullname', 'System Super Administrator');

        if ($username === '' || $email === '') {
            throw new RuntimeException('Superadmin username and email are required.');
        }

        if (! $password) {
            throw new RuntimeException('PORTAL_SUPERADMIN_PASSWORD must be set.');
        }

        if (! $phoneno) {
            if (! App::environment(['local', 'testing'])) {
                throw new RuntimeException('PORTAL_SUPERADMIN_PHONENO must be set outside local/testing environments.');
            }

            $phoneno = '00000000000';
        }

        $phoneno = (string) $phoneno;

        if (strlen($phoneno) !== 11) {
            throw new RuntimeException('PORTAL_SUPERADMIN_PHONENO must be exactly 11 characters.');
        }

        $superadmin = AppUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'username' => $username,
                'acctno' => null,
                'password' => Hash::make((string) $password),
                'phoneno' => $phoneno,
            ],
        );

        $superadmin->forceFill([
            'email_verified_at' => now(),
        ])->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $superadmin->user_id],
            ['role' => 'client', 'status' => 'active'],
        );

        AdminProfile::query()->updateOrCreate(
            ['user_id' => $superadmin->user_id],
            [
                'fullname' => $fullname,
                'access_level' => AdminProfile::ACCESS_LEVEL_SUPERADMIN,
            ],
        );

        $this->command?->info(
            sprintf(
                'Superadmin ready: %s (%s, %s)',
                $superadmin->email,
                $superadmin->username,
                $superadmin->display_code,
            ),
        );
    }
}
