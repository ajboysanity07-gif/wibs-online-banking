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
    expect($branding['reportHeader']['title'])->toBeNull();
    expect($branding['reportHeader']['tagline'])->toBeNull();
    expect($branding['reportHeader']['showLogo'])->toBeTrue();
    expect($branding['reportHeader']['showCompanyName'])->toBeTrue();
    expect($branding['reportHeader']['alignment'])->toBe('center');
    expect($branding['reportTypography']['headerTitle']['family'])
        ->toBe('DejaVu Sans');
    expect($branding['reportTypography']['headerTitle']['color'])->toBeNull();
    expect($branding['reportTypography']['headerTagline']['color'])->toBeNull();
    expect($branding['reportTypography']['label']['color'])->toBeNull();
    expect($branding['reportTypography']['value']['color'])->toBeNull();
    expect($branding['reportTypography']['headerTitle']['size'])->toBe(14);
    expect($branding['reportTypography']['label']['size'])->toBe(8);
    expect($branding['reportTypography']['value']['size'])->toBe(10);
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
        'report_header_title' => 'Loan Request Summary',
        'report_header_tagline' => 'Confidential',
        'report_header_show_logo' => false,
        'report_header_show_company_name' => true,
        'report_header_alignment' => 'right',
        'report_header_font_color' => '#112233',
        'report_header_tagline_color' => '#221133',
        'report_label_font_color' => '#223344',
        'report_value_font_color' => '#334455',
        'report_header_title_font_family' => 'Futura',
        'report_header_title_font_variant' => 'italic',
        'report_header_title_font_weight' => '700',
        'report_header_title_font_size' => 16,
        'report_header_tagline_font_family' => 'Mistral',
        'report_header_tagline_font_variant' => 'regular',
        'report_header_tagline_font_weight' => '500',
        'report_header_tagline_font_size' => 10,
        'report_label_font_family' => 'Futura',
        'report_label_font_variant' => 'regular',
        'report_label_font_weight' => '400',
        'report_label_font_size' => 9,
        'report_value_font_family' => 'Futura',
        'report_value_font_variant' => 'regular',
        'report_value_font_weight' => '600',
        'report_value_font_size' => 11,
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
    expect($branding['reportHeader']['title'])->toBe('Loan Request Summary');
    expect($branding['reportHeader']['tagline'])->toBe('Confidential');
    expect($branding['reportHeader']['showLogo'])->toBeFalse();
    expect($branding['reportHeader']['showCompanyName'])->toBeTrue();
    expect($branding['reportHeader']['alignment'])->toBe('right');
    expect($branding['reportTypography']['headerTitle']['family'])
        ->toBe('Futura');
    expect($branding['reportTypography']['headerTitle']['variant'])
        ->toBe('italic');
    expect($branding['reportTypography']['headerTitle']['weight'])
        ->toBe(700);
    expect($branding['reportTypography']['headerTitle']['size'])
        ->toBe(16);
    expect($branding['reportTypography']['headerTitle']['color'])
        ->toBe('#112233');
    expect($branding['reportTypography']['headerTagline']['color'])
        ->toBe('#221133');
    expect($branding['reportTypography']['label']['family'])->toBe('Futura');
    expect($branding['reportTypography']['label']['color'])->toBe('#223344');
    expect($branding['reportTypography']['value']['family'])->toBe('Futura');
    expect($branding['reportTypography']['value']['color'])->toBe('#334455');
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
