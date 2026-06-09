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

    public const LOAN_OFFICER = 'loan_officer';

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
            ['name' => self::ADMIN, 'display_name' => 'Admin'],
            ['name' => self::LOAN_OFFICER, 'display_name' => 'Loan Officer'],
            ['name' => self::LOAN_MANAGER, 'display_name' => 'Loan Manager'],
            ['name' => self::MEMBER, 'display_name' => 'Member'],
        ];
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
        $rolePermissions = [
            self::ADMIN => $permissionsByName->values()->all(),
            self::MEMBER => self::permissionIds($permissionsByName, [
                Permission::LOAN_CREATE,
                Permission::LOAN_VIEW,
            ]),
            self::LOAN_OFFICER => self::permissionIds($permissionsByName, [
                Permission::LOAN_VIEW,
                Permission::LOAN_REVIEW,
                Permission::LOAN_REQUEST_REVISION,
                Permission::LOAN_REJECT,
                Permission::LOAN_RECOMMEND_APPROVAL,
            ]),
            self::LOAN_MANAGER => self::permissionIds($permissionsByName, [
                Permission::LOAN_VIEW,
                Permission::LOAN_APPROVE,
                Permission::LOAN_DECLINE,
                Permission::LOAN_CONVERT_TO_LOAN,
            ]),
        ];

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
}
