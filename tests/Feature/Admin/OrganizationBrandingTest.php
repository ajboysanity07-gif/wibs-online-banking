<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\OrganizationSetting;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin can view organization branding settings page', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.settings.organization'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organization-settings')
            ->where('branding.logoPreset', OrganizationSettingsService::LOGO_PRESET_MARK)
            ->where('branding.logoMarkUrl', asset('mrdinc-logo-mark.png'))
            ->where('branding.logoFullUrl', asset('mrdinc-logo.png')));
});

test('admin users cannot view organization branding settings page', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.settings.organization'))
        ->assertForbidden();
});

test('non-admin users cannot view organization branding settings page', function () {
    $user = AppUser::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.organization'))
        ->assertForbidden();
});

test('admin users cannot update organization branding settings', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.settings.organization.update'), [
            'company_name' => 'Acme Cooperative',
        ])
        ->assertForbidden();

    expect(OrganizationSetting::query()->count())->toBe(0);
});

test('superadmin can update organization branding logo and name', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
            'portal_label' => 'Members Hub',
            'logo_preset' => OrganizationSettingsService::LOGO_PRESET_MARK,
            'favicon' => UploadedFile::fake()->image('favicon.png'),
            'support_contact_name' => 'Support Team',
            'support_email' => 'support@acme.test',
            'support_phone' => '+15551231234',
            'brand_primary_color' => '#112233',
            'brand_accent_color' => '#445566',
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting = OrganizationSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting->company_name)->toBe('Acme Cooperative');
    expect($setting->portal_label)->toBe('Members Hub');
    expect($setting->logo_preset)
        ->toBe(OrganizationSettingsService::LOGO_PRESET_MARK);
    expect($setting->favicon_path)->not->toBeNull();
    expect($setting->support_contact_name)->toBe('Support Team');
    expect($setting->support_email)->toBe('support@acme.test');
    expect($setting->support_phone)->toBe('+15551231234');
    expect($setting->brand_primary_color)->toBe('#112233');
    expect($setting->brand_accent_color)->toBe('#445566');

    Storage::disk('public')->assertExists($setting->favicon_path);
});

test('superadmin can update loan sms templates', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
            'loan_sms_approved_template' => 'Approved {loan_reference}.',
            'loan_sms_declined_template' => 'Declined {loan_reference}.',
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting = OrganizationSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting->loan_sms_approved_template)
        ->toBe('Approved {loan_reference}.');
    expect($setting->loan_sms_declined_template)
        ->toBe('Declined {loan_reference}.');
});

test('superadmin can upload logo mark and full overrides', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
            'logo_mark' => UploadedFile::fake()->image('logo-mark.png'),
            'logo_full' => UploadedFile::fake()->image('logo-full.png'),
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting = OrganizationSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting->logo_mark_path)->not->toBeNull();
    expect($setting->logo_full_path)->not->toBeNull();

    Storage::disk('public')->assertExists($setting->logo_mark_path);
    Storage::disk('public')->assertExists($setting->logo_full_path);
});

test('brand colors are normalized to 6-digit hex values', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
            'brand_primary_color' => 'abc',
            'brand_accent_color' => 'A1B2C3',
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting = OrganizationSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting->brand_primary_color)->toBe('#aabbcc');
    expect($setting->brand_accent_color)->toBe('#a1b2c3');
});

test('brand color validation rejects invalid hex values', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
            'brand_primary_color' => '#12',
            'brand_accent_color' => 'not-a-color',
        ],
    );

    $response->assertSessionHasErrors([
        'brand_primary_color',
        'brand_accent_color',
    ]);

    expect(OrganizationSetting::query()->count())->toBe(0);
});

test('superadmin can select the built-in full logo preset', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
            'logo_preset' => OrganizationSettingsService::LOGO_PRESET_FULL,
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting = OrganizationSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting->logo_preset)
        ->toBe(OrganizationSettingsService::LOGO_PRESET_FULL);
});

test('superadmin can update report header typography settings', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => 'Acme Cooperative',
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
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting = OrganizationSetting::query()->first();

    expect($setting)->not->toBeNull();
    expect($setting->report_header_title)->toBe('Loan Request Summary');
    expect($setting->report_header_tagline)->toBe('Confidential');
    expect((bool) $setting->report_header_show_logo)->toBeFalse();
    expect((bool) $setting->report_header_show_company_name)->toBeTrue();
    expect($setting->report_header_alignment)->toBe('right');
    expect($setting->report_header_font_color)->toBe('#112233');
    expect($setting->report_header_tagline_color)->toBe('#221133');
    expect($setting->report_label_font_color)->toBe('#223344');
    expect($setting->report_value_font_color)->toBe('#334455');
    expect($setting->report_header_title_font_family)->toBe('Futura');
    expect($setting->report_header_title_font_variant)->toBe('italic');
    expect($setting->report_header_title_font_weight)->toBe('700');
    expect($setting->report_header_title_font_size)->toBe(16);
});

test('superadmin can reset portal icon to the default', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $faviconPath = 'branding/favicons/custom-icon.png';
    Storage::disk('public')->put($faviconPath, 'icon');

    $setting = OrganizationSetting::factory()->create([
        'favicon_path' => $faviconPath,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => $setting->company_name,
            'favicon_reset' => true,
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting->refresh();

    expect($setting->favicon_path)->toBeNull();
    Storage::disk('public')->assertMissing($faviconPath);
});

test('superadmin can reset logo mark and full to defaults', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $admin->user_id,
    ]);

    $markPath = 'branding/logos/mark/custom-mark.png';
    $fullPath = 'branding/logos/full/custom-full.png';

    Storage::disk('public')->put($markPath, 'mark');
    Storage::disk('public')->put($fullPath, 'full');

    $setting = OrganizationSetting::factory()->create([
        'logo_mark_path' => $markPath,
        'logo_full_path' => $fullPath,
    ]);

    $response = $this->actingAs($admin)->patch(
        route('admin.settings.organization.update'),
        [
            'company_name' => $setting->company_name,
            'logo_mark_reset' => true,
            'logo_full_reset' => true,
        ],
    );

    $response->assertRedirect(route('admin.settings.organization'));

    $setting->refresh();

    expect($setting->logo_mark_path)->toBeNull();
    expect($setting->logo_full_path)->toBeNull();

    Storage::disk('public')->assertMissing($markPath);
    Storage::disk('public')->assertMissing($fullPath);
});
