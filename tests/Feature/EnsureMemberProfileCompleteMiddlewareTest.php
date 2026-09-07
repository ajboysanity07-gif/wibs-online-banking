<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('a JSON request from a member with an incomplete profile gets a JSON error instead of an HTML redirect', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => '009900',
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->create([
        'user_id' => $member->user_id,
        'release_method' => null,
    ]);

    $member = $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);

    expect($member->memberApplicationProfileIsComplete())->toBeFalse();

    $this->actingAs($member)
        ->getJson('/client/co-makers/999999')
        ->assertStatus(409)
        ->assertJsonPath('ok', false);
});
