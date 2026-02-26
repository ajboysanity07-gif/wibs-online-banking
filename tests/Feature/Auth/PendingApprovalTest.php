<?php

use App\Models\AppUser as User;
use App\Models\UserProfile;

test('pending approval page is displayed for authenticated users', function () {
    $user = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $user->user_id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('pending-approval'));

    $response->assertOk();
});
