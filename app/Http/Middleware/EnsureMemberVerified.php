<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberVerified
{
    private const VERIFICATION_SESSION_KEY = 'member_verification';

    private const VERIFICATION_TTL_MINUTES = 15;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $verification = $request->session()->get(self::VERIFICATION_SESSION_KEY);

        if (! is_array($verification)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $verifiedAt = $verification['verified_at'] ?? null;

        if (! is_numeric($verifiedAt)) {
            $request->session()->forget(self::VERIFICATION_SESSION_KEY);

            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $expiresAt = CarbonImmutable::createFromTimestamp((int) $verifiedAt)
            ->addMinutes(self::VERIFICATION_TTL_MINUTES);

        if (now()->greaterThan($expiresAt)) {
            $request->session()->forget(self::VERIFICATION_SESSION_KEY);

            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
