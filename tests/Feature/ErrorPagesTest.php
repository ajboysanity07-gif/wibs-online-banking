<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

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
