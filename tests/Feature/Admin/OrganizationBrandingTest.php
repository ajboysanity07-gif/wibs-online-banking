<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\OrganizationSetting;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view organization branding settings page', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.settings.organization'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organization-settings')
            ->where('branding.logoMarkUrl', asset('mrdinc-logo-mark.png'))
            ->where('branding.logoFullUrl', asset('mrdinc-logo.png')));
});

test('non-admin users cannot view organization branding settings page', function () {
    $user = AppUser::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.organization'))
        ->assertForbidden();
});

test('admin can update organization branding logo and name', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->create([
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

test('admin can upload logo mark and full overrides', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->create([
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
    AdminProfile::factory()->create([
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
    AdminProfile::factory()->create([
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

test('admin can select the built-in full logo preset', function () {
    $admin = AppUser::factory()->create();
    AdminProfile::factory()->create([
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

test('admin can reset portal icon to the default', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->create([
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

test('admin can reset logo mark and full to defaults', function () {
    Storage::fake('public');

    $admin = AppUser::factory()->create();
    AdminProfile::factory()->create([
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
