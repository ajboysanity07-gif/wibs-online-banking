<?php

use App\Models\AppUser;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register after verification', function () {
    $response = $this->withSession([
        'member_verification' => [
            'acctno' => '000123',
            'verified_at' => now()->getTimestamp(),
        ],
    ])->post(route('register.store'), [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = AppUser::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->acctno)->toBe('000123');
    expect($user->userProfile)->not->toBeNull();
    expect($user->userProfile->status)->toBe('pending');
});

test('registration requires member verification', function () {
    $response = $this->post(route('register.store'), [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('verification');
});
