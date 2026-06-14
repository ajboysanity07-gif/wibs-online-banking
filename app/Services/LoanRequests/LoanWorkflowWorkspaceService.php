<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LoanWorkflowWorkspaceService
{
    public const WORKSPACE_MEMBER = 'member';

    public const WORKSPACE_STAFF = 'staff';

    private const ACTIVE_WORKSPACE_SESSION_KEY = 'active_workspace';

    public function canAccess(?AppUser $user): bool
    {
        return $this->canAccessLoanWorkflow($user);
    }

    public function canAccessMemberWorkspace(AppUser $user): bool
    {
        return $user->hasMemberAccess();
    }

    public function canAccessStaffWorkspace(?AppUser $user): bool
    {
        if (! $user instanceof AppUser) {
            return false;
        }

        if (! $user->hasActiveStaffAccess()) {
            return false;
        }

        $this->loadWorkspaceRelations($user);

        if ($user->isAdmin() || $user->isSuperadmin()) {
            return true;
        }

        return $user->hasAnyRole(Role::editableStaffNames());
    }

    public function canAccessLoanWorkflow(?AppUser $user): bool
    {
        if (! $user instanceof AppUser) {
            return false;
        }

        $this->loadWorkspaceRelations($user);

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
    public function availableWorkspaces(?AppUser $user): array
    {
        if (! $user instanceof AppUser) {
            return [];
        }

        $available = [];

        if ($this->canAccessMemberWorkspace($user)) {
            $available[] = self::WORKSPACE_MEMBER;
        }

        if ($this->canAccessStaffWorkspace($user)) {
            $available[] = self::WORKSPACE_STAFF;
        }

        return $available;
    }

    public function hasMultipleWorkspaces(?AppUser $user): bool
    {
        return count($this->availableWorkspaces($user)) > 1;
    }

    public function canAccessWorkspace(AppUser $user, string $workspace): bool
    {
        return match ($workspace) {
            self::WORKSPACE_MEMBER => $this->canAccessMemberWorkspace($user),
            self::WORKSPACE_STAFF => $this->canAccessStaffWorkspace($user),
            default => false,
        };
    }

    public function resolveActiveWorkspace(
        Request $request,
        ?AppUser $user,
    ): ?string {
        if (! $user instanceof AppUser) {
            $request->session()->forget(self::ACTIVE_WORKSPACE_SESSION_KEY);

            return null;
        }

        $available = $this->availableWorkspaces($user);

        if ($available === []) {
            $request->session()->forget(self::ACTIVE_WORKSPACE_SESSION_KEY);

            return null;
        }

        $routeWorkspace = $this->workspaceForRoute($request);

        if (
            $routeWorkspace !== null
            && in_array($routeWorkspace, $available, true)
        ) {
            if ($request->isMethod('GET')) {
                $request->session()->put(
                    self::ACTIVE_WORKSPACE_SESSION_KEY,
                    $routeWorkspace,
                );
            }

            return $routeWorkspace;
        }

        $storedWorkspace = $this->normalizedStoredWorkspace($request, $user);

        if ($storedWorkspace !== null) {
            return $storedWorkspace;
        }

        if (count($available) === 1) {
            return $available[0];
        }

        return null;
    }

    public function dashboardRedirectPath(
        Request $request,
        AppUser $user,
    ): ?string {
        $this->loadWorkspaceRelations($user);

        if ($this->hasMultipleWorkspaces($user)) {
            if ($request->boolean('choose')) {
                return null;
            }

            $storedWorkspace = $this->normalizedStoredWorkspace($request, $user);

            return $storedWorkspace === null
                ? null
                : $this->defaultRouteForWorkspace($user, $storedWorkspace);
        }

        $availableWorkspaces = $this->availableWorkspaces($user);

        if ($availableWorkspaces !== []) {
            return $this->defaultRouteForWorkspace(
                $user,
                $availableWorkspaces[0],
            );
        }

        return $this->fallbackRoute($user);
    }

    public function postAuthenticationRedirectPath(
        Request $request,
        AppUser $user,
    ): string {
        return $this->dashboardRedirectPath($request, $user)
            ?? route('dashboard', absolute: false);
    }

    public function defaultRouteForWorkspace(
        AppUser $user,
        string $workspace,
    ): string {
        $this->loadWorkspaceRelations($user);

        return match ($workspace) {
            self::WORKSPACE_MEMBER => $this->memberWorkspaceRoute($user),
            self::WORKSPACE_STAFF => $this->staffWorkspaceRoute($user),
            default => $this->fallbackRoute($user),
        };
    }

    public function switchWorkspace(
        Request $request,
        AppUser $user,
        string $workspace,
    ): string {
        $request->session()->put(self::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);

        return $this->defaultRouteForWorkspace($user, $workspace);
    }

    /**
     * @return list<string>
     */
    public function workflowRoles(?AppUser $user): array
    {
        if (! $user instanceof AppUser) {
            return [];
        }

        $this->loadWorkspaceRelations($user);

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

        $this->loadWorkspaceRelations($user);

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
        $this->loadWorkspaceRelations($user);

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
        $this->loadWorkspaceRelations($user);

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

    private function memberWorkspaceRoute(AppUser $user): string
    {
        if ($user->userProfile?->status === 'suspended') {
            return route('pending-approval', absolute: false);
        }

        if (! $user->memberApplicationProfileIsComplete()) {
            return route('profile.edit', [
                'onboarding' => 1,
            ], false);
        }

        return route('client.dashboard', absolute: false);
    }

    private function staffWorkspaceRoute(AppUser $user): string
    {
        if ($user->canViewStaffManagement()) {
            return route('superadmin.staff.index', absolute: false);
        }

        if ($user->isAdmin() && $user->hasActiveStaffAccess()) {
            return route('admin.dashboard', absolute: false);
        }

        if ($this->canAccessLoanWorkflow($user)) {
            return route('staff.loan-requests.index', absolute: false);
        }

        return $this->fallbackRoute($user);
    }

    private function fallbackRoute(AppUser $user): string
    {
        $this->loadWorkspaceRelations($user);

        if ($user->userProfile?->status === 'suspended') {
            return route('pending-approval', absolute: false);
        }

        if (! $user->hasMemberAccess()) {
            return route('profile.edit', absolute: false);
        }

        return $this->memberWorkspaceRoute($user);
    }

    private function normalizedStoredWorkspace(
        Request $request,
        AppUser $user,
    ): ?string {
        $storedWorkspace = $request->session()->get(
            self::ACTIVE_WORKSPACE_SESSION_KEY,
        );

        if (! is_string($storedWorkspace)) {
            if ($storedWorkspace !== null) {
                $request->session()->forget(self::ACTIVE_WORKSPACE_SESSION_KEY);
            }

            return null;
        }

        if (
            ! in_array($storedWorkspace, [
                self::WORKSPACE_MEMBER,
                self::WORKSPACE_STAFF,
            ], true)
            || ! in_array(
                $storedWorkspace,
                $this->availableWorkspaces($user),
                true,
            )
        ) {
            $request->session()->forget(self::ACTIVE_WORKSPACE_SESSION_KEY);

            return null;
        }

        return $storedWorkspace;
    }

    private function workspaceForRoute(Request $request): ?string
    {
        if ($request->routeIs('client.*')) {
            return self::WORKSPACE_MEMBER;
        }

        if (
            $request->routeIs('staff.*')
            || $request->routeIs('admin.*')
            || $request->routeIs('superadmin.*')
        ) {
            return self::WORKSPACE_STAFF;
        }

        return null;
    }

    private function loadWorkspaceRelations(AppUser $user): void
    {
        $user->loadMissing(
            'adminProfile',
            'userProfile',
            'memberApplicationProfile',
            'roles.permissions',
            'staffAccessControl',
        );
    }
}
