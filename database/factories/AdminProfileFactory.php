<?php

namespace Database\Factories;

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminProfile>
 */
class AdminProfileFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (AdminProfile $adminProfile): void {
            $adminProfile->loadMissing('appUser');

            if (
                $adminProfile->appUser instanceof AppUser
                && $adminProfile->access_level === AdminProfile::ACCESS_LEVEL_SUPERADMIN
            ) {
                Role::attachNamedRole($adminProfile->appUser, Role::SUPERADMIN);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory(),
            'fullname' => fake()->name(),
            'access_level' => AdminProfile::ACCESS_LEVEL_SUPERADMIN,
            'profile_pic_path' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    /**
     * Non-superadmin profile with the retired legacy 'admin' level (new rows now default to 'staff'),
     * kept so tests can simulate staff profiles with no implicit superadmin. Also the factory's
     * default state -- tests that don't care about tier and sync their
     * own RBAC role afterward must NOT get an implicit `isLegacySuperadmin()`
     * bypass from a default of ACCESS_LEVEL_SUPERADMIN.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'access_level' => 'admin',
        ]);
    }

    public function superadmin(): static
    {
        return $this->state(fn () => [
            'access_level' => AdminProfile::ACCESS_LEVEL_SUPERADMIN,
        ]);
    }
}
