<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureMemberProfileComplete;
use App\Http\Middleware\EnsureMemberVerified;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\EnsureUserApproved;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'approved' => EnsureUserApproved::class,
            'member-profile-complete' => EnsureMemberProfileComplete::class,
            'member-verified' => EnsureMemberVerified::class,
            'superadmin' => EnsureSuperadmin::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            $handledStatuses = [403, 404, 419, 429, 500, 503];

            if (! in_array($status, $handledStatuses, true)) {
                return $response;
            }

            $isInertia = $request->header('X-Inertia') !== null;

            if ($request->expectsJson() && ! $isInertia) {
                return $response;
            }

            if (! $isInertia && ! $request->acceptsHtml()) {
                return $response;
            }

            if (config('app.debug') && app()->environment(['local', 'testing']) && $status >= 500) {
                return $response;
            }

            $request->attributes->set('inertia_error_page', true);

            return Inertia::render('errors/error', [
                'status' => $status,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
