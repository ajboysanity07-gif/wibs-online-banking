<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Models\UserRoleChange;
use App\Services\Admin\StaffManagementService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('superadmin staff management routes allow explicit and legacy superadmins and block other actors', function (): void {
    $superadmin = createManagedSuperadmin();
    $legacyFallbackSuperadmin = createLegacyFallbackSuperadmin();
    $loanOfficer = createManagedStaffUser([Role::LOAN_OFFICER]);
    $loanManager = createManagedStaffUser([Role::LOAN_MANAGER]);
    $member = AppUser::factory()->create();
    $legacyAdmin = createLegacyAdmin();

    $this
        ->actingAs($superadmin)
        ->get(route('superadmin.staff.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('superadmin/staff')
            ->where('auth.isSuperadmin', true)
            ->where('auth.hasActiveStaffAccess', true));

    $this
        ->actingAs($legacyFallbackSuperadmin)
        ->get(route('superadmin.staff.index'))
        ->assertOk();

    $this
        ->actingAs($superadmin)
        ->getJson(route('spa.superadmin.staff.index'))
        ->assertOk();

    $this
        ->actingAs($loanOfficer)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();

    $this
        ->actingAs($loanManager)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();

    $this
        ->actingAs($member)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();

    $this
        ->actingAs($legacyAdmin)
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();

    $this
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();
});

test('suspended staff cannot access superadmin or workflow routes while hybrid member access remains available', function (): void {
    $suspendedSuperadmin = createManagedSuperadmin();
    $actingSuperadmin = createManagedSuperadmin();
    StaffAccessControl::factory()
        ->suspended($actingSuperadmin)
        ->create([
            'user_id' => $suspendedSuperadmin->user_id,
        ]);

    $loanOfficer = createManagedStaffUser([Role::LOAN_OFFICER]);
    StaffAccessControl::factory()
        ->suspended($actingSuperadmin)
        ->create([
            'user_id' => $loanOfficer->user_id,
        ]);

    $owner = AppUser::factory()->create([
        'acctno' => '300001',
    ]);
    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
    ]);

    $suspendedHybrid = createLegacyAdmin(acctno: '300002');
    StaffAccessControl::factory()
        ->suspended($actingSuperadmin)
        ->create([
            'user_id' => $suspendedHybrid->user_id,
        ]);

    $this
        ->actingAs($suspendedSuperadmin->fresh())
        ->get(route('superadmin.staff.index'))
        ->assertForbidden();

    $this
        ->actingAs($suspendedSuperadmin->fresh())
        ->getJson(route('spa.superadmin.staff.index'))
        ->assertForbidden();

    $this
        ->actingAs($loanOfficer->fresh())
        ->get(route('staff.loan-requests.index'))
        ->assertForbidden();

    $this
        ->actingAs($loanOfficer->fresh())
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Attempting access while suspended.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($suspendedHybrid->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('profile.edit', ['onboarding' => 1]));

    $this
        ->actingAs($suspendedHybrid->fresh())
        ->get(route('profile.edit', ['onboarding' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.hasMemberAccess', true)
            ->where('auth.hasActiveStaffAccess', false));
});

test('superadmin can assign and remove editable staff roles while preserving member access and writing audits', function (): void {
    $superadmin = createManagedSuperadmin();
    $target = AppUser::factory()->create([
        'acctno' => '400001',
    ]);

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.roles.update', $target), [
            'role' => Role::LOAN_OFFICER,
            'operation' => 'assign',
            'reason' => 'Assign to the officer queue.',
        ])
        ->assertOk()
        ->assertJsonPath('data.staff.has_member_access', true)
        ->assertJsonPath('data.staff.acctno', '400001');

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.roles.update', $target), [
            'role' => Role::LOAN_MANAGER,
            'operation' => 'assign',
            'reason' => 'Cross-train for management approvals.',
        ])
        ->assertOk();

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.roles.update', $target), [
            'role' => Role::LOAN_OFFICER,
            'operation' => 'remove',
            'reason' => 'Shift the user back to manager-only duties.',
        ])
        ->assertOk();

    $target->refresh()->load('roles.permissions', 'staffAccessControl');

    expect($target->acctno)->toBe('400001');
    expect($target->hasRole(Role::MEMBER))->toBeTrue();
    expect($target->hasRole(Role::LOAN_OFFICER))->toBeFalse();
    expect($target->hasRole(Role::LOAN_MANAGER))->toBeTrue();
    expect($target->hasActiveStaffAccess())->toBeTrue();

    expect(UserRoleChange::query()->count())->toBe(3);

    $assignOfficerAudit = UserRoleChange::query()
        ->where('action', UserRoleChange::ACTION_ROLE_ASSIGNED)
        ->where('role_name', Role::LOAN_OFFICER)
        ->orderBy('id')
        ->firstOrFail();

    expect($assignOfficerAudit->reason)->toBe('Assign to the officer queue.');
    expect($assignOfficerAudit->before_roles_json)->toBe([Role::MEMBER]);
    expect($assignOfficerAudit->after_roles_json)->toContain(
        Role::MEMBER,
        Role::LOAN_OFFICER,
    );
});

test('staff role mutations require reasons and do not manage legacy admin accounts from the new interface', function (): void {
    $superadmin = createManagedSuperadmin();
    $target = AppUser::factory()->create([
        'acctno' => '400002',
    ]);
    $legacyAdmin = createLegacyAdmin();

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.roles.update', $target), [
            'role' => Role::LOAN_OFFICER,
            'operation' => 'assign',
            'reason' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.roles.update', $legacyAdmin), [
            'role' => Role::LOAN_MANAGER,
            'operation' => 'assign',
            'reason' => 'Attempt to move legacy admin into the new flow.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['staff']);
});

test('staff-only account creation hashes the password, assigns selected roles, and hides raw secrets', function (): void {
    $superadmin = createManagedSuperadmin();
    $rawPassword = 'TempPass123!';

    $response = $this
        ->actingAs($superadmin)
        ->postJson(route('spa.superadmin.staff.store'), [
            'username' => 'queue.superadmin.staff',
            'email' => 'queue.superadmin.staff@example.com',
            'phoneno' => '09123456789',
            'password' => $rawPassword,
            'password_confirmation' => $rawPassword,
            'roles' => [Role::SUPERADMIN, Role::LOAN_MANAGER],
            'reason' => 'Provision a dedicated operations staff account.',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.staff.username', 'queue.superadmin.staff')
        ->assertJsonPath('data.staff.has_member_access', false)
        ->assertJsonMissingPath('data.staff.password');

    expect(json_encode($response->json()))->not->toContain($rawPassword);

    $staff = AppUser::query()
        ->where('email', 'queue.superadmin.staff@example.com')
        ->firstOrFail();

    $staff->refresh()->load('roles.permissions', 'staffAccessControl');

    expect($staff->acctno)->toBeNull();
    expect($staff->hasRole(Role::SUPERADMIN))->toBeTrue();
    expect($staff->hasRole(Role::LOAN_MANAGER))->toBeTrue();
    expect($staff->hasRole(Role::MEMBER))->toBeFalse();
    expect($staff->password)->not->toBe($rawPassword);
    expect(password_verify($rawPassword, $staff->password))->toBeTrue();
    expect($staff->hasActiveStaffAccess())->toBeTrue();

    $audit = UserRoleChange::query()->sole();

    expect($audit->action)->toBe(UserRoleChange::ACTION_STAFF_CREATED);
    expect($audit->reason)->toBe('Provision a dedicated operations staff account.');
    expect($audit->metadata_json)->toBe([
        'account_type' => 'staff_only',
    ]);
    expect(json_encode($audit->toArray()))->not->toContain($rawPassword);
});

test('superadmin cannot remove or suspend their own superadmin access and service protects the final active superadmin', function (): void {
    $superadmin = createManagedSuperadmin();

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.roles.update', $superadmin), [
            'role' => Role::SUPERADMIN,
            'operation' => 'remove',
            'reason' => 'Attempting to remove my own access.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.suspend', $superadmin), [
            'reason' => 'Attempting to suspend my own access.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['staff']);

    $service = app(StaffManagementService::class);
    $actor = AppUser::factory()->create([
        'acctno' => null,
    ]);

    expect(fn () => $service->removeRole(
        $superadmin->fresh(),
        $actor,
        Role::SUPERADMIN,
        'Would remove the last active superadmin.',
    ))->toThrow(ValidationException::class);

    expect(fn () => $service->suspend(
        $superadmin->fresh(),
        $actor,
        'Would suspend the last active superadmin.',
    ))->toThrow(ValidationException::class);
});

test('suspending a loan officer unassigns active applications, preserves terminal ones, audits each unassignment, and reactivation does not reclaim them', function (): void {
    $superadmin = createManagedSuperadmin();
    $loanOfficer = createManagedStaffUser([
        Role::LOAN_OFFICER,
    ], acctno: '500001');
    $member = AppUser::factory()->create([
        'acctno' => '500002',
    ]);

    $underReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $approved = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.suspend', $loanOfficer), [
            'reason' => 'Suspending access during a compliance review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.staff.staff_access_status', 'suspended');

    $underReview->refresh();
    $recommended->refresh();
    $approved->refresh();

    expect($underReview->assigned_officer_id)->toBeNull();
    expect($recommended->assigned_officer_id)->toBe($loanOfficer->user_id);
    expect($approved->assigned_officer_id)->toBe($loanOfficer->user_id);

    $unassignments = LoanRequestChange::query()
        ->where('action', LoanRequestChange::ACTION_UNASSIGN_SUSPENDED_OFFICER)
        ->orderBy('loan_request_id')
        ->get();

    expect($unassignments)->toHaveCount(1);
    expect($unassignments->pluck('loan_request_id')->all())->toBe([
        $underReview->id,
    ]);
    $firstMetadata = $unassignments->firstOrFail()->metadata_json;

    expect($firstMetadata['previous_officer']['user_id'] ?? null)
        ->toBe($loanOfficer->user_id);
    expect($firstMetadata['new_officer'] ?? null)->toBeNull();
    expect($firstMetadata['cause'] ?? null)
        ->toBe(\App\Services\LoanRequests\LoanRequestAssignmentService::CAUSE_STAFF_SUSPENDED);
    expect($unassignments->firstOrFail()->changed_by)->toBe($superadmin->user_id);
    expect($unassignments->firstOrFail()->reason)
        ->toBe('Suspending access during a compliance review.');

    $this
        ->actingAs($superadmin)
        ->patchJson(route('spa.superadmin.staff.reactivate', $loanOfficer), [
            'reason' => 'Compliance review completed.',
        ])
        ->assertOk()
        ->assertJsonPath('data.staff.staff_access_status', 'active');

    $underReview->refresh();
    $recommended->refresh();

    expect($underReview->assigned_officer_id)->toBeNull();
    expect($recommended->assigned_officer_id)->toBe($loanOfficer->user_id);
});

function createManagedSuperadmin(?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    AdminProfile::factory()->superadmin()->create([
        'user_id' => $user->user_id,
    ]);

    return $user->fresh()->load(
        'adminProfile',
        'roles.permissions',
        'staffAccessControl',
    );
}

function createLegacyFallbackSuperadmin(): AppUser
{
    $user = createManagedSuperadmin();

    Role::detachNamedRole($user, Role::SUPERADMIN);

    return $user->fresh()->load(
        'adminProfile',
        'roles.permissions',
        'staffAccessControl',
    );
}

function createLegacyAdmin(?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    AdminProfile::factory()->admin()->create([
        'user_id' => $user->user_id,
    ]);

    return $user->fresh()->load(
        'adminProfile',
        'roles.permissions',
        'staffAccessControl',
    );
}

function createManagedStaffUser(
    array $roles,
    ?string $acctno = null,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    $user->roles()->syncWithoutDetaching(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    return $user->fresh()->load(
        'adminProfile',
        'roles.permissions',
        'staffAccessControl',
    );
}
