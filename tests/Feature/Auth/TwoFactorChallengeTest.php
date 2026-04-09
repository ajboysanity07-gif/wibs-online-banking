<?php

use App\Models\AppUser as User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

test('two factor challenge redirects to login when not authenticated', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/two-factor-challenge')
        );
});

test('two factor challenge authenticates after spa login', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $secret = 'JBSWY3DPEHPK3PXP';
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/spa/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $response = $this->post(route('two-factor.login'), [
        'recovery_code' => 'recovery-code-1',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('two factor challenge authenticates with a code after spa login', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $secret = 'JBSWY3DPEHPK3PXP';
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/spa/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $response = $this->post(route('two-factor.login'), [
        'code' => $code,
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});
