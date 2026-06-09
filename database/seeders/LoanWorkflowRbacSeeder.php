<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LoanWorkflowRbacSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('roles') ||
            ! Schema::hasTable('permissions') ||
            ! Schema::hasTable('role_permissions') ||
            ! Schema::hasTable('user_roles')
        ) {
            $this->command?->warn('Loan workflow RBAC tables are not available yet. Skipping RBAC seed.');

            return;
        }

        foreach (Role::defaults() as $roleDefinition) {
            Role::query()->updateOrCreate(
                ['name' => $roleDefinition['name']],
                ['display_name' => $roleDefinition['display_name']],
            );
        }

        foreach (Permission::defaults() as $permissionDefinition) {
            Permission::query()->updateOrCreate(
                ['name' => $permissionDefinition['name']],
                ['display_name' => $permissionDefinition['display_name']],
            );
        }

        $permissionsByName = Permission::query()->pluck('id', 'name');
        $rolePermissions = [
            Role::ADMIN => $permissionsByName->values()->all(),
            Role::MEMBER => $this->permissionIds($permissionsByName, [
                Permission::LOAN_CREATE,
                Permission::LOAN_VIEW,
            ]),
            Role::LOAN_OFFICER => $this->permissionIds($permissionsByName, [
                Permission::LOAN_VIEW,
                Permission::LOAN_REVIEW,
                Permission::LOAN_REQUEST_REVISION,
                Permission::LOAN_REJECT,
                Permission::LOAN_RECOMMEND_APPROVAL,
            ]),
            Role::LOAN_MANAGER => $this->permissionIds($permissionsByName, [
                Permission::LOAN_VIEW,
                Permission::LOAN_APPROVE,
                Permission::LOAN_DECLINE,
                Permission::LOAN_CONVERT_TO_LOAN,
            ]),
        ];

        Role::query()
            ->whereIn('name', array_keys($rolePermissions))
            ->get()
            ->each(function (Role $role) use ($rolePermissions): void {
                $role->permissions()->sync($rolePermissions[$role->name] ?? []);
            });

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

    /**
     * @param  Collection<string, int>  $permissionsByName
     * @param  list<string>  $permissionNames
     * @return list<int>
     */
    private function permissionIds(Collection $permissionsByName, array $permissionNames): array
    {
        return array_values(array_filter(array_map(
            static fn (string $permissionName): ?int => $permissionsByName->get($permissionName),
            $permissionNames,
        )));
    }
}
