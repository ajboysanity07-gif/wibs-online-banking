<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;

test('pending member review routes are unavailable', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $this->actingAs($admin)
        ->get('/admin/users/pending')
        ->assertNotFound();

    $this->actingAs($admin)
        ->patch('/admin/users/123/approve')
        ->assertNotFound();
});
