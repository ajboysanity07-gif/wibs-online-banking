<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\AppUser;

use function Pest\Laravel\mock;

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
    expect($user->userProfile->status)->toBe('active');
    expect($user->memberApplicationProfile)->toBeNull();
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

test('spa registration keeps password confirmation when creating user', function () {
    $user = AppUser::factory()->create();

    mock(CreateNewUser::class)
        ->shouldReceive('create')
        ->once()
        ->withArgs(function (array $input): bool {
            return array_key_exists('password_confirmation', $input)
                && $input['password'] === $input['password_confirmation'];
        })
        ->andReturn($user);

    $response = $this->withSession([
        'member_verification' => [
            'acctno' => '000555',
            'verified_at' => now()->getTimestamp(),
        ],
    ])->postJson('/spa/auth/register', [
        'username' => 'spauser',
        'email' => 'spauser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertOk();
});

test('spa registration succeeds with matching passwords', function () {
    $response = $this->withSession([
        'member_verification' => [
            'acctno' => '000321',
            'verified_at' => now()->getTimestamp(),
        ],
    ])->postJson('/spa/auth/register', [
        'username' => 'spauser',
        'email' => 'spauser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertOk();
    $response->assertJson(['redirect_to' => '/settings/profile?onboarding=1']);
    $this->assertAuthenticated();

    $user = AppUser::where('email', 'spauser@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->acctno)->toBe('000321');
    expect($user->userProfile)->not->toBeNull();
    expect($user->userProfile->status)->toBe('active');
    expect($user->memberApplicationProfile)->toBeNull();
});

test('spa registration rejects mismatched password confirmation', function () {
    $response = $this->withSession([
        'member_verification' => [
            'acctno' => '000987',
            'verified_at' => now()->getTimestamp(),
        ],
    ])->postJson('/spa/auth/register', [
        'username' => 'spauser',
        'email' => 'spauser@example.com',
        'password' => 'password',
        'password_confirmation' => 'not-matching',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['password']);
    $this->assertGuest();
});
