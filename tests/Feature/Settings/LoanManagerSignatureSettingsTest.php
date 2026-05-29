<?php

use App\Models\AdminProfile;
use App\Models\AdminSignature;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can save a loan manager signature from settings', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Loan Manager Admin',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('profile.loan-manager-signature.update'), [
            'signature_data' => testPngSignatureDataUrl(),
        ])
        ->assertRedirect(route('profile.edit'));

    $signature = AdminSignature::query()
        ->where('user_id', $admin->user_id)
        ->where('is_active', true)
        ->sole();

    Storage::disk('public')->assertExists($signature->signature_path);
    expect($signature->created_ip)->toBe('127.0.0.1');
    expect($signature->created_user_agent)->not->toBeNull();

    $this
        ->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where(
                'loanManagerSignature.previewUrl',
                Storage::disk('public')->url($signature->signature_path),
            )
            ->where(
                'loanManagerSignature.updatedAt',
                $signature->updated_at?->toDateTimeString(),
            ));
});

test('saved loan manager signature png is cleaned for transparent document overlays', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Loan Manager Admin',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('profile.loan-manager-signature.update'), [
            'signature_data' => testOpaqueWhiteSignatureDataUrl(),
        ])
        ->assertRedirect(route('profile.edit'));

    $signature = AdminSignature::query()
        ->where('user_id', $admin->user_id)
        ->where('is_active', true)
        ->sole();

    $storedBinary = Storage::disk('public')->get($signature->signature_path);
    $storedDimensions = pngDimensions($storedBinary);

    expect(pngHasTransparency($storedBinary))->toBeTrue();
    expect($storedDimensions['width'])->toBeLessThan(160);
    expect($storedDimensions['height'])->toBeLessThan(60);
});

test('empty loan manager signature cannot be saved', function () {
    $admin = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $this
        ->actingAs($admin)
        ->from(route('profile.edit'))
        ->post(route('profile.loan-manager-signature.update'), [
            'signature_data' => '',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors([
            'signature_data' => 'Please draw your loan manager signature before saving.',
        ]);

    expect(AdminSignature::query()->count())->toBe(0);
});

test('invalid loan manager signature data is rejected', function () {
    $admin = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $this
        ->actingAs($admin)
        ->from(route('profile.edit'))
        ->post(route('profile.loan-manager-signature.update'), [
            'signature_data' => 'data:image/png;base64,not-a-real-signature',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors([
            'signature_data' => 'Please provide a valid PNG signature.',
        ]);

    expect(AdminSignature::query()->count())->toBe(0);
});

test('non admin users cannot save a loan manager signature', function () {
    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $this
        ->actingAs($member)
        ->post(route('profile.loan-manager-signature.update'), [
            'signature_data' => testPngSignatureDataUrl(),
        ])
        ->assertForbidden();

    expect(AdminSignature::query()->count())->toBe(0);
});
