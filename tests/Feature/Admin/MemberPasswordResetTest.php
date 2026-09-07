<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;

test('superadmin can reset a member password, forcing a change without leaking the secret', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    $originalHash = $member->password;

    $response = $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$member->user_id}/reset-password", [
            'reason' => 'Member lost access to email and phone.',
        ])
        ->assertOk();

    $temporaryPassword = $response->json('data.temporary_password');

    expect($temporaryPassword)->toBeString()->not->toBeEmpty();

    $member->refresh();

    expect($member->password)->not->toBe($originalHash);
    expect(password_verify($temporaryPassword, $member->password))->toBeTrue();
    expect($member->must_change_password)->toBeTrue();
    expect(json_encode($response->json()))->not->toContain($originalHash);
});

test('member password reset requires a reason and blocks resetting your own password', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$member->user_id}/reset-password", [
            'reason' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    // The superadmin actor also carries an admin profile, so this is
    // rejected by the same admin-account guard exercised above.
    $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$superadmin->user_id}/reset-password", [
            'reason' => 'Trying to reset my own password.',
        ])
        ->assertForbidden();
});

test('member password reset is blocked for admin accounts and non-superadmin actors', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$admin->user_id}/reset-password", [
            'reason' => 'Attempting to reset an admin account.',
        ])
        ->assertForbidden();

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/reset-password", [
            'reason' => 'Non-superadmin attempt.',
        ])
        ->assertForbidden();

    $client = User::factory()->create();

    $this
        ->actingAs($client)
        ->patchJson("/spa/admin/members/{$member->user_id}/reset-password", [
            'reason' => 'Member attempt.',
        ])
        ->assertForbidden();
});

test('a member with a pending forced password change is redirected until they set a new password', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $response = $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$member->user_id}/reset-password", [
            'reason' => 'Member requested a reset.',
        ])
        ->assertOk();

    $temporaryPassword = $response->json('data.temporary_password');

    $member->refresh();

    $this
        ->actingAs($member)
        ->get(route('dashboard'))
        ->assertRedirect(route('user-password.edit'));

    $this
        ->actingAs($member)
        ->put(route('user-password.update'), [
            'current_password' => $temporaryPassword,
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ])
        ->assertRedirect();

    $member->refresh();

    expect($member->must_change_password)->toBeFalse();
});
