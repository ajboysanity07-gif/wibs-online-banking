<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
        });
    }
});

test('superadmin can grant admin access to a registered member', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '001001',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '001001',
        'fname' => 'Grace',
        'lname' => 'Member',
        'bname' => 'Member, Grace',
    ]);

    $response = $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$member->user_id}/grant-admin");

    $response->assertOk();

    expect(AdminProfile::query()->where('user_id', $member->user_id)->count())
        ->toBe(1);
    expect(
        AdminProfile::query()
            ->where('user_id', $member->user_id)
            ->where('access_level', AdminProfile::ACCESS_LEVEL_ADMIN)
            ->exists(),
    )->toBeTrue();
    expect($response->json('data.member.admin_access_level'))->toBe('admin');
    expect($response->json('data.member.is_admin'))->toBeTrue();

    $list = $this->actingAs($superadmin)->getJson('/spa/admin/members');

    $list->assertOk();
    expect(collect($list->json('data.items'))->pluck('user_id'))
        ->toContain($member->user_id);
});

test('superadmin can revoke admin access from a member', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '001002',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $member->user_id,
    ]);

    $response = $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$member->user_id}/revoke-admin");

    $response->assertOk();

    expect(AdminProfile::query()->where('user_id', $member->user_id)->exists())
        ->toBeFalse();
    expect($response->json('data.member.admin_access_level'))->toBe('member');
    expect($response->json('data.member.is_admin'))->toBeFalse();
});

test('superadmin cannot grant or revoke their own admin access', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $grant = $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$superadmin->user_id}/grant-admin");

    $grant->assertUnprocessable();

    $revoke = $this
        ->actingAs($superadmin)
        ->patchJson("/spa/admin/members/{$superadmin->user_id}/revoke-admin");

    $revoke->assertUnprocessable();
});

test('only superadmins can manage admin access', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $adminResponse = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/grant-admin");

    $adminResponse->assertForbidden();

    $client = User::factory()->create();

    $clientResponse = $this
        ->actingAs($client)
        ->patchJson("/spa/admin/members/{$member->user_id}/grant-admin");

    $clientResponse->assertForbidden();
});

test('unregistered members cannot be granted admin access', function () {
    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '001003',
        'fname' => 'Una',
        'lname' => 'Registered',
        'bname' => 'Registered, Una',
    ]);

    $response = $this
        ->actingAs($superadmin)
        ->patchJson('/spa/admin/members/acct-001003/grant-admin');

    $response->assertUnprocessable();
});
