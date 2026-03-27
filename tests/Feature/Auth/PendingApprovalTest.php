<?php

use App\Models\AppUser as User;
use App\Models\UserProfile;

test('account unavailable page is displayed for suspended users', function () {
    $user = User::factory()->create();
    UserProfile::factory()->create([
        'user_id' => $user->user_id,
        'status' => 'suspended',
    ]);

    $response = $this->actingAs($user)->get(route('pending-approval'));

    $response->assertOk();
});
