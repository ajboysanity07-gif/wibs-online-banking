<?php

use App\LoanWorkflowPreflightStage;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Models\UserProfile;
use App\Models\UserRoleChange;
use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (\Illuminate\Database\Schema\Blueprint $table): void {
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

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createLegacyAdminStuckUser(string $acctno): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    AdminProfile::factory()->admin()->create(['user_id' => $user->user_id]);

    // AdminProfileFactory::afterCreating auto-assigns Role::ADMIN; strip it to replicate
    // the real stuck users (Samarah/Boyle) who pre-dated the RBAC backfill.
    $adminRoleId = Role::query()->where('name', Role::ADMIN)->value('id');
    if ($adminRoleId !== null) {
        $user->roles()->detach($adminRoleId);
    }

    return $user->fresh(['adminProfile', 'roles.permissions']);
}

function createLegacyAdminMember(string $acctno): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    AdminProfile::factory()->admin()->create(['user_id' => $user->user_id]);

    $user->roles()->syncWithoutDetaching(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $user->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Legacy', 'lname' => 'Admin', 'birthday' => '1980-01-01', 'address' => 'Test St'],
    );

    return $user->fresh(['adminProfile', 'roles.permissions', 'userProfile']);
}

function createSuperadminActorForBackfill(): AppUser
{
    $actor = AppUser::factory()->create(['email_verified_at' => now()]);

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

// ─── Part 1: BackfillLegacyAdminRoles command ────────────────────────────────

test('admin:backfill-legacy-roles attaches admin RBAC role to stuck legacy admin users', function (): void {
    $stuck = createLegacyAdminStuckUser('BLA-001');
    expect($stuck->hasRole(Role::ADMIN))->toBeFalse();

    $this->artisan('admin:backfill-legacy-roles')->assertSuccessful();

    $stuck->refresh()->load('roles');
    expect($stuck->hasRole(Role::ADMIN))->toBeTrue();
});

test('admin:backfill-legacy-roles is idempotent', function (): void {
    $stuck = createLegacyAdminStuckUser('BLA-002');

    $this->artisan('admin:backfill-legacy-roles')->assertSuccessful();
    $this->artisan('admin:backfill-legacy-roles')->assertSuccessful();

    $stuck->refresh()->load('roles');
    $adminRoleCount = $stuck->roles->where('name', Role::ADMIN)->count();
    expect($adminRoleCount)->toBe(1);
});

test('admin:backfill-legacy-roles preserves the adminProfile', function (): void {
    $stuck = createLegacyAdminStuckUser('BLA-003');

    $this->artisan('admin:backfill-legacy-roles')->assertSuccessful();

    $stuck->refresh()->load('adminProfile');
    expect($stuck->adminProfile)->not->toBeNull()
        ->and($stuck->adminProfile->access_level)->toBe(AdminProfile::ACCESS_LEVEL_ADMIN);
});

test('admin:backfill-legacy-roles skips users who already have a staff RBAC role', function (): void {
    $alreadyAdmin = createLegacyAdminStuckUser('BLA-004');
    Role::attachNamedRole($alreadyAdmin, Role::ADMIN);

    $alreadyProcessor = AppUser::factory()->create(['acctno' => 'BLA-005', 'email_verified_at' => now()]);
    AdminProfile::factory()->admin()->create(['user_id' => $alreadyProcessor->user_id]);
    Role::attachNamedRole($alreadyProcessor, Role::LOAN_PROCESSOR);

    $this->artisan('admin:backfill-legacy-roles')
        ->expectsOutputToContain('Backfilled: 0')
        ->assertSuccessful();
});

// ─── Part 2: promoteMember succeeds for legacy-admin users ───────────────────

test('promoteMember succeeds on a legacy-admin member and attaches the requested role', function (): void {
    $actor = createSuperadminActorForBackfill();
    $target = createLegacyAdminMember('BLA-010');

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => 'BLA-010',
            'role' => Role::LOAN_PROCESSOR,
            'reason' => 'Migrating legacy admin into RBAC.',
        ])
        ->assertCreated();

    $target->refresh()->load('roles');
    expect($target->hasRole(Role::LOAN_PROCESSOR))->toBeTrue();
});

test('promoting a legacy-admin member preserves the adminProfile', function (): void {
    $actor = createSuperadminActorForBackfill();
    $target = createLegacyAdminMember('BLA-011');

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => 'BLA-011',
            'role' => Role::LOAN_MANAGER,
            'reason' => 'Migrating legacy admin into RBAC.',
        ])
        ->assertCreated();

    $target->refresh()->load('adminProfile');
    expect($target->adminProfile)->not->toBeNull()
        ->and($target->adminProfile->access_level)->toBe(AdminProfile::ACCESS_LEVEL_ADMIN);
});

test('promoting a legacy-admin member records a UserRoleChange audit entry with migration flag', function (): void {
    $actor = createSuperadminActorForBackfill();
    $target = createLegacyAdminMember('BLA-012');

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => 'BLA-012',
            'role' => Role::LOAN_PROCESSOR,
            'reason' => 'Legacy admin migration audit test.',
        ])
        ->assertCreated();

    $audit = UserRoleChange::query()
        ->where('target_user_id', $target->user_id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->metadata_json['migrated_from_legacy_admin'] ?? false)->toBeTrue()
        ->and($audit->metadata_json['account_type'] ?? null)->toBe('member_promoted');
});

// ─── Part 3: Samarah-style promotion (legacy-admin → superadmin) ──────────────

test('superadmin can promote a legacy-admin member to superadmin and the adminProfile stays intact', function (): void {
    $actor = createSuperadminActorForBackfill();

    // Simulate Samarah: legacy-admin, already a member with acctno 005431
    $samarah = createLegacyAdminMember('005431');

    $this->actingAs($actor)
        ->postJson(route('spa.superadmin.staff.promote'), [
            'account_number' => '005431',
            'role' => Role::SUPERADMIN,
            'reason' => 'Promoting legacy admin to superadmin role.',
        ])
        ->assertCreated();

    $samarah->refresh()->load('roles', 'adminProfile');
    expect($samarah->isSuperadmin())->toBeTrue();
    expect($samarah->adminProfile)->not->toBeNull();

    $audit = UserRoleChange::query()
        ->where('target_user_id', $samarah->user_id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->metadata_json['migrated_from_legacy_admin'] ?? false)->toBeTrue();
});

// ─── Part 4: Preflight legacy_admin_drift check ────────────────────────────────

test('preflight reports legacy_admin_drift as deferred when stuck legacy admin users exist', function (): void {
    createLegacyAdminStuckUser('BLA-020');

    $report = app(LoanWorkflowProductionSupportService::class)->preflight(
        LoanWorkflowPreflightStage::PostMigration,
    );

    $issue = collect($report['deferred'])->firstWhere('code', 'legacy_admin_drift');
    expect($issue)->not->toBeNull()
        ->and($issue['count'])->toBeGreaterThanOrEqual(1);
});

test('preflight reports legacy_admin_drift as ok when no stuck legacy admin users exist', function (): void {
    $normalUser = AppUser::factory()->create(['acctno' => 'BLA-021', 'email_verified_at' => now()]);
    AdminProfile::factory()->admin()->create(['user_id' => $normalUser->user_id]);
    Role::attachNamedRole($normalUser, Role::ADMIN);

    $report = app(LoanWorkflowProductionSupportService::class)->preflight(
        LoanWorkflowPreflightStage::PostMigration,
    );

    $issue = collect($report['ok'])->firstWhere('code', 'legacy_admin_drift');
    expect($issue)->not->toBeNull();
});
