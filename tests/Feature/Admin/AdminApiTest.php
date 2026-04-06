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
            $table->string('email_address')->nullable();
            $table->date('datemem')->nullable();
        });
    }
});

test('admin can list members', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000701',
        'username' => 'jrivera',
        'email' => 'jrivera@example.test',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $member->acctno,
        'fname' => 'Jane',
        'lname' => 'Rivera',
        'bname' => 'Rivera, Jane',
        'email_address' => 'jane.rivera@example.test',
    ]);

    DB::table('wmaster')->insert([
        'acctno' => '000702',
        'fname' => 'Ana',
        'lname' => 'Cruz',
        'bname' => 'Cruz, Ana',
        'email_address' => 'ana.cruz@example.test',
    ]);

    $response = $this->actingAs($admin)->getJson('/spa/admin/members');

    $response->assertOk();

    $items = collect($response->json('data.items'));
    $memberIds = $items->pluck('user_id');
    $acctnos = $items->pluck('acctno');
    $registeredMember = $items->firstWhere('acctno', '000701');
    $unregisteredMember = $items->firstWhere('acctno', '000702');

    expect($memberIds)->toContain($member->user_id);
    expect($registeredMember['registration_status'])->toBe('registered');
    expect($unregisteredMember['registration_status'])->toBe('unregistered');
    expect($unregisteredMember['user_id'])->toBeNull();
});

test('member summary portal status defaults to active for registered members', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $activeMember = User::factory()->create(['acctno' => '000710']);
    UserProfile::factory()->approved()->create([
        'user_id' => $activeMember->user_id,
    ]);

    $missingStatusMember = User::factory()->create(['acctno' => '000711']);
    UserProfile::factory()->create([
        'user_id' => $missingStatusMember->user_id,
        'status' => '',
    ]);

    $suspendedMember = User::factory()->create(['acctno' => '000712']);
    UserProfile::factory()->create([
        'user_id' => $suspendedMember->user_id,
        'status' => 'suspended',
    ]);

    DB::table('wmaster')->insert([
        [
            'acctno' => '000710',
            'fname' => 'Active',
            'lname' => 'Member',
            'bname' => 'Member, Active',
        ],
        [
            'acctno' => '000711',
            'fname' => 'Missing',
            'lname' => 'Status',
            'bname' => 'Status, Missing',
        ],
        [
            'acctno' => '000712',
            'fname' => 'Suspended',
            'lname' => 'Member',
            'bname' => 'Member, Suspended',
        ],
        [
            'acctno' => '000713',
            'fname' => 'Unregistered',
            'lname' => 'Member',
            'bname' => 'Member, Unregistered',
        ],
    ]);

    $response = $this->actingAs($admin)->getJson('/spa/admin/members');

    $response->assertSuccessful();

    $items = collect($response->json('data.items'));

    expect($items->firstWhere('acctno', '000710')['portal_status'])->toBe('active');
    expect($items->firstWhere('acctno', '000711')['portal_status'])->toBe('active');
    expect($items->firstWhere('acctno', '000712')['portal_status'])->toBe('suspended');
    expect($items->firstWhere('acctno', '000713')['portal_status'])->toBeNull();
});

test('member detail portal status defaults to active for registered members', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $activeMember = User::factory()->create(['acctno' => '000720']);
    UserProfile::factory()->approved()->create([
        'user_id' => $activeMember->user_id,
    ]);

    $missingStatusMember = User::factory()->create(['acctno' => '000721']);
    UserProfile::factory()->create([
        'user_id' => $missingStatusMember->user_id,
        'status' => '',
    ]);

    $suspendedMember = User::factory()->create(['acctno' => '000722']);
    UserProfile::factory()->create([
        'user_id' => $suspendedMember->user_id,
        'status' => 'suspended',
    ]);

    DB::table('wmaster')->insert([
        [
            'acctno' => '000723',
            'fname' => 'Unregistered',
            'lname' => 'Member',
            'bname' => 'Member, Unregistered',
        ],
    ]);

    $activeResponse = $this->actingAs($admin)->getJson("/spa/admin/members/{$activeMember->user_id}");
    $missingResponse = $this->actingAs($admin)->getJson("/spa/admin/members/{$missingStatusMember->user_id}");
    $suspendedResponse = $this->actingAs($admin)->getJson("/spa/admin/members/{$suspendedMember->user_id}");
    $unregisteredResponse = $this->actingAs($admin)->getJson('/spa/admin/members/acct-000723');

    $activeResponse->assertSuccessful();
    $missingResponse->assertSuccessful();
    $suspendedResponse->assertSuccessful();
    $unregisteredResponse->assertSuccessful();

    expect($activeResponse->json('data.member.portal_status'))->toBe('active');
    expect($missingResponse->json('data.member.portal_status'))->toBe('active');
    expect($suspendedResponse->json('data.member.portal_status'))->toBe('suspended');
    expect($unregisteredResponse->json('data.member.portal_status'))->toBeNull();
});

test('admin can suspend and reactivate members', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $suspendResponse = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/suspend");

    $suspendResponse->assertOk();
    expect($member->refresh()->userProfile?->status)->toBe('suspended');

    $reactivateResponse = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/members/{$member->user_id}/reactivate");

    $reactivateResponse->assertOk();
    expect($member->refresh()->userProfile?->status)->toBe('active');
});

test('non-admin users cannot change member status', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson("/spa/admin/members/{$member->user_id}/suspend");

    $response->assertForbidden();
});

test('member directory search and pagination use wmaster records', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    foreach (range(1, 6) as $index) {
        DB::table('wmaster')->insert([
            'acctno' => sprintf('0008%02d', $index),
            'fname' => 'Member',
            'lname' => "Test{$index}",
            'bname' => "Test{$index}, Member",
        ]);
    }

    DB::table('wmaster')->insert([
        'acctno' => '000899',
        'fname' => 'Maria',
        'lname' => 'Rivera',
        'bname' => 'Rivera, Maria',
    ]);

    $paginated = $this->actingAs($admin)->getJson(
        '/spa/admin/members?perPage=5&page=1',
    );

    $paginated->assertOk();
    expect($paginated->json('data.items'))->toHaveCount(5);
    expect($paginated->json('data.meta.total'))->toBe(7);
    expect($paginated->json('data.meta.lastPage'))->toBe(2);

    $search = $this->actingAs($admin)->getJson(
        '/spa/admin/members?search=Rivera',
    );

    $search->assertOk();
    expect($search->json('data.items'))->toHaveCount(1);
    expect($search->json('data.items.0.acctno'))->toBe('000899');
});
