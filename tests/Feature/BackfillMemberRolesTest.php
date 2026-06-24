<?php

use App\LoanWorkflowPreflightStage;
use App\Models\AppUser;
use App\Models\Role;
use App\Services\Admin\StaffManagementService;
use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

// ─── Part 1: Registration already assigns the member role ────────────────────

test('registration assigns member role to a new member with acctno', function (): void {
    $this->withSession([
        'member_verification' => [
            'acctno' => 'BFR-REG-001',
            'verified_at' => now()->getTimestamp(),
        ],
    ])->post(route('register.store'), [
        'username' => 'bfrreguser',
        'email' => 'bfrreg@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = AppUser::where('email', 'bfrreg@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole(Role::MEMBER))->toBeTrue();
});

// ─── Part 3: BackfillMemberRoles command ────────────────────────────────────

test('members:backfill-roles assigns the member role to orphaned members', function (): void {
    $orphanA = AppUser::factory()->create(['acctno' => 'BFR-ORP-A1', 'email_verified_at' => now()]);
    $orphanB = AppUser::factory()->create(['acctno' => 'BFR-ORP-A2', 'email_verified_at' => now()]);
    $orphanA->roles()->detach();
    $orphanB->roles()->detach();

    // User without acctno must not receive the member role
    $noAcctno = AppUser::factory()->create(['acctno' => null]);
    $noAcctno->roles()->detach();

    $this->artisan('members:backfill-roles')
        ->expectsOutputToContain('BFR-ORP-A1')
        ->expectsOutputToContain('BFR-ORP-A2')
        ->expectsOutputToContain('Backfilled: 2')
        ->assertSuccessful();

    expect($orphanA->fresh()->hasRole(Role::MEMBER))->toBeTrue()
        ->and($orphanB->fresh()->hasRole(Role::MEMBER))->toBeTrue()
        ->and($noAcctno->fresh()->hasRole(Role::MEMBER))->toBeFalse();
});

test('members:backfill-roles is idempotent: second run backfills zero members', function (): void {
    $orphan = AppUser::factory()->create(['acctno' => 'BFR-ORP-B1', 'email_verified_at' => now()]);
    $orphan->roles()->detach();

    $this->artisan('members:backfill-roles')
        ->expectsOutputToContain('Backfilled: 1')
        ->assertSuccessful();

    expect($orphan->fresh()->hasRole(Role::MEMBER))->toBeTrue();

    $this->artisan('members:backfill-roles')
        ->expectsOutputToContain('Backfilled: 0')
        ->assertSuccessful();

    $assignmentCount = DB::table('user_roles')
        ->join('roles', 'roles.id', '=', 'user_roles.role_id')
        ->where('user_roles.user_id', $orphan->user_id)
        ->where('roles.name', Role::MEMBER)
        ->count();

    expect($assignmentCount)->toBe(1);
});

test('members:backfill-roles skips members that already have the role', function (): void {
    $already = AppUser::factory()->create(['acctno' => 'BFR-ORP-C1', 'email_verified_at' => now()]);
    Role::attachNamedRole($already, Role::MEMBER);

    $this->artisan('members:backfill-roles')
        ->expectsOutputToContain('Backfilled: 0')
        ->assertSuccessful();
});

// ─── searchMembers returns backfilled members ────────────────────────────────

test('searchMembers returns orphaned member after members:backfill-roles', function (): void {
    $orphan = AppUser::factory()->create([
        'acctno' => 'BFR-SRCH-D1',
        'email' => 'bfr.srch@example.com',
        'email_verified_at' => now(),
    ]);
    $orphan->roles()->detach();

    $service = app(StaffManagementService::class);

    expect($service->searchMembers('BFR-SRCH-D1'))->toHaveCount(0);

    $this->artisan('members:backfill-roles')->assertSuccessful();

    $results = $service->searchMembers('BFR-SRCH-D1');
    expect($results)->toHaveCount(1)
        ->and($results->first()->user_id)->toBe($orphan->user_id);
});

// ─── Part 4: Preflight drift check ──────────────────────────────────────────

test('preflight reports member_role_drift as deferred when orphaned members exist', function (): void {
    $orphan = AppUser::factory()->create(['acctno' => 'BFR-PRE-E1', 'email_verified_at' => now()]);
    $orphan->roles()->detach();

    $report = app(LoanWorkflowProductionSupportService::class)->preflight(
        LoanWorkflowPreflightStage::PostMigration,
    );

    $issue = collect($report['deferred'])->firstWhere('code', 'member_role_drift');
    expect($issue)->not->toBeNull()
        ->and($issue['count'])->toBeGreaterThanOrEqual(1);
});

test('preflight reports member_role_drift as ok when no orphaned members exist', function (): void {
    $member = AppUser::factory()->create(['acctno' => 'BFR-PRE-F1', 'email_verified_at' => now()]);
    Role::attachNamedRole($member, Role::MEMBER);

    $report = app(LoanWorkflowProductionSupportService::class)->preflight(
        LoanWorkflowPreflightStage::PostMigration,
    );

    $issue = collect($report['ok'])->firstWhere('code', 'member_role_drift');
    expect($issue)->not->toBeNull();
});
