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
    expect($branding['logoPreset'])->toBe(
        OrganizationSettingsService::LOGO_PRESET_MARK,
    );
    expect($branding['logoIsWordmark'])->toBeFalse();
    expect($branding['logoPath'])->toBeNull();
    expect($branding['logoUrl'])->toBe(asset('mrdinc-logo-mark.png'));
    expect($branding['logoMarkUrl'])->toBe(asset('mrdinc-logo-mark.png'));
    expect($branding['logoFullUrl'])->toBe(asset('mrdinc-logo.png'));
    expect($branding['logoMarkDefaultUrl'])->toBe(asset('mrdinc-logo-mark.png'));
    expect($branding['logoFullDefaultUrl'])->toBe(asset('mrdinc-logo.png'));
    expect($branding['logoMarkIsDefault'])->toBeTrue();
    expect($branding['logoFullIsDefault'])->toBeTrue();
    expect($branding['faviconPath'])->toBeNull();
    expect($branding['faviconUrl'])->toBe(asset('favicon.ico'));
    expect($branding['faviconDefaultUrl'])->toBe(asset('favicon.ico'));
    expect($branding['brandPrimaryColor'])->toBeNull();
    expect($branding['brandAccentColor'])->toBeNull();
    expect($branding['supportEmail'])->toBeNull();
    expect($branding['supportPhone'])->toBeNull();
    expect($branding['supportContactName'])->toBeNull();
});

test('branding uses stored organization settings when available', function () {
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Corp',
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_MARK,
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
    expect($branding['logoPreset'])->toBe(
        OrganizationSettingsService::LOGO_PRESET_MARK,
    );
    expect($branding['logoIsWordmark'])->toBeFalse();
    expect($branding['logoPath'])->toBeNull();
    expect($branding['logoUrl'])->toBe(asset('mrdinc-logo-mark.png'));
    expect($branding['logoMarkIsDefault'])->toBeTrue();
    expect($branding['logoFullIsDefault'])->toBeTrue();
    expect($branding['faviconPath'])->toBe('branding/favicon.ico');
    expect($branding['faviconUrl'])
        ->toBe(Storage::disk('public')->url('branding/favicon.ico'));
    expect($branding['faviconDefaultUrl'])->toBe(asset('favicon.ico'));
    expect($branding['brandPrimaryColor'])->toBe('#111111');
    expect($branding['brandAccentColor'])->toBe('#222222');
    expect($branding['supportEmail'])->toBe('support@acme.test');
    expect($branding['supportPhone'])->toBe('123456789');
    expect($branding['supportContactName'])->toBe('Support Team');
});

test('branding supports built-in full logo preset', function () {
    OrganizationSetting::factory()->create([
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_FULL,
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();

    expect($branding['logoPreset'])->toBe(
        OrganizationSettingsService::LOGO_PRESET_FULL,
    );
    expect($branding['logoIsWordmark'])->toBeTrue();
    expect($branding['logoUrl'])->toBe(asset('mrdinc-logo.png'));
});

test('branding uses stored logo mark and full overrides when available', function () {
    Storage::fake('public');

    Storage::disk('public')->put('branding/logos/mark/custom-mark.png', 'mark');
    Storage::disk('public')->put('branding/logos/full/custom-full.png', 'full');

    OrganizationSetting::factory()->create([
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_FULL,
        'logo_mark_path' => 'branding/logos/mark/custom-mark.png',
        'logo_full_path' => 'branding/logos/full/custom-full.png',
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();

    expect($branding['logoMarkIsDefault'])->toBeFalse();
    expect($branding['logoFullIsDefault'])->toBeFalse();
    expect($branding['logoMarkUrl'])->toBe(
        Storage::disk('public')->url('branding/logos/mark/custom-mark.png'),
    );
    expect($branding['logoFullUrl'])->toBe(
        Storage::disk('public')->url('branding/logos/full/custom-full.png'),
    );
    expect($branding['logoUrl'])->toBe(
        Storage::disk('public')->url('branding/logos/full/custom-full.png'),
    );
});
