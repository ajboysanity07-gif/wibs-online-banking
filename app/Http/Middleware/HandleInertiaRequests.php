<?php

namespace App\Http\Middleware;

use App\Services\OrganizationSettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
        $branding = app(OrganizationSettingsService::class)->branding();

        $user = $request->user();
        $user?->loadMissing('adminProfile', 'userProfile');
        $isAdmin = $user?->isAdmin() ?? false;

        return [
            ...parent::share($request),
            'name' => $branding['appTitle'],
            'branding' => $branding,
            'auth' => [
                'user' => $user?->withoutRelations(),
                'isAdmin' => $isAdmin,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
