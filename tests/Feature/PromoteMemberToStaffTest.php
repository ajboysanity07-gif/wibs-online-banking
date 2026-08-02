<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table): void {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
        });
    }
});

/**
 * Create a fully-authorized superadmin actor.
 */
function createSuperadminActor(): AppUser
{
    $actor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);

    $actor->roles()->sync(
        Role::query()->where('name', Role::SUPERADMIN)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $actor->user_id]);

    StaffAccessControl::query()->create([
        'user_id' => $actor->user_id,
        'status' => StaffAccessControl::STATUS_ACTIVE,
        'suspended_at' => null,
        'suspended_by' => null,
        'suspension_reason' => null,
    ]);

    $actor->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();

    return $actor->fresh(['roles.permissions', 'userProfile', 'staffAccessControl']);
}

/**
 * Create a member with the MEMBER role and an account number.
 */
function createMemberForPromotion(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Promote', 'lname' => 'Test', 'birthday' => '1990-01-01', 'address' => 'Test St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('promoting a valid member by account number grants the role', function (): void {
    $actor = createSuperadminActor();
    $member = createMemberForPromotion('003001');

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => '003001',
            'role' => 'loan_processor',
            'reason' => 'Onboarding new processor.',
        ])
        ->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('data.staff.user_id', $member->user_id);

    $member->refresh();
    expect($member->hasRole(Role::LOAN_PROCESSOR))->toBeTrue();
});

test('member portal access is preserved after promotion', function (): void {
    $actor = createSuperadminActor();
    $member = createMemberForPromotion('003002');

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => '003002',
            'role' => 'loan_manager',
            'reason' => 'Promoting to loan manager.',
        ])
        ->assertCreated();

    $member->refresh()->load('roles');
    expect($member->hasRole(Role::MEMBER))->toBeTrue()
        ->and($member->hasMemberAccess())->toBeTrue();
});

test('invalid account number returns 422', function (): void {
    $actor = createSuperadminActor();

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => 'DOES-NOT-EXIST',
            'role' => 'loan_processor',
            'reason' => 'Test.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_number']);
});

test('already-staff member can receive additional role without duplication', function (): void {
    $actor = createSuperadminActor();
    $member = createMemberForPromotion('003003');

    $promote = fn (string $role) => $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => '003003',
            'role' => $role,
            'reason' => 'Role assignment test.',
        ]);

    $promote('loan_processor')->assertCreated();
    $promote('loan_processor')->assertCreated();

    $member->refresh()->load('roles');
    $processorCount = $member->roles
        ->where('name', Role::LOAN_PROCESSOR)
        ->count();

    expect($processorCount)->toBe(1)
        ->and($member->hasRole(Role::LOAN_PROCESSOR))->toBeTrue();
});

test('user with acctno but without member role returns 422 on promote', function (): void {
    $actor = createSuperadminActor();

    $nonMember = AppUser::factory()->create([
        'acctno' => '003005',
        'email_verified_at' => now(),
    ]);
    // factory auto-assigns MEMBER to any user with an acctno — strip it to isolate the guard
    $memberRole = Role::query()->where('name', Role::MEMBER)->first();
    if ($memberRole) {
        $nonMember->roles()->detach($memberRole->id);
    }

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => '003005',
            'role' => 'loan_processor',
            'reason' => 'Attempting promotion without member role.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_number']);

    $nonMember->refresh()->load('roles');
    expect($nonMember->hasRole(Role::LOAN_PROCESSOR))->toBeFalse();
});

test('promotion creates a UserRoleChange audit entry', function (): void {
    $actor = createSuperadminActor();
    $member = createMemberForPromotion('003006');

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => '003006',
            'role' => 'loan_manager',
            'reason' => 'Audit trail test.',
        ])
        ->assertCreated();

    $auditEntry = \App\Models\UserRoleChange::query()
        ->where('target_user_id', $member->user_id)
        ->latest('id')
        ->first();

    expect($auditEntry)->not->toBeNull()
        ->and($auditEntry->action)->toBe(\App\Models\UserRoleChange::ACTION_STAFF_CREATED)
        ->and($auditEntry->metadata_json['account_type'] ?? null)->toBe('member_promoted');
});

test('superadmin cannot promote themselves via this endpoint', function (): void {
    $actor = AppUser::factory()->create([
        'acctno' => '003007',
        'email_verified_at' => now(),
    ]);

    $actor->roles()->sync(
        Role::query()->whereIn('name', [Role::SUPERADMIN, Role::MEMBER])->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $actor->user_id]);

    StaffAccessControl::query()->create([
        'user_id' => $actor->user_id,
        'status' => StaffAccessControl::STATUS_ACTIVE,
        'suspended_at' => null,
        'suspended_by' => null,
        'suspension_reason' => null,
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '003007'],
        ['fname' => 'Self', 'lname' => 'Promote', 'birthday' => '1990-01-01', 'address' => 'Test St'],
    );

    $actor->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();

    $actor = $actor->fresh(['roles.permissions', 'userProfile', 'staffAccessControl']);

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => '003007',
            'role' => 'loan_processor',
            'reason' => 'Self-promotion attempt.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_number']);
});

/**
 * Create a member with a specific email, name, and account number for search tests.
 */
function createSearchableMember(string $acctno, string $email, string $fname, string $lname): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email' => $email,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => $fname, 'lname' => $lname, 'birthday' => '1990-01-01', 'address' => 'Test St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('searchMembers returns correct results for name query', function (): void {
    $actor = createSuperadminActor();
    createSearchableMember('005001', 'name.srch@example.com', 'Findable', 'Surname');

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => 'Findable']))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonCount(1, 'data.members');
});

test('searchMembers returns correct results for account number query', function (): void {
    $actor = createSuperadminActor();
    createSearchableMember('005002', 'acct.srch@example.com', 'Jane', 'Smith');

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => '005002']))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonCount(1, 'data.members');
});

test('searchMembers returns correct results for email query', function (): void {
    $actor = createSuperadminActor();
    createSearchableMember('005003', 'findable.email@example.com', 'Bob', 'Jones');

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => 'findable.email@example.com']))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonCount(1, 'data.members');
});

test('searchMembers excludes non-registered members', function (): void {
    $actor = createSuperadminActor();

    $nonMember = AppUser::factory()->create([
        'acctno' => '005004',
        'email' => 'nonmember.srch@example.com',
        'email_verified_at' => now(),
    ]);

    $memberRole = Role::query()->where('name', Role::MEMBER)->first();
    if ($memberRole) {
        $nonMember->roles()->detach($memberRole->id);
    }

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '005004'],
        ['fname' => 'Excluded', 'lname' => 'NonMember', 'birthday' => '1990-01-01', 'address' => 'Test St'],
    );

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => 'Excluded']))
        ->assertOk()
        ->assertJsonCount(0, 'data.members');
});

test('searchMembers limits to 10 results', function (): void {
    $actor = createSuperadminActor();

    for ($i = 1; $i <= 12; $i++) {
        createSearchableMember(
            sprintf('006%03d', $i),
            sprintf('bulk%d@example.com', $i),
            'BulkMember',
            sprintf('Surname%d', $i),
        );
    }

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => 'BulkMember']))
        ->assertOk()
        ->assertJsonCount(10, 'data.members');
});

test('already-staff members appear in results with their current roles', function (): void {
    $actor = createSuperadminActor();
    $member = createSearchableMember('005005', 'staffed.member@example.com', 'Staffed', 'Person');

    $member->roles()->attach(
        Role::query()->where('name', Role::LOAN_PROCESSOR)->pluck('id')->all(),
    );

    $response = $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => 'Staffed']))
        ->assertOk()
        ->assertJsonCount(1, 'data.members');

    $roleNames = collect($response->json('data.members.0.roles'))->pluck('name')->all();
    expect($roleNames)->toContain(Role::LOAN_PROCESSOR);
});

test('member lookup returns member details for a valid account number', function (): void {
    $actor = createSuperadminActor();
    createMemberForPromotion('003004');

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.member-lookup', ['account_number' => '003004']))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['data' => ['member' => ['user_id', 'acctno']]]);
});

test('member lookup returns 404 for unknown account number', function (): void {
    $actor = createSuperadminActor();

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.member-lookup', ['account_number' => 'UNKNOWN']))
        ->assertNotFound();
});

test('member lookup requires an account_number', function (): void {
    $actor = createSuperadminActor();

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.member-lookup'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_number']);
});

test('non-superadmin cannot access member lookup or member search', function (): void {
    $member = AppUser::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($member)
        ->getJson(route('spa.superadmin.staff.member-lookup', ['account_number' => '003004']))
        ->assertForbidden();

    $this->actingAs($member)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => 'anything']))
        ->assertForbidden();
});

test('searchMembers with empty query returns registered members up to 25', function (): void {
    $actor = createSuperadminActor();

    for ($i = 1; $i <= 30; $i++) {
        createSearchableMember(
            sprintf('008%03d', $i),
            sprintf('default%d@example.com', $i),
            'Default',
            sprintf('Member%d', $i),
        );
    }

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members'))
        ->assertOk()
        ->assertJsonCount(25, 'data.members');
});

test('searchMembers with empty query excludes accounts without acctno', function (): void {
    $actor = createSuperadminActor();

    createSearchableMember('010001', 'with.acct@example.com', 'Alice', 'Alpha');

    $noAcct = AppUser::factory()->create([
        'acctno' => null,
        'email' => 'no.acct@example.com',
        'email_verified_at' => now(),
    ]);
    $noAcct->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );
    UserProfile::factory()->approved()->create(['user_id' => $noAcct->user_id]);

    $response = $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members'))
        ->assertOk();

    $acctNos = collect($response->json('data.members'))->pluck('acctno')->all();
    expect($acctNos)->toContain('010001')
        ->and($acctNos)->not->toContain(null);
});

test('searchMembers with empty query returns results ordered by name', function (): void {
    $actor = createSuperadminActor();

    createSearchableMember('009001', 'charlie@example.com', 'Charlie', 'Zeta');
    createSearchableMember('009002', 'alice@example.com', 'Alice', 'Alpha');
    createSearchableMember('009003', 'bob@example.com', 'Bob', 'Beta');

    $response = $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members'))
        ->assertOk();

    $acctNos = collect($response->json('data.members'))->pluck('acctno')->all();

    $alphaIdx = array_search('009002', $acctNos, true);
    $betaIdx = array_search('009003', $acctNos, true);
    $zetaIdx = array_search('009001', $acctNos, true);

    expect($alphaIdx)->toBeLessThan($betaIdx)
        ->and($betaIdx)->toBeLessThan($zetaIdx);
});

test('searchMembers active query still capped at 10 results', function (): void {
    $actor = createSuperadminActor();

    for ($i = 1; $i <= 12; $i++) {
        createSearchableMember(
            sprintf('011%03d', $i),
            sprintf('cap%d@example.com', $i),
            'CapTest',
            sprintf('Surname%d', $i),
        );
    }

    $this->actingAs($actor)
        ->getJson(route('spa.superadmin.staff.search-members', ['query' => 'CapTest']))
        ->assertOk()
        ->assertJsonCount(10, 'data.members');
});
