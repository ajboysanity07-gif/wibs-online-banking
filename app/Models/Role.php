<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;

    public const ADMIN = 'admin';

    public const SUPERADMIN = 'superadmin';

    public const LOAN_PROCESSOR = 'loan_processor';

    public const LOAN_MANAGER = 'loan_manager';

    public const MEMBER = 'member';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
    ];

    /**
     * @return list<array{name: string, display_name: string|null}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => self::SUPERADMIN, 'display_name' => 'Superadmin'],
            ['name' => self::LOAN_PROCESSOR, 'display_name' => 'Loan Processor'],
            ['name' => self::LOAN_MANAGER, 'display_name' => 'Loan Manager'],
            ['name' => self::MEMBER, 'display_name' => 'Member'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function editableStaffNames(): array
    {
        return [
            self::SUPERADMIN,
            self::LOAN_PROCESSOR,
            self::LOAN_MANAGER,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function workflowPermissionNames(
        bool $includeLegacyLoanOfficer = false,
    ): array {
        $permissions = [
            self::SUPERADMIN => [
                Permission::LOAN_VIEW,
                Permission::LOAN_MANAGE_ASSIGNMENT,
                Permission::STAFF_VIEW,
                Permission::STAFF_MANAGE,
                Permission::MEMBER_VIEW,
                Permission::MEMBER_CREATE,
                Permission::MEMBER_UPDATE,
                Permission::PAYMENT_CREATE,
                Permission::REPORT_VIEW_ALL,
                Permission::REPORT_EXPORT,
            ],
            self::MEMBER => [
                Permission::LOAN_CREATE,
                Permission::LOAN_VIEW,
            ],
            self::LOAN_PROCESSOR => [
                Permission::LOAN_VIEW,
                Permission::LOAN_REVIEW,
                Permission::LOAN_CORRECT,
                Permission::LOAN_CLAIM,
                Permission::LOAN_RETURN_TO_QUEUE,
                Permission::LOAN_REQUEST_REVISION,
                Permission::LOAN_REJECT,
                Permission::LOAN_RECOMMEND_APPROVAL,
                Permission::REPORT_VIEW_OWN,
            ],
            self::LOAN_MANAGER => [
                Permission::LOAN_VIEW,
                Permission::LOAN_MANAGE_ASSIGNMENT,
                Permission::LOAN_CORRECT,
                Permission::LOAN_APPROVE,
                Permission::LOAN_DECLINE,
                Permission::LOAN_WIBS_ENCODE,
                Permission::REPORT_VIEW_ALL,
                Permission::REPORT_EXPORT,
            ],
        ];

        if ($includeLegacyLoanOfficer) {
            $permissions['loan_officer'] = $permissions[self::LOAN_PROCESSOR];
        }

        return $permissions;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            AppUser::class,
            'user_roles',
            'role_id',
            'user_id',
            'id',
            'user_id',
        )->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    public static function ensureWorkflowDefaults(): void
    {
        if (
            ! Schema::hasTable('roles') ||
            ! Schema::hasTable('permissions') ||
            ! Schema::hasTable('role_permissions')
        ) {
            return;
        }

        if (
            ! self::query()->where('name', self::LOAN_PROCESSOR)->exists()
            && self::query()->where('name', 'loan_officer')->exists()
        ) {
            self::query()
                ->where('name', 'loan_officer')
                ->update([
                    'name' => self::LOAN_PROCESSOR,
                    'display_name' => 'Loan Processor',
                ]);
        }

        foreach (self::defaults() as $roleDefinition) {
            self::query()->updateOrCreate(
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
        $rolePermissions = collect(
            self::workflowPermissionNames(includeLegacyLoanOfficer: true),
        )->mapWithKeys(
            fn (array $permissionNames, string $roleName): array => [
                $roleName => self::permissionIds(
                    $permissionsByName,
                    $permissionNames,
                ),
            ],
        )->all();

        self::query()
            ->whereIn('name', array_keys($rolePermissions))
            ->get()
            ->each(function (self $role) use ($rolePermissions): void {
                $role->permissions()->sync($rolePermissions[$role->name] ?? []);
            });
    }

    public static function attachNamedRole(AppUser $user, string $roleName): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        self::ensureWorkflowDefaults();
        $roleName = self::normalizeRoleName($roleName);

        $roleId = self::query()
            ->where('name', $roleName)
            ->value('id');

        if ($roleId === null) {
            return;
        }

        $user->roles()->syncWithoutDetaching([$roleId]);
    }

    public static function detachNamedRole(AppUser $user, string $roleName): void
    {
        if (! Schema::hasTable('user_roles')) {
            return;
        }

        $roleName = self::normalizeRoleName($roleName);

        $roleId = self::query()
            ->where('name', $roleName)
            ->value('id');

        if ($roleId === null) {
            return;
        }

        $user->roles()->detach($roleId);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $permissionsByName
     * @param  list<string>  $permissionNames
     * @return list<int>
     */
    private static function permissionIds($permissionsByName, array $permissionNames): array
    {
        return array_values(array_filter(array_map(
            static fn (string $permissionName): ?int => $permissionsByName->get($permissionName),
            $permissionNames,
        )));
    }

    private static function normalizeRoleName(string $roleName): string
    {
        return trim($roleName) === 'loan_officer'
            ? self::LOAN_PROCESSOR
            : trim($roleName);
    }
}
