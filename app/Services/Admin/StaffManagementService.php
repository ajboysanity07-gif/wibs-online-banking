<?php

namespace App\Services\Admin;

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Models\UserProfile;
use App\Models\UserRoleChange;
use App\Services\LoanRequests\LoanRequestAssignmentService;
use App\Support\SchemaCapabilities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffManagementService
{
    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
        private LoanRequestAssignmentService $assignmentService,
    ) {}

    public function getPaginated(
        string $search,
        ?string $roleFilter,
        ?string $accessFilter,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        Role::ensureWorkflowDefaults();

        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);
        $trimmedSearch = trim($search);
        $query = AppUser::query()
            ->with([
                'adminProfile',
                'roles.permissions',
                'staffAccessControl',
                'userProfile',
                'latestRoleChange.actor.adminProfile',
            ]);

        if (Schema::hasTable('wmaster')) {
            $query->with('wmaster');
        }

        $showSearchCandidates = $trimmedSearch !== ''
            && $roleFilter === null
            && $accessFilter === null;

        if ($showSearchCandidates) {
            $query->where(function (Builder $builder) use ($trimmedSearch): void {
                $this->applyManagedStaffScope($builder);
                $builder->orWhere(function (Builder $candidateQuery) use ($trimmedSearch): void {
                    $this->applyAssignableCandidateScope($candidateQuery);
                    $this->applySearchFilter($candidateQuery, $trimmedSearch);
                });
            });
        } else {
            $this->applyManagedStaffScope($query);

            if ($trimmedSearch !== '') {
                $this->applySearchFilter($query, $trimmedSearch);
            }
        }

        if ($roleFilter !== null && $roleFilter !== '') {
            $this->applyRoleFilter($query, $roleFilter);
        }

        if ($accessFilter !== null && $accessFilter !== '') {
            $this->applyAccessFilter($query, $accessFilter);
        }

        return $query
            ->orderByRaw('CASE WHEN acctno IS NULL OR LTRIM(RTRIM(acctno)) = \'\' THEN 0 ELSE 1 END')
            ->orderBy('username')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Collection<int, UserRoleChange>
     */
    public function historyFor(AppUser $user, int $limit = 50): Collection
    {
        $limit = max(1, min($limit, 100));

        return UserRoleChange::query()
            ->with(['actor.adminProfile'])
            ->where('target_user_id', $user->user_id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{
     *     username: string,
     *     email: string,
     *     phoneno?: string|null,
     *     password: string,
     *     roles: array<int, string>,
     *     reason: string
     * }  $payload
     */
    public function createStaff(array $payload, AppUser $actor): AppUser
    {
        Role::ensureWorkflowDefaults();

        $reason = $this->normalizeReason($payload['reason'] ?? null);
        $roles = $this->normalizeEditableRoles($payload['roles'] ?? []);

        return DB::transaction(function () use ($payload, $actor, $reason, $roles): AppUser {
            $user = AppUser::query()->create([
                'username' => trim((string) $payload['username']),
                'email' => trim((string) $payload['email']),
                'phoneno' => $this->normalizeOptionalString($payload['phoneno'] ?? null),
                'acctno' => null,
                'password' => (string) $payload['password'],
            ]);

            UserProfile::query()->updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'role' => 'client',
                    'status' => 'active',
                ],
            );

            foreach ($roles as $roleName) {
                Role::attachNamedRole($user, $roleName);
            }

            $user = $this->reloadUser($user->user_id);

            $this->syncLegacySuperadminProfile($user);
            $this->activateStaffAccessRow($user);
            $user = $this->reloadUser($user->user_id);

            $this->recordUserRoleChange(
                $user,
                $actor,
                UserRoleChange::ACTION_STAFF_CREATED,
                null,
                [],
                $this->visibleRoleNames($user),
                null,
                $this->auditStaffStatus($user),
                $reason,
                [
                    'account_type' => 'staff_only',
                ],
            );

            return $this->reloadUser($user->user_id);
        });
    }

    public function assignRole(
        AppUser $target,
        AppUser $actor,
        string $roleName,
        string $reason,
    ): AppUser {
        Role::ensureWorkflowDefaults();

        $normalizedRole = $this->normalizeEditableRole($roleName);
        $normalizedReason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($target, $actor, $normalizedRole, $normalizedReason): AppUser {
            $user = $this->lockUser($target->user_id);
            $this->lockSupportingRows($user->user_id);
            $this->guardLegacyAdminOnlyTarget($user);

            if ($this->userAlreadyHasEditableRole($user, $normalizedRole)) {
                return $this->reloadUser($user->user_id);
            }

            $beforeRoles = $this->visibleRoleNames($user);
            $beforeStatus = $this->auditStaffStatus($user);

            Role::attachNamedRole($user, $normalizedRole);

            $user = $this->reloadUser($user->user_id);
            $this->syncLegacySuperadminProfile($user);
            $this->ensureStaffAccessRowForAssignment($user);
            $user = $this->reloadUser($user->user_id);

            $this->recordUserRoleChange(
                $user,
                $actor,
                UserRoleChange::ACTION_ROLE_ASSIGNED,
                $normalizedRole,
                $beforeRoles,
                $this->visibleRoleNames($user),
                $beforeStatus,
                $this->auditStaffStatus($user),
                $normalizedReason,
                null,
            );

            return $this->reloadUser($user->user_id);
        });
    }

    public function removeRole(
        AppUser $target,
        AppUser $actor,
        string $roleName,
        string $reason,
    ): AppUser {
        Role::ensureWorkflowDefaults();

        $normalizedRole = $this->normalizeEditableRole($roleName);
        $normalizedReason = $this->normalizeReason($reason);

        if ($target->user_id === $actor->user_id && $normalizedRole === Role::SUPERADMIN) {
            throw ValidationException::withMessages([
                'role' => 'You cannot remove or change your own Superadmin access from this page.',
            ]);
        }

        return DB::transaction(function () use ($target, $actor, $normalizedRole, $normalizedReason): AppUser {
            $user = $this->lockUser($target->user_id);
            $this->lockSupportingRows($user->user_id);
            $this->guardLegacyAdminOnlyTarget($user);

            if (! $this->userAlreadyHasEditableRole($user, $normalizedRole)) {
                return $this->reloadUser($user->user_id);
            }

            if ($normalizedRole === Role::SUPERADMIN) {
                $this->ensureNotRemovingLastActiveSuperadmin($user);
            }

            $beforeRoles = $this->visibleRoleNames($user);
            $beforeStatus = $this->auditStaffStatus($user);

            $unassignedLoanRequestIds = $normalizedRole === Role::LOAN_PROCESSOR
                ? $this->assignmentService->unassignUnavailableOfficerRequests(
                    $user,
                    $actor,
                    $normalizedReason,
                    LoanRequestAssignmentService::CAUSE_ROLE_REMOVED,
                )
                : [];

            Role::detachNamedRole($user, $normalizedRole);

            $user = $this->reloadUser($user->user_id);
            $this->syncLegacySuperadminProfile($user);
            $user = $this->reloadUser($user->user_id);

            $this->recordUserRoleChange(
                $user,
                $actor,
                UserRoleChange::ACTION_ROLE_REMOVED,
                $normalizedRole,
                $beforeRoles,
                $this->visibleRoleNames($user),
                $beforeStatus,
                $this->auditStaffStatus($user),
                $normalizedReason,
                $unassignedLoanRequestIds !== []
                    ? ['unassigned_loan_request_ids' => $unassignedLoanRequestIds]
                    : null,
            );

            return $this->reloadUser($user->user_id);
        });
    }

    public function suspend(
        AppUser $target,
        AppUser $actor,
        string $reason,
    ): AppUser {
        $normalizedReason = $this->normalizeReason($reason);

        if ($target->user_id === $actor->user_id) {
            throw ValidationException::withMessages([
                'staff' => 'You cannot suspend your own Superadmin access.',
            ]);
        }

        return DB::transaction(function () use ($target, $actor, $normalizedReason): AppUser {
            $user = $this->lockUser($target->user_id);
            $this->lockSupportingRows($user->user_id);
            $this->guardLegacyAdminOnlyTarget($user);
            $this->guardSuspendableTarget($user);

            if ($user->isStaffAccessSuspended()) {
                return $this->reloadUser($user->user_id);
            }

            $this->ensureNotSuspendingLastActiveSuperadmin($user);

            $beforeRoles = $this->visibleRoleNames($user);
            $beforeStatus = $this->auditStaffStatus($user);

            $control = StaffAccessControl::query()->updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'status' => StaffAccessControl::STATUS_SUSPENDED,
                    'suspended_at' => now(),
                    'suspended_by' => $actor->user_id,
                    'suspension_reason' => $normalizedReason,
                ],
            );

            $user->setRelation('staffAccessControl', $control);

            $unassignedLoanRequestIds = $this->userAlreadyHasEditableRole($user, Role::LOAN_PROCESSOR)
                ? $this->assignmentService->unassignUnavailableOfficerRequests(
                    $user,
                    $actor,
                    $normalizedReason,
                    LoanRequestAssignmentService::CAUSE_STAFF_SUSPENDED,
                )
                : [];

            $user = $this->reloadUser($user->user_id);

            $this->recordUserRoleChange(
                $user,
                $actor,
                UserRoleChange::ACTION_STAFF_SUSPENDED,
                null,
                $beforeRoles,
                $this->visibleRoleNames($user),
                $beforeStatus,
                $this->auditStaffStatus($user),
                $normalizedReason,
                [
                    'unassigned_loan_request_ids' => $unassignedLoanRequestIds,
                ],
            );

            return $this->reloadUser($user->user_id);
        });
    }

    public function reactivate(
        AppUser $target,
        AppUser $actor,
        string $reason,
    ): AppUser {
        $normalizedReason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($target, $actor, $normalizedReason): AppUser {
            $user = $this->lockUser($target->user_id);
            $this->lockSupportingRows($user->user_id);
            $this->guardLegacyAdminOnlyTarget($user);
            $this->guardSuspendableTarget($user);

            if (! $user->isStaffAccessSuspended()) {
                return $this->reloadUser($user->user_id);
            }

            $beforeRoles = $this->visibleRoleNames($user);
            $beforeStatus = $this->auditStaffStatus($user);

            $control = StaffAccessControl::query()->updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'status' => StaffAccessControl::STATUS_ACTIVE,
                    'suspended_at' => null,
                    'suspended_by' => null,
                    'suspension_reason' => null,
                ],
            );

            $user->setRelation('staffAccessControl', $control);
            $user = $this->reloadUser($user->user_id);

            $this->recordUserRoleChange(
                $user,
                $actor,
                UserRoleChange::ACTION_STAFF_REACTIVATED,
                null,
                $beforeRoles,
                $this->visibleRoleNames($user),
                $beforeStatus,
                $this->auditStaffStatus($user),
                $normalizedReason,
                null,
            );

            return $this->reloadUser($user->user_id);
        });
    }

    public function findMemberByAccountNumber(string $accountNumber): ?AppUser
    {
        $trimmed = trim($accountNumber);

        if ($trimmed === '') {
            return null;
        }

        $query = AppUser::query()
            ->where('acctno', $trimmed)
            ->with([
                'adminProfile',
                'roles.permissions',
                'staffAccessControl',
                'userProfile',
                'latestRoleChange.actor.adminProfile',
            ]);

        if (Schema::hasTable('wmaster')) {
            $query->with('wmaster');
        }

        return $query->first();
    }

    /**
     * @return Collection<int, AppUser>
     */
    public function searchMembers(string $query, int $limit = 10): Collection
    {
        $trimmed = trim($query);
        $isDefault = $trimmed === '' || mb_strlen($trimmed) < 2;

        $q = AppUser::query()
            ->with([
                'adminProfile',
                'roles.permissions',
                'staffAccessControl',
                'userProfile',
                'latestRoleChange.actor.adminProfile',
            ])
            ->whereHas('roles', function (Builder $roleQuery): void {
                $roleQuery->where('name', Role::MEMBER);
            });

        if ($isDefault) {
            $q->whereNotNull('acctno')->where('acctno', '!=', '');

            if (Schema::hasTable('wmaster')) {
                $q->with('wmaster');
            }

            return $q->limit(200)->get()
                ->sortBy(fn (AppUser $user): string => $this->memberSortKey($user))
                ->values()
                ->take(25);
        }

        $searchLike = '%'.addcslashes($trimmed, '%_\\').'%';
        $limit = max(1, min($limit, 10));

        $q->where(function (Builder $builder) use ($searchLike): void {
            $builder
                ->where('username', 'like', $searchLike)
                ->orWhere('email', 'like', $searchLike)
                ->orWhere('acctno', 'like', $searchLike)
                ->orWhereHas('adminProfile', function (Builder $profileQuery) use ($searchLike): void {
                    $profileQuery->where('fullname', 'like', $searchLike);
                });

            if (Schema::hasTable('wmaster')) {
                $builder->orWhereHas('wmaster', function (Builder $wmasterQuery) use ($searchLike): void {
                    $wmasterQuery
                        ->where('fname', 'like', $searchLike)
                        ->orWhere('mname', 'like', $searchLike)
                        ->orWhere('lname', 'like', $searchLike)
                        ->orWhere('bname', 'like', $searchLike);
                });
            }
        });

        if (Schema::hasTable('wmaster')) {
            $q->with('wmaster');
        }

        return $q->limit($limit)->get();
    }

    private function memberSortKey(AppUser $user): string
    {
        if (Schema::hasTable('wmaster') && $user->wmaster !== null) {
            $parts = array_filter([
                trim((string) ($user->wmaster->lname ?? '')),
                trim((string) ($user->wmaster->fname ?? '')),
            ]);
            $name = implode(', ', $parts);
            if ($name !== '') {
                return strtolower($name);
            }
        }

        if ($user->adminProfile?->fullname !== null && trim($user->adminProfile->fullname) !== '') {
            return strtolower(trim($user->adminProfile->fullname));
        }

        return strtolower(trim((string) ($user->username ?? $user->email ?? '')));
    }

    public function promoteMember(
        string $accountNumber,
        string $roleName,
        ?string $reason,
        AppUser $actor,
    ): AppUser {
        Role::ensureWorkflowDefaults();

        $normalizedRole = $this->normalizeEditableRole($roleName);
        $normalizedReason = trim((string) ($reason ?? ''));
        $trimmedAcctno = trim($accountNumber);

        $target = AppUser::query()
            ->where('acctno', $trimmedAcctno)
            ->firstOrFail();

        if (! $target->hasRole(Role::MEMBER)) {
            throw ValidationException::withMessages([
                'account_number' => 'The account number does not belong to a registered portal member.',
            ]);
        }

        if ($target->user_id === $actor->user_id) {
            throw ValidationException::withMessages([
                'account_number' => 'You cannot promote yourself.',
            ]);
        }

        return DB::transaction(function () use ($target, $actor, $normalizedRole, $normalizedReason): AppUser {
            $user = $this->lockUser($target->user_id);
            $this->lockSupportingRows($user->user_id);
            $this->guardLegacyAdminOnlyTarget($user);

            $beforeRoles = $this->visibleRoleNames($user);
            $beforeStatus = $this->auditStaffStatus($user);

            if (! $this->userAlreadyHasEditableRole($user, $normalizedRole)) {
                Role::attachNamedRole($user, $normalizedRole);
                $user = $this->reloadUser($user->user_id);
                $this->syncLegacySuperadminProfile($user);
            }

            $this->activateStaffAccessRow($user);
            $user = $this->reloadUser($user->user_id);

            $this->recordUserRoleChange(
                $user,
                $actor,
                UserRoleChange::ACTION_STAFF_CREATED,
                $normalizedRole,
                $beforeRoles,
                $this->visibleRoleNames($user),
                $beforeStatus,
                $this->auditStaffStatus($user),
                $normalizedReason,
                ['account_type' => 'member_promoted'],
            );

            return $this->reloadUser($user->user_id);
        });
    }

    private function applyManagedStaffScope(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            $builder
                ->whereHas('roles', function (Builder $roleQuery): void {
                    $roleQuery->whereIn('name', Role::editableStaffNames());
                })
                ->orWhereHas('adminProfile', function (Builder $profileQuery): void {
                    $profileQuery->where(
                        'access_level',
                        AdminProfile::ACCESS_LEVEL_SUPERADMIN,
                    );
                });
        });
    }

    private function applyAssignableCandidateScope(Builder $query): void
    {
        $query->where(function (Builder $builder): void {
            $builder
                ->whereDoesntHave('adminProfile')
                ->orWhereHas('adminProfile', function (Builder $profileQuery): void {
                    $profileQuery->where(
                        'access_level',
                        AdminProfile::ACCESS_LEVEL_SUPERADMIN,
                    );
                });
        });
    }

    private function applyRoleFilter(Builder $query, string $roleFilter): void
    {
        if ($roleFilter === Role::SUPERADMIN) {
            $query->where(function (Builder $builder): void {
                $builder
                    ->whereHas('roles', function (Builder $roleQuery): void {
                        $roleQuery->where('name', Role::SUPERADMIN);
                    })
                    ->orWhereHas('adminProfile', function (Builder $profileQuery): void {
                        $profileQuery->where(
                            'access_level',
                            AdminProfile::ACCESS_LEVEL_SUPERADMIN,
                        );
                    });
            });

            return;
        }

        $query->whereHas('roles', function (Builder $roleQuery) use ($roleFilter): void {
            $roleQuery->where('name', $roleFilter);
        });
    }

    private function applyAccessFilter(Builder $query, string $accessFilter): void
    {
        if ($accessFilter === StaffAccessControl::STATUS_SUSPENDED) {
            $query->whereHas('staffAccessControl', function (Builder $controlQuery): void {
                $controlQuery->where('status', StaffAccessControl::STATUS_SUSPENDED);
            });

            return;
        }

        $query->where(function (Builder $builder): void {
            $builder
                ->whereDoesntHave('staffAccessControl')
                ->orWhereHas('staffAccessControl', function (Builder $controlQuery): void {
                    $controlQuery->where('status', StaffAccessControl::STATUS_ACTIVE);
                });
        });
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $searchLike = '%'.addcslashes($search, '%_\\').'%';

        $query->where(function (Builder $builder) use ($searchLike): void {
            $builder
                ->where('username', 'like', $searchLike)
                ->orWhere('email', 'like', $searchLike)
                ->orWhere('phoneno', 'like', $searchLike)
                ->orWhere('acctno', 'like', $searchLike)
                ->orWhereHas('adminProfile', function (Builder $profileQuery) use ($searchLike): void {
                    $profileQuery->where('fullname', 'like', $searchLike);
                });

            if (Schema::hasTable('wmaster')) {
                $builder->orWhereHas('wmaster', function (Builder $wmasterQuery) use ($searchLike): void {
                    $wmasterQuery
                        ->where('acctno', 'like', $searchLike)
                        ->orWhere('fname', 'like', $searchLike)
                        ->orWhere('mname', 'like', $searchLike)
                        ->orWhere('lname', 'like', $searchLike)
                        ->orWhere('bname', 'like', $searchLike);
                });
            }
        });
    }

    private function lockUser(int $userId): AppUser
    {
        $query = AppUser::query()
            ->whereKey($userId)
            ->with([
                'adminProfile',
                'roles.permissions',
                'staffAccessControl',
                'userProfile',
            ]);

        if (Schema::hasTable('wmaster')) {
            $query->with('wmaster');
        }

        return $query->lockForUpdate()->firstOrFail();
    }

    private function reloadUser(int $userId): AppUser
    {
        $query = AppUser::query()
            ->whereKey($userId)
            ->with([
                'adminProfile',
                'roles.permissions',
                'staffAccessControl',
                'userProfile',
                'latestRoleChange.actor.adminProfile',
            ]);

        if (Schema::hasTable('wmaster')) {
            $query->with('wmaster');
        }

        return $query->firstOrFail();
    }

    private function lockSupportingRows(int $userId): void
    {
        if ($this->schemaCapabilities->hasTable('user_roles')) {
            DB::table('user_roles')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();
        }

        if ($this->schemaCapabilities->hasTable('admin_profiles')) {
            DB::table('admin_profiles')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();
        }

        if ($this->schemaCapabilities->hasTable('staff_access_controls')) {
            DB::table('staff_access_controls')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();
        }
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private function normalizeEditableRoles(array $roles): array
    {
        $allowedRoles = Role::editableStaffNames();

        return array_values(array_filter(
            array_unique(array_map(static fn (mixed $role): string => trim((string) $role), $roles)),
            static fn (string $role): bool => in_array($role, $allowedRoles, true),
        ));
    }

    private function normalizeEditableRole(string $role): string
    {
        $normalizedRole = trim($role);

        if (! in_array($normalizedRole, Role::editableStaffNames(), true)) {
            throw ValidationException::withMessages([
                'role' => 'The selected role cannot be managed from this page.',
            ]);
        }

        return $normalizedRole;
    }

    private function normalizeReason(mixed $reason): string
    {
        $normalizedReason = trim((string) $reason);

        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for every staff change.',
            ]);
        }

        return $normalizedReason;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function guardLegacyAdminOnlyTarget(AppUser $user): void
    {
        if (
            $user->adminProfile?->access_level === AdminProfile::ACCESS_LEVEL_ADMIN
            && ! $user->hasAnyRole(Role::editableStaffNames())
        ) {
            throw ValidationException::withMessages([
                'staff' => 'Legacy admin accounts are managed from the existing admin-access flow.',
            ]);
        }
    }

    private function guardSuspendableTarget(AppUser $user): void
    {
        if ($this->auditStaffStatus($user) === null) {
            throw ValidationException::withMessages([
                'staff' => 'This user does not currently have a managed staff role.',
            ]);
        }
    }

    private function ensureNotRemovingLastActiveSuperadmin(AppUser $user): void
    {
        if (! $user->isSuperadmin() || ! $user->hasActiveStaffAccess()) {
            return;
        }

        if ($this->activeSuperadminCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'You cannot remove the final active Superadmin.',
            ]);
        }
    }

    private function ensureNotSuspendingLastActiveSuperadmin(AppUser $user): void
    {
        if (! $user->isSuperadmin() || ! $user->hasActiveStaffAccess()) {
            return;
        }

        if ($this->activeSuperadminCount() <= 1) {
            throw ValidationException::withMessages([
                'staff' => 'You cannot suspend the final active Superadmin.',
            ]);
        }
    }

    private function activeSuperadminCount(): int
    {
        return AppUser::query()
            ->with(['adminProfile', 'roles', 'staffAccessControl'])
            ->where(function (Builder $builder): void {
                $builder
                    ->whereHas('roles', function (Builder $roleQuery): void {
                        $roleQuery->where('name', Role::SUPERADMIN);
                    })
                    ->orWhereHas('adminProfile', function (Builder $profileQuery): void {
                        $profileQuery->where(
                            'access_level',
                            AdminProfile::ACCESS_LEVEL_SUPERADMIN,
                        );
                    });
            })
            ->lockForUpdate()
            ->get()
            ->filter(fn (AppUser $user): bool => $user->hasActiveStaffAccess())
            ->count();
    }

    private function syncLegacySuperadminProfile(AppUser $user): void
    {
        if ($this->userAlreadyHasEditableRole($user, Role::SUPERADMIN)) {
            AdminProfile::query()->updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'fullname' => $user->adminProfile?->fullname ?? $this->resolveAdminFullName($user),
                    'access_level' => AdminProfile::ACCESS_LEVEL_SUPERADMIN,
                    'profile_pic_path' => $user->adminProfile?->profile_pic_path
                        ?? $user->userProfile?->profile_pic_path,
                ],
            );

            return;
        }

        if ($user->adminProfile?->access_level !== AdminProfile::ACCESS_LEVEL_SUPERADMIN) {
            return;
        }

        if ($user->hasRole(Role::ADMIN)) {
            $user->adminProfile()->update([
                'fullname' => $user->adminProfile?->fullname ?? $this->resolveAdminFullName($user),
                'access_level' => AdminProfile::ACCESS_LEVEL_ADMIN,
            ]);

            return;
        }

        $user->adminProfile()->delete();
        $user->unsetRelation('adminProfile');
    }

    private function ensureStaffAccessRowForAssignment(AppUser $user): void
    {
        if (! $this->schemaCapabilities->hasTable('staff_access_controls')) {
            return;
        }

        if ($user->staffAccessControl instanceof StaffAccessControl) {
            return;
        }

        StaffAccessControl::query()->create([
            'user_id' => $user->user_id,
            'status' => StaffAccessControl::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);
    }

    private function activateStaffAccessRow(AppUser $user): void
    {
        if (! $this->schemaCapabilities->hasTable('staff_access_controls')) {
            return;
        }

        StaffAccessControl::query()->updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'status' => StaffAccessControl::STATUS_ACTIVE,
                'suspended_at' => null,
                'suspended_by' => null,
                'suspension_reason' => null,
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function visibleRoleNames(AppUser $user): array
    {
        $roles = [];

        if ($user->isSuperadmin()) {
            $roles[] = Role::SUPERADMIN;
        }

        foreach ([Role::LOAN_PROCESSOR, Role::LOAN_MANAGER] as $roleName) {
            if ($user->hasRole($roleName)) {
                $roles[] = $roleName;
            }
        }

        if ($user->hasMemberAccess() || $user->hasRole(Role::MEMBER)) {
            $roles[] = Role::MEMBER;
        }

        return array_values(array_unique($roles));
    }

    private function auditStaffStatus(AppUser $user): ?string
    {
        if (! $user->hasAnyRole(Role::editableStaffNames()) && ! $user->isLegacySuperadmin()) {
            return null;
        }

        return $user->isStaffAccessSuspended()
            ? StaffAccessControl::STATUS_SUSPENDED
            : StaffAccessControl::STATUS_ACTIVE;
    }

    private function userAlreadyHasEditableRole(AppUser $user, string $roleName): bool
    {
        if ($roleName === Role::SUPERADMIN) {
            return $user->isSuperadmin();
        }

        return $user->hasRole($roleName);
    }

    private function resolveAdminFullName(AppUser $user): string
    {
        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
            $wmasterName = $user->wmaster?->displayName();

            if (is_string($wmasterName) && trim($wmasterName) !== '') {
                return $wmasterName;
            }
        }

        $username = trim((string) $user->username);

        if ($username !== '') {
            return $username;
        }

        $email = trim((string) $user->email);

        return $email !== '' ? $email : 'Superadmin';
    }

    /**
     * @param  array<int, string>  $beforeRoles
     * @param  array<int, string>  $afterRoles
     * @param  array<string, mixed>|null  $metadata
     */
    private function recordUserRoleChange(
        AppUser $target,
        AppUser $actor,
        string $action,
        ?string $roleName,
        array $beforeRoles,
        array $afterRoles,
        ?string $beforeStaffStatus,
        ?string $afterStaffStatus,
        string $reason,
        ?array $metadata,
    ): void {
        UserRoleChange::query()->create([
            'target_user_id' => $target->user_id,
            'actor_user_id' => $actor->user_id,
            'action' => $action,
            'role_name' => $roleName,
            'before_roles_json' => $beforeRoles,
            'after_roles_json' => $afterRoles,
            'before_staff_status' => $beforeStaffStatus,
            'after_staff_status' => $afterStaffStatus,
            'reason' => $reason,
            'metadata_json' => $metadata,
        ]);
    }
}
