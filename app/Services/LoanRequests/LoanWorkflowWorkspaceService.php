<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;

class LoanWorkflowWorkspaceService
{
    public function canAccess(?AppUser $user): bool
    {
        if (! $user instanceof AppUser) {
            return false;
        }

        $user->loadMissing('roles.permissions');

        if (! $user->hasActiveStaffAccess()) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return $user->hasPermission(Permission::LOAN_VIEW)
                || $user->isLegacySuperadmin();
        }

        return $user->hasPermission(Permission::LOAN_VIEW)
            && $user->hasAnyRole([
                Role::SUPERADMIN,
                Role::LOAN_OFFICER,
                Role::LOAN_MANAGER,
            ]);
    }

    /**
     * @return list<string>
     */
    public function workflowRoles(?AppUser $user): array
    {
        if (! $user instanceof AppUser) {
            return [];
        }

        $user->loadMissing('roles.permissions');

        $roles = [];

        if ($user->hasRole(Role::SUPERADMIN) || $user->isLegacySuperadmin()) {
            $roles[] = Role::SUPERADMIN;
        }

        foreach ([Role::LOAN_OFFICER, Role::LOAN_MANAGER] as $roleName) {
            if ($user->hasRole($roleName)) {
                $roles[] = $roleName;
            }
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    public function workflowPermissions(?AppUser $user): array
    {
        if (! $user instanceof AppUser) {
            return [];
        }

        $user->loadMissing('roles.permissions');

        $permissions = $user->roles
            ->flatMap(static fn (Role $role) => $role->permissions->pluck('name'))
            ->filter(
                static fn (mixed $permission): bool => is_string($permission)
                    && str_starts_with($permission, 'loan.'),
            )
            ->unique()
            ->values()
            ->all();

        if (
            $user->isLegacySuperadmin() &&
            ! in_array(Permission::LOAN_VIEW, $permissions, true)
        ) {
            $permissions[] = Permission::LOAN_VIEW;
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public function visibleStatusesFor(AppUser $user): array
    {
        $user->loadMissing('roles.permissions');

        if (
            $user->hasRole(Role::SUPERADMIN) ||
            $user->isLegacySuperadmin()
        ) {
            return array_values(array_filter(
                LoanRequestStatus::requestFilterValues(),
                static fn (string $status): bool => $status !== LoanRequestStatus::Draft->value,
            ));
        }

        $statuses = [];

        if ($user->hasRole(Role::LOAN_OFFICER)) {
            $statuses = array_merge($statuses, [
                LoanRequestStatus::Submitted->value,
                LoanRequestStatus::PendingCoMakerSignatures->value,
                LoanRequestStatus::PendingReview->value,
                LoanRequestStatus::UnderReview->value,
                LoanRequestStatus::NeedsRevision->value,
                LoanRequestStatus::Rejected->value,
                LoanRequestStatus::RecommendedForApproval->value,
            ]);
        }

        if ($user->hasRole(Role::LOAN_MANAGER)) {
            $statuses = array_merge($statuses, [
                LoanRequestStatus::RecommendedForApproval->value,
                LoanRequestStatus::Approved->value,
                LoanRequestStatus::Declined->value,
                LoanRequestStatus::ConvertedToLoan->value,
            ]);
        }

        return array_values(array_unique($statuses));
    }

    public function applyVisibleScope(Builder $query, AppUser $user): void
    {
        $user->loadMissing('roles.permissions');

        if (
            $user->hasRole(Role::SUPERADMIN) ||
            $user->isLegacySuperadmin()
        ) {
            return;
        }

        $statuses = $this->visibleStatusesFor($user);

        if ($statuses === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('status', $statuses);
    }
}
