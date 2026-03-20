<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberProfileComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $user->loadMissing('adminProfile', 'memberApplicationProfile');

        if ($user->adminProfile !== null) {
            return $next($request);
        }

        $memberProfile = $user->memberApplicationProfile;

        if ($memberProfile !== null && $memberProfile->isComplete()) {
            return $next($request);
        }

        return to_route('profile.edit', ['onboarding' => 1]);
    }
}
