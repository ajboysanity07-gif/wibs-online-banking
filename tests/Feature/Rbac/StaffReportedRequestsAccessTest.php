<?php

use App\Models\AppUser;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('loan processors can access the reported requests page', function (): void {
    $processor = createReportedRequestsStaffUser([Role::LOAN_PROCESSOR]);

    $this
        ->actingAs($processor)
        ->get(route('staff.reported-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('staff/reported-requests'));

    $this
        ->actingAs($processor)
        ->getJson(route('spa.staff.loan-requests.index', ['reported' => 1]))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

test('loan managers can access the reported requests page', function (): void {
    $manager = createReportedRequestsStaffUser([Role::LOAN_MANAGER]);

    $this
        ->actingAs($manager)
        ->get(route('staff.reported-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('staff/reported-requests'));
});

test('members cannot access the reported requests page', function (): void {
    $member = createReportedRequestsStaffUser([Role::MEMBER], acctno: '900010');

    $this
        ->actingAs($member)
        ->get(route('staff.reported-requests.index'))
        ->assertForbidden();
});

test('unauthenticated users cannot access the reported requests page', function (): void {
    $this->get(route('staff.reported-requests.index'))
        ->assertRedirect(route('login'));
});

function createReportedRequestsStaffUser(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    $twoFactorRoles = [Role::SUPERADMIN, Role::LOAN_MANAGER];
    if (! empty(array_intersect($roles, $twoFactorRoles))) {
        $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();
    }

    return $user->load('roles.permissions');
}
