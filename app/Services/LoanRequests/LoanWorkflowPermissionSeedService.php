<?php

namespace App\Services\LoanRequests;

use App\Models\AdminProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Support\SchemaCapabilities;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanWorkflowPermissionSeedService
{
    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    /**
     * @return array{
     *     dry_run:bool,
     *     created:list<array<string, mixed>>,
     *     updated:list<array<string, mixed>>,
     *     unchanged:list<array<string, mixed>>,
     *     conflicts:list<array<string, mixed>>
     * }
     */
    public function seed(bool $dryRun = false): array
    {
        $this->ensureRequiredTables();

        $conflicts = $this->initialConflicts();

        if ($conflicts !== []) {
            return [
                'dry_run' => $dryRun,
                'created' => [],
                'updated' => [],
                'unchanged' => [],
                'conflicts' => $conflicts,
            ];
        }

        $before = $this->snapshot();
        $after = $before;

        DB::beginTransaction();

        try {
            $this->applySeed();
            $after = $this->snapshot();

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $throwable) {
            DB::rollBack();

            throw $throwable;
        }

        $report = $this->buildReport($before, $after, $dryRun);
        $report['conflicts'] = array_merge(
            $report['conflicts'],
            $this->postApplyConflicts($after),
        );

        return $report;
    }

    private function ensureRequiredTables(): void
    {
        $missingTables = array_values(array_filter([
            $this->schemaCapabilities->hasTable('roles') ? null : 'roles',
            $this->schemaCapabilities->hasTable('permissions') ? null : 'permissions',
            $this->schemaCapabilities->hasTable('role_permissions') ? null : 'role_permissions',
            $this->schemaCapabilities->hasTable('user_roles') ? null : 'user_roles',
            $this->schemaCapabilities->hasTable('appusers') ? null : 'appusers',
            $this->schemaCapabilities->hasTable('admin_profiles') ? null : 'admin_profiles',
        ]));

        if ($missingTables !== []) {
            throw new RuntimeException(sprintf(
                'Loan workflow permission seeding requires these tables: %s.',
                implode(', ', $missingTables),
            ));
        }
    }

    private function applySeed(): void
    {
        Role::ensureWorkflowDefaults();

        $roleIdsByName = Role::query()->pluck('id', 'name');

        $superadminRoleId = $roleIdsByName->get(Role::SUPERADMIN);
        if (is_numeric($superadminRoleId)) {
            $this->attachRoleToUsers(
                (int) $superadminRoleId,
                DB::table('admin_profiles')
                    ->where('access_level', AdminProfile::ACCESS_LEVEL_SUPERADMIN)
                    ->orderBy('user_id')
                    ->pluck('user_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all(),
            );
        }

        $memberRoleId = $roleIdsByName->get(Role::MEMBER);
        if (is_numeric($memberRoleId)) {
            $this->attachRoleToUsers(
                (int) $memberRoleId,
                DB::table('appusers')
                    ->whereNotNull('acctno')
                    ->whereRaw("LTRIM(RTRIM(acctno)) <> ''")
                    ->orderBy('user_id')
                    ->pluck('user_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all(),
            );
        }
    }

    /**
     * @param  array{
     *     roles:array<string, string>,
     *     permissions:array<string, string>,
     *     role_permissions:array<string, list<string>>,
     *     user_roles:array<string, list<int>>
     * }  $before
     * @param  array{
     *     roles:array<string, string>,
     *     permissions:array<string, string>,
     *     role_permissions:array<string, list<string>>,
     *     user_roles:array<string, list<int>>
     * }  $after
     * @return array{
     *     dry_run:bool,
     *     created:list<array<string, mixed>>,
     *     updated:list<array<string, mixed>>,
     *     unchanged:list<array<string, mixed>>,
     *     conflicts:list<array<string, mixed>>
     * }
     */
    private function buildReport(
        array $before,
        array $after,
        bool $dryRun,
    ): array {
        $report = [
            'dry_run' => $dryRun,
            'created' => [],
            'updated' => [],
            'unchanged' => [],
            'conflicts' => [],
        ];

        $workflowRoleNames = array_map(
            static fn (array $role): string => $role['name'],
            Role::defaults(),
        );
        $workflowPermissionNames = Permission::defaultNames();

        $legacyLoanOfficerRenamed = array_key_exists('loan_officer', $before['roles'])
            && ! array_key_exists(Role::LOAN_PROCESSOR, $before['roles'])
            && array_key_exists(Role::LOAN_PROCESSOR, $after['roles'])
            && ! array_key_exists('loan_officer', $after['roles']);

        $createdRoles = array_values(array_filter(
            $workflowRoleNames,
            function (string $roleName) use (
                $before,
                $after,
                $legacyLoanOfficerRenamed,
            ): bool {
                if ($legacyLoanOfficerRenamed && $roleName === Role::LOAN_PROCESSOR) {
                    return false;
                }

                return ! array_key_exists($roleName, $before['roles'])
                    && array_key_exists($roleName, $after['roles']);
            },
        ));

        if ($createdRoles !== []) {
            $report['created'][] = $this->issue(
                'workflow_roles',
                'Workflow roles will be created or were created.',
                count($createdRoles),
                $createdRoles,
            );
        }

        $updatedRoles = [];

        if ($legacyLoanOfficerRenamed) {
            $updatedRoles[] = 'loan_officer -> loan_processor';
        }

        foreach ($workflowRoleNames as $roleName) {
            if (
                ! array_key_exists($roleName, $before['roles'])
                || ! array_key_exists($roleName, $after['roles'])
            ) {
                continue;
            }

            if ($before['roles'][$roleName] !== $after['roles'][$roleName]) {
                $updatedRoles[] = $roleName;
            }
        }

        if ($updatedRoles !== []) {
            $report['updated'][] = $this->issue(
                'workflow_roles',
                'Workflow roles will be updated or were updated.',
                count($updatedRoles),
                $updatedRoles,
            );
        }

        $unchangedRoles = array_values(array_filter(
            $workflowRoleNames,
            fn (string $roleName): bool => array_key_exists($roleName, $before['roles'])
                && array_key_exists($roleName, $after['roles'])
                && $before['roles'][$roleName] === $after['roles'][$roleName],
        ));

        if ($unchangedRoles !== []) {
            $report['unchanged'][] = $this->issue(
                'workflow_roles',
                'Workflow roles were already current.',
                count($unchangedRoles),
                $unchangedRoles,
            );
        }

        $createdPermissions = array_values(array_filter(
            $workflowPermissionNames,
            fn (string $permissionName): bool => ! array_key_exists($permissionName, $before['permissions'])
                && array_key_exists($permissionName, $after['permissions']),
        ));

        if ($createdPermissions !== []) {
            $report['created'][] = $this->issue(
                'workflow_permissions',
                'Workflow permissions will be created or were created.',
                count($createdPermissions),
                $createdPermissions,
            );
        }

        $updatedPermissions = array_values(array_filter(
            $workflowPermissionNames,
            fn (string $permissionName): bool => array_key_exists($permissionName, $before['permissions'])
                && array_key_exists($permissionName, $after['permissions'])
                && $before['permissions'][$permissionName] !== $after['permissions'][$permissionName],
        ));

        if ($updatedPermissions !== []) {
            $report['updated'][] = $this->issue(
                'workflow_permissions',
                'Workflow permissions will be updated or were updated.',
                count($updatedPermissions),
                $updatedPermissions,
            );
        }

        $unchangedPermissions = array_values(array_filter(
            $workflowPermissionNames,
            fn (string $permissionName): bool => array_key_exists($permissionName, $before['permissions'])
                && array_key_exists($permissionName, $after['permissions'])
                && $before['permissions'][$permissionName] === $after['permissions'][$permissionName],
        ));

        if ($unchangedPermissions !== []) {
            $report['unchanged'][] = $this->issue(
                'workflow_permissions',
                'Workflow permissions were already current.',
                count($unchangedPermissions),
                $unchangedPermissions,
            );
        }

        $createdRoleMappings = [];
        $updatedRoleMappings = [];
        $unchangedRoleMappings = [];

        foreach (array_keys(Role::workflowPermissionNames()) as $roleName) {
            $beforePermissions = $before['role_permissions'][$roleName] ?? [];
            $afterPermissions = $after['role_permissions'][$roleName] ?? [];
            $desiredPermissions = Role::workflowPermissionNames()[$roleName] ?? [];
            sort($desiredPermissions);

            if ($afterPermissions !== $desiredPermissions) {
                $report['conflicts'][] = $this->issue(
                    'workflow_role_mappings',
                    'A workflow role mapping did not match the expected permission set after seeding.',
                    1,
                    [$roleName],
                );

                continue;
            }

            if ($beforePermissions === []) {
                $createdRoleMappings[] = $roleName;

                continue;
            }

            if ($beforePermissions !== $afterPermissions) {
                $updatedRoleMappings[] = $roleName;

                continue;
            }

            $unchangedRoleMappings[] = $roleName;
        }

        if ($createdRoleMappings !== []) {
            $report['created'][] = $this->issue(
                'workflow_role_mappings',
                'Workflow role-permission mappings will be created or were created.',
                count($createdRoleMappings),
                $createdRoleMappings,
            );
        }

        if ($updatedRoleMappings !== []) {
            $report['updated'][] = $this->issue(
                'workflow_role_mappings',
                'Workflow role-permission mappings will be updated or were updated.',
                count($updatedRoleMappings),
                $updatedRoleMappings,
            );
        }

        if ($unchangedRoleMappings !== []) {
            $report['unchanged'][] = $this->issue(
                'workflow_role_mappings',
                'Workflow role-permission mappings were already current.',
                count($unchangedRoleMappings),
                $unchangedRoleMappings,
            );
        }

        foreach ([
            Role::ADMIN => 'admin role backfill',
            Role::SUPERADMIN => 'superadmin role backfill',
            Role::MEMBER => 'member role backfill',
        ] as $roleName => $label) {
            $beforeUserIds = $before['user_roles'][$roleName] ?? [];
            $afterUserIds = $after['user_roles'][$roleName] ?? [];
            $newAssignments = array_values(array_diff($afterUserIds, $beforeUserIds));

            if ($newAssignments !== []) {
                $report['created'][] = $this->issue(
                    $roleName.'_user_roles',
                    sprintf('%s will attach or attached new user-role rows.', ucfirst($label)),
                    count($newAssignments),
                    array_map(
                        static fn (int $userId): string => 'user:'.$userId,
                        $newAssignments,
                    ),
                );
            }

            $unchangedAssignments = array_values(array_intersect($afterUserIds, $beforeUserIds));

            if ($unchangedAssignments !== []) {
                $report['unchanged'][] = $this->issue(
                    $roleName.'_user_roles',
                    sprintf('%s preserved existing user-role rows.', ucfirst($label)),
                    count($unchangedAssignments),
                    array_map(
                        static fn (int $userId): string => 'user:'.$userId,
                        $unchangedAssignments,
                    ),
                );
            }
        }

        return $report;
    }

    /**
     * @return array{
     *     roles:array<string, string>,
     *     permissions:array<string, string>,
     *     role_permissions:array<string, list<string>>,
     *     user_roles:array<string, list<int>>
     * }
     */
    private function snapshot(): array
    {
        $workflowRoleNames = array_values(array_unique([
            ...array_map(
                static fn (array $role): string => $role['name'],
                Role::defaults(),
            ),
            'loan_officer',
        ]));

        $roles = Role::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(
                fn (Role $role): array => [
                    $role->name => trim((string) ($role->display_name ?? '')),
                ],
            )->all();

        $permissions = Permission::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(
                fn (Permission $permission): array => [
                    $permission->name => trim((string) ($permission->display_name ?? '')),
                ],
            )->all();

        $rolePermissions = [];

        Role::query()
            ->with('permissions')
            ->whereIn('name', $workflowRoleNames)
            ->orderBy('id')
            ->get()
            ->each(function (Role $role) use (&$rolePermissions): void {
                $rolePermissions[$role->name] = $role->permissions
                    ->pluck('name')
                    ->map(static fn (mixed $value): string => (string) $value)
                    ->sort()
                    ->values()
                    ->all();
            });

        $userRoles = [];

        foreach ([Role::ADMIN, Role::SUPERADMIN, Role::MEMBER] as $roleName) {
            $roleId = Role::query()
                ->where('name', $roleName)
                ->value('id');

            if (! is_numeric($roleId)) {
                $userRoles[$roleName] = [];

                continue;
            }

            $userRoles[$roleName] = DB::table('user_roles')
                ->where('role_id', (int) $roleId)
                ->orderBy('user_id')
                ->pluck('user_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->all();
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'role_permissions' => $rolePermissions,
            'user_roles' => $userRoles,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function initialConflicts(): array
    {
        $hasLoanOfficer = Role::query()
            ->where('name', 'loan_officer')
            ->exists();
        $hasLoanProcessor = Role::query()
            ->where('name', Role::LOAN_PROCESSOR)
            ->exists();

        if ($hasLoanOfficer && $hasLoanProcessor) {
            return [
                $this->issue(
                    'legacy_role_conflict',
                    'Both loan_officer and loan_processor roles exist. Resolve the duplicate workflow-role state before seeding.',
                    2,
                    ['loan_officer', Role::LOAN_PROCESSOR],
                ),
            ];
        }

        return [];
    }

    /**
     * @param  array{
     *     roles:array<string, string>,
     *     permissions:array<string, string>,
     *     role_permissions:array<string, list<string>>,
     *     user_roles:array<string, list<int>>
     * }  $after
     * @return list<array<string, mixed>>
     */
    private function postApplyConflicts(array $after): array
    {
        $conflicts = [];

        $missingRoles = array_values(array_diff(
            array_map(
                static fn (array $role): string => $role['name'],
                Role::defaults(),
            ),
            array_keys($after['roles']),
        ));

        if ($missingRoles !== []) {
            $conflicts[] = $this->issue(
                'missing_workflow_roles',
                'Some workflow roles are still missing after seeding.',
                count($missingRoles),
                $missingRoles,
            );
        }

        $missingPermissions = array_values(array_diff(
            Permission::defaultNames(),
            array_keys($after['permissions']),
        ));

        if ($missingPermissions !== []) {
            $conflicts[] = $this->issue(
                'missing_workflow_permissions',
                'Some workflow permissions are still missing after seeding.',
                count($missingPermissions),
                $missingPermissions,
            );
        }

        return $conflicts;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function attachRoleToUsers(int $roleId, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $timestamps = [
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('user_roles')->upsert(
            array_map(
                static fn (int $userId) => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    ...$timestamps,
                ],
                array_values(array_unique($userIds)),
            ),
            ['user_id', 'role_id'],
            ['updated_at'],
        );
    }

    /**
     * @param  list<string>  $references
     * @return array<string, mixed>
     */
    private function issue(
        string $code,
        string $summary,
        int $count,
        array $references = [],
    ): array {
        return [
            'code' => $code,
            'summary' => $summary,
            'count' => $count,
            'references' => array_slice($references, 0, 20),
        ];
    }
}
