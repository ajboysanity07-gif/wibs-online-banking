<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

test('renders custom error pages for html requests', function (int $status): void {
    Route::get("/__test-error-{$status}", function () use ($status): void {
        abort($status);
    });

    $this->get("/__test-error-{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', $status));
})->with([403, 404, 419, 429, 500, 503]);

test('preserves json error responses for api requests', function (): void {
    Route::get('/__test-error-json', function (): void {
        abort(403);
    });

    $this->getJson('/__test-error-json')
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});

test('error page shared props bypass db-backed branding and auth lookups', function (): void {
    $fallbackBranding = app(OrganizationSettingsService::class)->fallbackBranding();

    mock(OrganizationSettingsService::class, function ($mock) use ($fallbackBranding): void {
        $mock->shouldNotReceive('branding');
        $mock->shouldReceive('fallbackBranding')
            ->once()
            ->andReturn($fallbackBranding);
    });

    $request = Request::create('/__test-error-share', 'GET');
    $request->attributes->set('inertia_error_page', true);
    $request->setUserResolver(function (): never {
        throw new \RuntimeException('The error page share path should not resolve a user.');
    });

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['name'])->toBe($fallbackBranding['appTitle']);
    expect($shared['branding'])->toMatchArray($fallbackBranding);
    expect($shared['auth'])->toBe([
        'user' => null,
        'isAdmin' => false,
        'isSuperadmin' => false,
        'hasMemberAccess' => false,
        'isAdminOnly' => false,
        'isHybrid' => false,
        'experience' => null,
    ]);
});

test('handled 500 responses still render the custom error page when branding lookups fail', function (): void {
    $fallbackBranding = app(OrganizationSettingsService::class)->fallbackBranding();

    mock(OrganizationSettingsService::class, function ($mock) use ($fallbackBranding): void {
        $mock->shouldNotReceive('branding');
        $mock->shouldReceive('fallbackBranding')
            ->atLeast()
            ->once()
            ->andReturn($fallbackBranding);
    });

    Route::get('/__test-error-db-outage', function (): void {
        abort(500);
    });

    config(['app.debug' => false]);

    $this->get('/__test-error-db-outage')
        ->assertStatus(500)
        ->assertSee($fallbackBranding['appTitle'], false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 500));
});
