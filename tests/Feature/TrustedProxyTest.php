<?php

use Illuminate\Support\Facades\Route;

test('forwarded proto is trusted for secure urls', function () {
    Route::get('/proxy-check', function () {
        return response()->json([
            'secure' => request()->isSecure(),
            'url' => url('/'),
        ]);
    });

    $response = $this->get('/proxy-check', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'example.test',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-For' => '203.0.113.10',
        'X-Forwarded-Prefix' => '/app',
    ]);

    $response->assertSuccessful();

    $payload = $response->json();

    expect($payload['secure'])->toBeTrue();
    expect($payload['url'])->toStartWith('https://example.test');
});
