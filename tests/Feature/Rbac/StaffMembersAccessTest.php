<?php

use App\Models\AppUser;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('loan processors can access the staff members page and list', function (): void {
    $processor = createStaffWorkflowUser([Role::LOAN_PROCESSOR]);
    AppUser::factory()->create(['acctno' => '900001']);

    $this
        ->actingAs($processor)
        ->get(route('staff.members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('staff/members'));

    $this
        ->actingAs($processor)
        ->getJson(route('spa.staff.members.index'))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

test('loan managers can access the staff members page and list', function (): void {
    $manager = createStaffWorkflowUser([Role::LOAN_MANAGER]);

    $this
        ->actingAs($manager)
        ->get(route('staff.members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('staff/members'));

    $this
        ->actingAs($manager)
        ->getJson(route('spa.staff.members.index'))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

test('a staff user without member view permission is forbidden', function (): void {
    $processor = createStaffWorkflowUser([Role::LOAN_PROCESSOR]);
    $processor->roles()->first()->permissions()->detach(
        \App\Models\Permission::query()->where('name', \App\Models\Permission::MEMBER_VIEW)->value('id'),
    );
    $processor->load('roles.permissions');

    $this
        ->actingAs($processor)
        ->get(route('staff.members.index'))
        ->assertForbidden();

    $this
        ->actingAs($processor)
        ->getJson(route('spa.staff.members.index'))
        ->assertForbidden();
});

test('unauthenticated users cannot access the staff members page', function (): void {
    $this->get(route('staff.members.index'))
        ->assertRedirect(route('login'));
});

function createStaffWorkflowUser(array $roles, ?string $acctno = null): AppUser
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
