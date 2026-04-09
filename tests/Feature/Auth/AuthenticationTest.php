<?php

use App\Models\AppUser as User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('spa login authenticates users without two factor', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/spa/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJson(['ok' => true])
        ->assertJsonStructure(['redirect_to']);
    $this->assertAuthenticatedAs($user);
});

test('spa login requires two factor for confirmed users', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->postJson('/spa/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'requires_two_factor' => true,
            'redirect_to' => route('two-factor.login', absolute: false),
        ])
        ->assertSessionHas('login.id', $user->id)
        ->assertSessionHas('login.remember', true);
    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
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

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('spa login fails with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/spa/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();
    $this->assertGuest();
});

test('spa logout rotates the csrf token', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $initialResponse = $this->get('/');
    $initialToken = collect($initialResponse->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN')
        ?->getValue();

    $response = $this->postJson('/spa/auth/logout');
    $response->assertOk();

    $nextToken = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN')
        ?->getValue();

    expect($initialToken)->not->toBeNull();
    expect($nextToken)->not->toBeNull();
    expect($nextToken)->not->toBe($initialToken);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
