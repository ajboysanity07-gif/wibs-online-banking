<?php

use App\Models\AppUser as User;
use App\Models\UserProfile;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('approved users can visit the dashboard', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('pending users are redirected to pending approval', function () {
    $user = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $user->user_id,
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('pending-approval'));
});
