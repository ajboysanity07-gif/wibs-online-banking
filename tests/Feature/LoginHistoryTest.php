<?php

use App\Listeners\RecordLoginHistory;
use App\Models\AppUser;
use App\Models\LoginHistory;
use App\Models\Role;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('login history is recorded when a user logs in', function (): void {
    $user = AppUser::factory()->create(['email_verified_at' => now()]);

    $request = Request::create('/login', 'POST', [], [], [], [
        'REMOTE_ADDR' => '192.168.1.10',
        'HTTP_USER_AGENT' => 'TestBrowser/1.0',
    ]);

    $listener = new RecordLoginHistory($request);
    $listener->handle(new Login('web', $user, false));

    $history = LoginHistory::query()->where('user_id', $user->user_id)->first();
    expect($history)->not->toBeNull();
    expect($history->ip_address)->toBe('192.168.1.10');
    expect($history->user_agent)->toBe('TestBrowser/1.0');
    expect($history->logged_in_at)->not->toBeNull();
});

test('login history records ip address correctly', function (): void {
    $user = AppUser::factory()->create(['email_verified_at' => now()]);

    $request = Request::create('/login', 'POST', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.5',
    ]);

    (new RecordLoginHistory($request))->handle(new Login('web', $user, false));

    expect(LoginHistory::query()->where('user_id', $user->user_id)->value('ip_address'))->toBe('10.0.0.5');
});

test('multiple logins create multiple history records', function (): void {
    $user = AppUser::factory()->create(['email_verified_at' => now()]);

    $request = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

    (new RecordLoginHistory($request))->handle(new Login('web', $user, false));
    (new RecordLoginHistory($request))->handle(new Login('web', $user, false));

    expect(LoginHistory::query()->where('user_id', $user->user_id)->count())->toBe(2);
});

test('suspicious ip flag fires when ip differs from last 3 logins', function (): void {
    $user = AppUser::factory()->create(['email_verified_at' => now()]);

    $knownIp = '10.10.10.10';

    LoginHistory::factory()->count(3)->create([
        'user_id' => $user->user_id,
        'ip_address' => $knownIp,
        'logged_in_at' => now()->subHour(),
    ]);

    $newIpRequest = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '99.99.99.99']);
    (new RecordLoginHistory($newIpRequest))->handle(new Login('web', $user, false));

    $recentIps = LoginHistory::query()
        ->where('user_id', $user->user_id)
        ->orderByDesc('logged_in_at')
        ->take(4)
        ->pluck('ip_address')
        ->unique()
        ->values();

    expect($recentIps->contains('99.99.99.99'))->toBeTrue();
    expect($recentIps->count())->toBeGreaterThan(1);
});

test('listener is a no-op when login_histories table is absent', function (): void {
    $user = AppUser::factory()->create();

    \Illuminate\Support\Facades\Schema::drop('login_histories');

    $request = Request::create('/login', 'POST');

    expect(fn () => (new RecordLoginHistory($request))->handle(new Login('web', $user, false)))
        ->not->toThrow(Throwable::class);
});
