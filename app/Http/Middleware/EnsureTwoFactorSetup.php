<?php

namespace App\Http\Middleware;

use App\Models\AppUser;
use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorSetup
{
    private const REQUIRED_ROLES = [Role::SUPERADMIN, Role::LOAN_MANAGER];

    private const EXEMPT_PATHS = [
        'settings/security',
        'settings/two-factor',
        'user/two-factor-authentication',
        'user/confirmed-two-factor-authentication',
        'user/two-factor-qr-code',
        'user/two-factor-secret-key',
        'user/two-factor-recovery-codes',
        'logout',
        'spa/auth/logout',
        'settings/password',
        'confirm-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            return $next($request);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            return $next($request);
        }

        if (! $this->requiresTwoFactor($user)) {
            return $next($request);
        }

        if ($this->hasTwoFactorConfirmed($user)) {
            return $next($request);
        }

        if ($this->isExemptPath($request)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Two-factor authentication setup is required.',
                'requires_two_factor_setup' => true,
            ], 403);
        }

        return redirect()->route('settings.security')
            ->with('status', 'two-factor-setup-required');
    }

    private function requiresTwoFactor(AppUser $user): bool
    {
        return $user->hasAnyRole(self::REQUIRED_ROLES);
    }

    private function hasTwoFactorConfirmed(AppUser $user): bool
    {
        return $user->two_factor_confirmed_at !== null
            && $user->two_factor_secret !== null;
    }

    private function isExemptPath(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        foreach (self::EXEMPT_PATHS as $exempt) {
            if ($path === $exempt || str_starts_with($path, $exempt)) {
                return true;
            }
        }

        return false;
    }
}
