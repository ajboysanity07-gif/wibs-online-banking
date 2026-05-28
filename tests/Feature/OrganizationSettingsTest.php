<?php

use App\Models\OrganizationSetting;
use App\Services\OrganizationSettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

test('branding falls back to defaults when no settings exist', function () {
    config(['app.name' => 'Acme Portal']);

    $service = app(OrganizationSettingsService::class);
    $branding = $service->branding();
    $templates = $service->loanSmsTemplates();

    expect($branding['companyName'])->toBe('Acme');
    expect($branding['businessAddress'])->toBeNull();
    expect($branding['businessAddress1'])->toBeNull();
    expect($branding['businessAddress2'])->toBeNull();
    expect($branding['businessAddress3'])->toBeNull();
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
    expect($branding['reportHeader']['designPath'])->toBeNull();
    expect($branding['reportHeader']['designUrl'])->toBeNull();
    expect($branding['reportHeader']['designData'])->toBeNull();
    expect($branding['reportTypography']['label']['family'])
        ->toBe('DejaVu Sans');
    expect($branding['reportTypography']['label']['color'])->toBeNull();
    expect($branding['reportTypography']['value']['color'])->toBeNull();
    expect($branding['reportTypography']['label']['size'])->toBe(8);
    expect($branding['reportTypography']['value']['size'])->toBe(10);
    expect($branding['communications']['loanSmsTemplates'])->toBe($templates);
});

test('branding uses stored organization settings when available', function () {
    Storage::fake('public');
    Storage::disk('public')->put(
        'branding/report-headers/custom-header.png',
        'header-image',
    );

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Corp',
        'business_address' => 'Head Office, Tagum City, Davao del Norte',
        'business_address1' => 'Head Office',
        'business_address2' => 'Tagum City',
        'business_address3' => 'Davao del Norte',
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_MARK,
        'portal_label' => 'Acme Portal',
        'favicon_path' => 'branding/favicon.ico',
        'brand_primary_color' => '#111111',
        'brand_accent_color' => '#222222',
        'support_email' => 'support@acme.test',
        'support_phone' => '123456789',
        'support_contact_name' => 'Support Team',
        'report_header_design_path' => 'branding/report-headers/custom-header.png',
        'report_label_font_color' => '#223344',
        'report_value_font_color' => '#334455',
        'report_label_font_family' => 'Futura',
        'report_label_font_variant' => 'regular',
        'report_label_font_weight' => '400',
        'report_label_font_size' => 9,
        'report_value_font_family' => 'Futura',
        'report_value_font_variant' => 'regular',
        'report_value_font_weight' => '600',
        'report_value_font_size' => 11,
        'loan_sms_approved_template' => 'Approved {loan_reference}.',
        'loan_sms_declined_template' => 'Declined {loan_reference}.',
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();

    expect($branding['companyName'])->toBe('Acme Corp');
    expect($branding['businessAddress'])
        ->toBe('Head Office, Tagum City, Davao del Norte');
    expect($branding['businessAddress1'])->toBe('Head Office');
    expect($branding['businessAddress2'])->toBe('Tagum City');
    expect($branding['businessAddress3'])->toBe('Davao del Norte');
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
    expect($branding['reportHeader']['designPath'])
        ->toBe('branding/report-headers/custom-header.png');
    expect($branding['reportHeader']['designUrl'])->toBe(
        Storage::disk('public')->url('branding/report-headers/custom-header.png'),
    );
    expect($branding['reportHeader']['designData'])->toBe(sprintf(
        'data:image/png;base64,%s',
        base64_encode('header-image'),
    ));
    expect($branding['reportTypography']['label']['family'])->toBe('Futura');
    expect($branding['reportTypography']['label']['color'])->toBe('#223344');
    expect($branding['reportTypography']['value']['family'])->toBe('Futura');
    expect($branding['reportTypography']['value']['color'])->toBe('#334455');
    expect($branding['communications']['loanSmsTemplates']['approved'])
        ->toBe('Approved {loan_reference}.');
    expect($branding['communications']['loanSmsTemplates']['declined'])
        ->toBe('Declined {loan_reference}.');
});

test('loan sms templates fall back to defaults when blank', function () {
    $service = app(OrganizationSettingsService::class);
    $defaults = $service->defaultAttributes();

    OrganizationSetting::factory()->create([
        'loan_sms_approved_template' => ' ',
        'loan_sms_declined_template' => null,
    ]);

    $templates = $service->loanSmsTemplates();

    expect($templates['approved'])
        ->toBe($defaults['loan_sms_approved_template']);
    expect($templates['declined'])
        ->toBe($defaults['loan_sms_declined_template']);
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

test('branding falls back to safe defaults when lookup throws', function () {
    config(['app.name' => 'Acme Portal']);

    Log::spy();

    $service = \Mockery::mock(OrganizationSettingsService::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $service->shouldReceive('currentSetting')
        ->once()
        ->andThrow(new QueryException(
            'sqlsrv',
            'select top 1 * from [organization_settings]',
            [],
            new \PDOException('SQLSTATE[08001]: Login timeout expired'),
        ));

    $branding = $service->branding();

    expect($branding['companyName'])->toBe('Acme');
    expect($branding['portalLabel'])->toBe('Member Portal');
    expect($branding['appTitle'])->toBe('Member Portal - Acme');
    expect($branding['logoUrl'])->toBe(asset('mrdinc-logo-mark.png'));
    expect($branding['faviconUrl'])->toBe(asset('favicon.ico'));
    expect($branding['general'])->toMatchArray([
        'companyName' => 'Acme',
        'portalLabel' => 'Member Portal',
        'appTitle' => 'Member Portal - Acme',
    ]);
    expect($branding['assets'])->toHaveKeys([
        'logoPreset',
        'logoUrl',
        'logoMarkUrl',
        'logoFullUrl',
        'faviconUrl',
        'brandPrimaryColor',
        'brandAccentColor',
    ]);
    expect($branding['contact'])->toMatchArray([
        'supportEmail' => null,
        'supportPhone' => null,
        'supportContactName' => null,
    ]);
    expect($branding['reports'])->toHaveKeys(['header', 'typography']);
    expect($branding['communications'])->toHaveKey('loanSmsTemplates');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Organization branding lookup failed. Using fallback branding.'
                && ($context['exception'] ?? null) === QueryException::class
                && str_contains(
                    (string) ($context['exception_message'] ?? ''),
                    'Login timeout expired',
                );
        });
});
