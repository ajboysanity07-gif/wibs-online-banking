<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LoanWorkflowRbacSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('roles') ||
            ! Schema::hasTable('user_roles')
        ) {
            $this->command?->warn('Loan workflow RBAC tables are not available yet. Skipping RBAC seed.');

            return;
        }

        Role::ensureWorkflowDefaults();

        $adminRoleId = Role::query()
            ->where('name', Role::ADMIN)
            ->value('id');
        $memberRoleId = Role::query()
            ->where('name', Role::MEMBER)
            ->value('id');

        if ($adminRoleId !== null) {
            AppUser::query()
                ->whereHas('adminProfile')
                ->get()
                ->each(function (AppUser $user) use ($adminRoleId): void {
                    $user->roles()->syncWithoutDetaching([$adminRoleId]);
                });
        }

        if ($memberRoleId !== null) {
            // Member backfill follows the existing member-access rule: a non-empty acctno.
            AppUser::query()
                ->whereNotNull('acctno')
                ->whereRaw("LTRIM(RTRIM(acctno)) <> ''")
                ->get()
                ->each(function (AppUser $user) use ($memberRoleId): void {
                    $user->roles()->syncWithoutDetaching([$memberRoleId]);
                });
        }

        $this->command?->info(sprintf(
            'Loan workflow RBAC seeded: %d roles, %d permissions, %d admin backfills, %d member backfills.',
            Role::query()->count(),
            Permission::query()->count(),
            AppUser::query()->whereHas('roles', function ($query): void {
                $query->where('name', Role::ADMIN);
            })->count(),
            AppUser::query()->whereHas('roles', function ($query): void {
                $query->where('name', Role::MEMBER);
            })->count(),
        ));
        $this->command?->line(
            'Member backfill only attaches the member role to users with a non-empty acctno. Users without that signal are left unchanged.',
        );
    }
}
