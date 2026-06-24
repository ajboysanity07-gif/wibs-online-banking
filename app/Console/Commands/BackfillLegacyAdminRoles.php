<?php

namespace App\Console\Commands;

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillLegacyAdminRoles extends Command
{
    protected $signature = 'admin:backfill-legacy-roles';

    protected $description = 'Assign the RBAC admin role to legacy admin users who have no staff RBAC role.';

    public function handle(): int
    {
        if (
            ! Schema::hasTable('admin_profiles')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('user_roles')
        ) {
            $this->error('Required tables (admin_profiles, roles, user_roles) are missing.');

            return self::FAILURE;
        }

        Role::ensureWorkflowDefaults();

        $adminRoleId = Role::query()->where('name', Role::ADMIN)->value('id');

        if ($adminRoleId === null) {
            $this->error('Admin role not found after seeding defaults.');

            return self::FAILURE;
        }

        $allStaffRoleNames = array_merge(Role::editableStaffNames(), [Role::ADMIN]);

        $backfilled = 0;

        DB::transaction(function () use ($adminRoleId, $allStaffRoleNames, &$backfilled): void {
            AppUser::query()
                ->whereHas('adminProfile', fn ($q) => $q->where('access_level', AdminProfile::ACCESS_LEVEL_ADMIN))
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', $allStaffRoleNames))
                ->orderBy('user_id')
                ->chunkById(200, function ($users) use ($adminRoleId, &$backfilled): void {
                    foreach ($users as $user) {
                        $user->roles()->syncWithoutDetaching([$adminRoleId]);
                        $this->line(sprintf(
                            'Assigned admin role: user_id=%d acctno=%s',
                            $user->user_id,
                            $user->acctno ?? '(none)',
                        ));
                        $backfilled++;
                    }
                }, 'user_id');
        });

        $this->newLine();
        $this->info(sprintf('Backfilled: %d legacy admin user(s)', $backfilled));

        return self::SUCCESS;
    }
}
