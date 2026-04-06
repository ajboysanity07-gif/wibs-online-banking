<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $user->loadMissing('userProfile', 'adminProfile');

        if ($user->isAdminOnly()) {
            return $next($request);
        }

        if ($user->userProfile?->status !== 'suspended') {
            return $next($request);
        }

        if ($request->routeIs('pending-approval') || $request->routeIs('logout')) {
            return $next($request);
        }

        return to_route('pending-approval');
    }
}
