<?php

use Illuminate\Support\Facades\Vite;

beforeEach(function (): void {
    // A running `npm run dev` leaves public/hot behind, which disables the CSP.
    Vite::useHotFile(storage_path('framework/testing/no-hot'));
});

test('web responses carry security headers and a nonce-based CSP', function (): void {
    $response = $this->get('/login');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("object-src 'none'")->toContain("frame-ancestors 'self'");
    // The printable loan-payments report loads organization fonts from Google Fonts.
    expect($csp)->toContain('https://fonts.googleapis.com')->toContain('https://fonts.gstatic.com');
    expect(preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $m))->toBe(1);
    expect($response->getContent())->toContain('nonce="'.$m[1].'"');
});

test('HSTS is only sent over HTTPS', function (): void {
    $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
});
