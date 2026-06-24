<?php

use App\Http\Middleware\EnsureTwoFactorSetup;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\Role;
use App\Models\StaffAccessControl;
use Illuminate\Http\Request;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function makeSuperadminWithout2FA(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'access_level' => \App\Models\AdminProfile::ACCESS_LEVEL_SUPERADMIN,
    ]);
    Role::attachNamedRole($user, Role::SUPERADMIN);
    StaffAccessControl::factory()->create(['user_id' => $user->user_id]);

    return $user->fresh(['roles.permissions']);
}

function makeSuperadminWith2FA(): AppUser
{
    $user = makeSuperadminWithout2FA();
    $user->forceFill([
        'two_factor_secret' => 'fakesecret',
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user->fresh(['roles.permissions']);
}

test('superadmin without confirmed 2FA can access protected routes', function (): void {
    $superadmin = makeSuperadminWithout2FA();

    $this->actingAs($superadmin)
        ->get(route('superadmin.staff.index'))
        ->assertOk();
});

test('superadmin with confirmed 2FA can access protected routes', function (): void {
    $superadmin = makeSuperadminWith2FA();

    $this->actingAs($superadmin)
        ->get(route('superadmin.staff.index'))
        ->assertOk();
});

test('loan manager without confirmed 2FA can access staff routes', function (): void {
    $manager = AppUser::factory()->create(['email_verified_at' => now()]);
    AdminProfile::factory()->create(['user_id' => $manager->user_id]);
    Role::attachNamedRole($manager, Role::LOAN_MANAGER);
    StaffAccessControl::factory()->create(['user_id' => $manager->user_id]);

    $manager = $manager->fresh(['roles.permissions']);

    $this->actingAs($manager)
        ->get(route('staff.loan-requests.index'))
        ->assertOk();
});

test('member role bypasses 2FA requirement', function (): void {
    $member = AppUser::factory()->create([
        'email_verified_at' => now(),
        'acctno' => '123456',
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    $member = $member->fresh(['roles.permissions']);

    $middleware = new EnsureTwoFactorSetup;
    $request = Request::create('/client/dashboard');
    $request->setUserResolver(fn () => $member);

    $called = false;
    $next = function () use (&$called) {
        $called = true;

        return response('ok');
    };

    $middleware->handle($request, $next);
    expect($called)->toBeTrue();
});

test('2FA gate does not lock out users mid-session on exempt paths', function (): void {
    $superadmin = makeSuperadminWithout2FA();

    $middleware = new EnsureTwoFactorSetup;
    $request = Request::create('/settings/security');
    $request->setUserResolver(fn () => $superadmin);

    $called = false;
    $next = function () use (&$called) {
        $called = true;

        return response('ok');
    };

    $middleware->handle($request, $next);
    expect($called)->toBeTrue('settings/security should be exempt from 2FA gate');
});

test('2FA gate allows access to two-factor API endpoints during setup', function (): void {
    $superadmin = makeSuperadminWithout2FA();

    $middleware = new EnsureTwoFactorSetup;

    foreach ([
        'user/two-factor-authentication',
        'user/confirmed-two-factor-authentication',
        'user/two-factor-qr-code',
        'user/two-factor-recovery-codes',
        'logout',
    ] as $path) {
        $request = Request::create('/'.$path);
        $request->setUserResolver(fn () => $superadmin);

        $called = false;
        $next = function () use (&$called) {
            $called = true;

            return response('ok');
        };

        $middleware->handle($request, $next);
        expect($called)->toBeTrue("Path /{$path} should be exempt from 2FA gate.");
    }
});

test('unauthenticated requests pass through the 2FA gate', function (): void {
    $middleware = new EnsureTwoFactorSetup;
    $request = Request::create('/staff/loan-requests');

    $called = false;
    $next = function () use (&$called) {
        $called = true;

        return response('ok');
    };

    $middleware->handle($request, $next);
    expect($called)->toBeTrue();
});
