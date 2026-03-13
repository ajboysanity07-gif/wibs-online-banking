<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view member loans page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000701',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.loans', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-loans')
            ->has('member')
            ->has('summary')
            ->has('loans')
            ->where('member.user_id', $member->user_id));
});

test('admin can view member savings page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000702',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.members.savings', $member->user_id));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/member-savings')
            ->has('member')
            ->has('summary')
            ->has('savings')
            ->where('member.user_id', $member->user_id));
});

test('non-admin users cannot access member account pages', function () {
    $user = User::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.members.loans', $member->user_id))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.members.savings', $member->user_id))
        ->assertForbidden();
});
