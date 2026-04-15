<?php

namespace App\Http\Middleware;

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
            $branding = $organizationSettings->fallbackBranding();

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
            return $organizationSettings->branding();
        } catch (Throwable $exception) {
            Log::warning('Inertia shared branding resolution failed. Using fallback branding.', [
                'path' => $request->path(),
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $organizationSettings->fallbackBranding();
        }
    }

    /**
     * @return array{
     *     user: mixed,
     *     isAdmin: bool,
     *     isSuperadmin: bool,
     *     hasMemberAccess: bool,
     *     isAdminOnly: bool,
     *     isHybrid: bool,
     *     experience: mixed
     * }
     */
    private function resolveAuth(Request $request): array
    {
        try {
            $user = $request->user();
            $user?->loadMissing('adminProfile', 'userProfile');

            return [
                'user' => $user?->withoutRelations(),
                'isAdmin' => $user?->isAdmin() ?? false,
                'isSuperadmin' => $user?->isSuperadmin() ?? false,
                'hasMemberAccess' => $user?->hasMemberAccess() ?? false,
                'isAdminOnly' => $user?->isAdminOnly() ?? false,
                'isHybrid' => $user?->isHybrid() ?? false,
                'experience' => $user?->experienceType(),
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
     *     experience: mixed
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
            'experience' => null,
        ];
    }
}
