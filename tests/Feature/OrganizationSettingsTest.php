<?php

use App\Models\OrganizationSetting;
use App\Services\OrganizationSettingsService;
use Illuminate\Support\Facades\Storage;

test('branding falls back to defaults when no settings exist', function () {
    config(['app.name' => 'Acme Portal']);

    $branding = app(OrganizationSettingsService::class)->branding();

    expect($branding['companyName'])->toBe('Acme');
    expect($branding['portalLabel'])->toBe('Member Portal');
    expect($branding['appTitle'])->toBe('Member Portal - Acme');
    expect($branding['logoPath'])->toBeNull();
    expect($branding['logoUrl'])->not->toBe('');
    expect($branding['faviconPath'])->toBeNull();
    expect($branding['faviconUrl'])->toBe(asset('favicon.ico'));
    expect($branding['brandPrimaryColor'])->toBeNull();
    expect($branding['brandAccentColor'])->toBeNull();
    expect($branding['supportEmail'])->toBeNull();
    expect($branding['supportPhone'])->toBeNull();
    expect($branding['supportContactName'])->toBeNull();
});

test('branding uses stored organization settings when available', function () {
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Corp',
        'company_logo_path' => 'branding/logo.png',
        'portal_label' => 'Acme Portal',
        'favicon_path' => 'branding/favicon.ico',
        'brand_primary_color' => '#111111',
        'brand_accent_color' => '#222222',
        'support_email' => 'support@acme.test',
        'support_phone' => '123456789',
        'support_contact_name' => 'Support Team',
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();

    expect($branding['companyName'])->toBe('Acme Corp');
    expect($branding['portalLabel'])->toBe('Acme Portal');
    expect($branding['appTitle'])->toBe('Acme Portal - Acme Corp');
    expect($branding['logoPath'])->toBe('branding/logo.png');
    expect($branding['logoUrl'])
        ->toBe(Storage::disk('public')->url('branding/logo.png'));
    expect($branding['faviconPath'])->toBe('branding/favicon.ico');
    expect($branding['faviconUrl'])
        ->toBe(Storage::disk('public')->url('branding/favicon.ico'));
    expect($branding['brandPrimaryColor'])->toBe('#111111');
    expect($branding['brandAccentColor'])->toBe('#222222');
    expect($branding['supportEmail'])->toBe('support@acme.test');
    expect($branding['supportPhone'])->toBe('123456789');
    expect($branding['supportContactName'])->toBe('Support Team');
});
