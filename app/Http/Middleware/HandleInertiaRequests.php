<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $organizationSettings = app(OrganizationSettingsService::class);

        if ($this->isErrorPageRequest($request)) {
            $branding = $this->stripReportEmbeds($organizationSettings->fallbackBranding());

            return [
                ...parent::share($request),
                'name' => $branding['appTitle'],
                'branding' => $branding,
                'auth' => $this->guestAuthState(),
                'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ];
        }

        $branding = $this->resolveBranding($request, $organizationSettings);
        $auth = $this->resolveAuth($request);

        return [
            ...parent::share($request),
            'name' => $branding['appTitle'],
            'branding' => $branding,
            'auth' => $auth,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBranding(
        Request $request,
        OrganizationSettingsService $organizationSettings,
    ): array {
        try {
            return $this->stripReportEmbeds($organizationSettings->branding());
        } catch (Throwable $exception) {
            Log::warning('Inertia shared branding resolution failed. Using fallback branding.', [
                'path' => $request->path(),
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->stripReportEmbeds($organizationSettings->fallbackBranding());
        }
    }

    /**
     * The browser never reads `reportHeader.designData` or
     * `reportTypography.fontFaceCss` -- they exist only for PDF/report
     * rendering (Blade views pull them straight from
     * OrganizationSettingsService::branding(), not from Inertia props). Left
     * in the shared prop, they ship several MB of base64 image/font data on
     * every page load, including the public homepage.
     *
     * @param  array<string, mixed>  $branding
     * @return array<string, mixed>
     */
    private function stripReportEmbeds(array $branding): array
    {
        if (isset($branding['reportHeader']['designData'])) {
            $branding['reportHeader']['designData'] = null;
        }

        if (isset($branding['reportTypography']['fontFaceCss'])) {
            $branding['reportTypography']['fontFaceCss'] = null;
        }

        if (isset($branding['reports']['header']['designData'])) {
            $branding['reports']['header']['designData'] = null;
        }

        if (isset($branding['reports']['typography']['fontFaceCss'])) {
            $branding['reports']['typography']['fontFaceCss'] = null;
        }

        return $branding;
    }

    /**
     * @return array{
     *     user: mixed,
     *     isAdmin: bool,
     *     isSuperadmin: bool,
     *     hasMemberAccess: bool,
     *     isAdminOnly: bool,
     *     isHybrid: bool,
     *     availableWorkspaces: array<int, string>,
     *     activeWorkspace: string|null,
     *     hasMultipleWorkspaces: bool,
     *     experience: mixed,
     *     hasActiveStaffAccess: bool,
     *     canAccessLoanWorkflow: bool,
     *     canViewStaffMembers: bool,
     *     loanWorkflowRoles: array<int, string>,
     *     loanWorkflowPermissions: array<int, string>
     * }
     */
    private function resolveAuth(Request $request): array
    {
        try {
            $user = $request->user();
            $user?->loadMissing(
                'adminProfile',
                'userProfile',
                'roles.permissions',
                'staffAccessControl',
            );
            $workspaceService = app(LoanWorkflowWorkspaceService::class);
            $availableWorkspaces = $workspaceService->availableWorkspaces($user);

            return [
                'user' => $user?->withoutRelations(),
                'isAdmin' => $user?->isAdmin() ?? false,
                'isSuperadmin' => $user?->isSuperadmin() ?? false,
                'hasMemberAccess' => $user?->hasMemberAccess() ?? false,
                'isAdminOnly' => $user?->isAdminOnly() ?? false,
                'isHybrid' => $workspaceService->hasMultipleWorkspaces($user),
                'availableWorkspaces' => $availableWorkspaces,
                'activeWorkspace' => $workspaceService->resolveActiveWorkspace($request, $user),
                'hasMultipleWorkspaces' => count($availableWorkspaces) > 1,
                'experience' => $user?->experienceType(),
                'hasActiveStaffAccess' => $user?->hasActiveStaffAccess() ?? false,
                'canAccessLoanWorkflow' => $workspaceService->canAccessLoanWorkflow($user),
                'canViewStaffMembers' => $user?->hasPermission(Permission::MEMBER_VIEW) ?? false,
                'loanWorkflowRoles' => $workspaceService->workflowRoles($user),
                'loanWorkflowPermissions' => $workspaceService->workflowPermissions($user),
            ];
        } catch (Throwable $exception) {
            Log::warning('Inertia shared auth resolution failed. Using guest auth state.', [
                'path' => $request->path(),
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->guestAuthState();
        }
    }

    private function isErrorPageRequest(Request $request): bool
    {
        return $request->attributes->get('inertia_error_page') === true;
    }

    /**
     * @return array{
     *     user: mixed,
     *     isAdmin: bool,
     *     isSuperadmin: bool,
     *     hasMemberAccess: bool,
     *     isAdminOnly: bool,
     *     isHybrid: bool,
     *     availableWorkspaces: array<int, string>,
     *     activeWorkspace: string|null,
     *     hasMultipleWorkspaces: bool,
     *     experience: mixed,
     *     hasActiveStaffAccess: bool,
     *     canAccessLoanWorkflow: bool,
     *     canViewStaffMembers: bool,
     *     loanWorkflowRoles: array<int, string>,
     *     loanWorkflowPermissions: array<int, string>
     * }
     */
    private function guestAuthState(): array
    {
        return [
            'user' => null,
            'isAdmin' => false,
            'isSuperadmin' => false,
            'hasMemberAccess' => false,
            'isAdminOnly' => false,
            'isHybrid' => false,
            'availableWorkspaces' => [],
            'activeWorkspace' => null,
            'hasMultipleWorkspaces' => false,
            'experience' => null,
            'hasActiveStaffAccess' => false,
            'canAccessLoanWorkflow' => false,
            'canViewStaffMembers' => false,
            'loanWorkflowRoles' => [],
            'loanWorkflowPermissions' => [],
        ];
    }
}
