<?php

use App\Models\AppUser as User;
use App\Models\PasswordRecoveryOtp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('forgot password recovery page renders', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/forgot-password')
        ->where('recovery.step', 'lookup')
        ->where('recovery.options', [])
        ->where('recovery.phone', null)
    );
});

test('account lookup uses generic messaging for matched and unmatched accounts', function () {
    $user = User::factory()->create([
        'username' => 'recovery.member',
        'acctno' => '000321',
    ]);

    $matchedResponse = $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->username,
    ]);

    $matchedResponse
        ->assertOk()
        ->assertJsonPath(
            'message',
            'If the details match our records, choose a recovery option below.',
        );

    $unmatchedResponse = $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => 'unknown-account',
    ]);

    $unmatchedResponse
        ->assertOk()
        ->assertJsonPath(
            'message',
            'If the details match our records, choose a recovery option below.',
        )
        ->assertJsonPath('recovery.step', 'lookup')
        ->assertJsonPath('recovery.options', []);
});

test('masked recovery options are returned without leaking full contact details', function () {
    $user = User::factory()->create([
        'email' => 'johndoe@gmail.com',
        'phoneno' => '09171232943',
    ]);

    $response = $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->email,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('recovery.step', 'options')
        ->assertJsonPath('recovery.options.0.type', 'email')
        ->assertJsonPath('recovery.options.0.masked_value', 'j******@gmail.com')
        ->assertJsonPath('recovery.options.1.type', 'phone')
        ->assertJsonPath('recovery.options.1.masked_value', '*******2943');

    expect($response->getContent())->not->toContain($user->email);
    expect($response->getContent())->not->toContain($user->phoneno);
});

test('email recovery path still sends the Fortify reset notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'username' => 'member.email',
    ]);

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->username,
    ])->assertOk();

    $this->postJson('/spa/password-recovery/email')
        ->assertOk()
        ->assertJsonPath(
            'message',
            'If the details match our records, we sent a password reset link.',
        );

    Notification::assertSentTo($user, ResetPassword::class);
});

test('phone otp can be requested', function () {
    Http::fake([
        'https://api.semaphore.co/api/v4/messages' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');
    config()->set('services.semaphore.base_url', 'https://api.semaphore.co/api/v4/messages');
    config()->set('services.semaphore.sender_name', 'WIBS');

    $user = User::factory()->create([
        'phoneno' => '09175551234',
    ]);

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->email,
    ])->assertOk();

    $response = $this->postJson('/spa/password-recovery/phone/send');

    $response
        ->assertOk()
        ->assertJsonPath('recovery.step', 'phone_verify')
        ->assertJsonPath('recovery.phone.masked_value', '*******1234');

    Http::assertSent(function ($request) use ($user): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.semaphore.co/api/v4/messages'
            && ($payload['number'] ?? null) === $user->phoneno;
    });

    expect(PasswordRecoveryOtp::query()->count())->toBe(1);
});

test('invalid otp is rejected', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');

    $user = User::factory()->create();

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->username,
    ])->assertOk();

    $this->postJson('/spa/password-recovery/phone/send')->assertOk();

    $response = $this->postJson('/spa/password-recovery/phone/verify', [
        'code' => '000000',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.code.0',
            'The verification code is invalid or has expired.',
        );
});

test('expired otp is rejected', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');

    $user = User::factory()->create();

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->username,
    ])->assertOk();

    $this->postJson('/spa/password-recovery/phone/send')->assertOk();

    $otp = PasswordRecoveryOtp::query()->latest('id')->firstOrFail();
    $otp->forceFill([
        'code_hash' => Hash::make('123456'),
        'expires_at' => now()->subMinute(),
    ])->save();

    $response = $this->postJson('/spa/password-recovery/phone/verify', [
        'code' => '123456',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.code.0',
            'The verification code is invalid or has expired.',
        );
});

test('used otp is rejected', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');

    $user = User::factory()->create();

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->email,
    ])->assertOk();

    $this->postJson('/spa/password-recovery/phone/send')->assertOk();

    $otp = PasswordRecoveryOtp::query()->latest('id')->firstOrFail();
    $otp->forceFill([
        'code_hash' => Hash::make('123456'),
        'used_at' => now(),
    ])->save();

    $response = $this->postJson('/spa/password-recovery/phone/verify', [
        'code' => '123456',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.code.0',
            'The verification code is invalid or has expired.',
        );
});

test('valid otp allows password reset', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');

    $user = User::factory()->create();

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $user->acctno,
    ])->assertOk();

    $this->postJson('/spa/password-recovery/phone/send')->assertOk();

    $otp = PasswordRecoveryOtp::query()->latest('id')->firstOrFail();
    $otp->forceFill([
        'code_hash' => Hash::make('123456'),
    ])->save();

    $this->postJson('/spa/password-recovery/phone/verify', [
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJsonPath('recovery.step', 'phone_reset');

    $response = $this->postJson('/spa/password-recovery/phone/reset', [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('redirect_to', route('login', absolute: false));

    expect(Hash::check('new-password', (string) $user->fresh()->password))->toBeTrue();
});

test('password recovery routes are rate limited', function () {
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');

    $lookupUser = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/spa/password-recovery/lookup', [
            'identifier' => $lookupUser->email,
        ])->assertOk();
    }

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $lookupUser->email,
    ])->assertTooManyRequests();

    $this->flushSession();

    $sendUser = User::factory()->create();

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $sendUser->username,
    ])->assertOk();

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/spa/password-recovery/phone/send')->assertOk();
    }

    $this->postJson('/spa/password-recovery/phone/send')
        ->assertTooManyRequests();

    $this->flushSession();

    $verifyUser = User::factory()->create();

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $verifyUser->username,
    ])->assertOk();

    $this->postJson('/spa/password-recovery/phone/send')->assertOk();

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/spa/password-recovery/phone/verify', [
            'code' => '000000',
        ])->assertUnprocessable();
    }

    $this->postJson('/spa/password-recovery/phone/verify', [
        'code' => '000000',
    ])->assertTooManyRequests();

    $this->flushSession();

    $resetUser = User::factory()->create();

    $this->postJson('/spa/password-recovery/lookup', [
        'identifier' => $resetUser->acctno,
    ])->assertOk();

    $this->postJson('/spa/password-recovery/phone/send')->assertOk();

    $otp = PasswordRecoveryOtp::query()->latest('id')->firstOrFail();
    $otp->forceFill([
        'code_hash' => Hash::make('123456'),
    ])->save();

    $this->postJson('/spa/password-recovery/phone/verify', [
        'code' => '123456',
    ])->assertOk();

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/spa/password-recovery/phone/reset', [
            'password' => 'new-password',
            'password_confirmation' => 'mismatch',
        ])->assertUnprocessable();
    }

    $this->postJson('/spa/password-recovery/phone/reset', [
        'password' => 'new-password',
        'password_confirmation' => 'mismatch',
    ])->assertTooManyRequests();
});
