<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use App\Models\StaffAccessControl;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * Create a hybrid user who is both a portal member and has a staff role.
 *
 * @param  array<int, string>  $staffRoles
 */
function createHybridMemberStaff(string $acctno, array $staffRoles): AppUser
{
    $user = AppUser::factory()->create(['acctno' => $acctno]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', [Role::MEMBER, ...$staffRoles])
            ->pluck('id')
            ->all(),
    );

    return $user->fresh()->load('adminProfile', 'roles.permissions', 'staffAccessControl');
}

/**
 * Create a pure staff user with no member access.
 *
 * @param  array<int, string>  $staffRoles
 */
function createPureStaff(array $staffRoles): AppUser
{
    $user = AppUser::factory()->create(['acctno' => null]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $staffRoles)
            ->pluck('id')
            ->all(),
    );

    return $user->fresh()->load('adminProfile', 'roles.permissions', 'staffAccessControl');
}

/**
 * Create a plain member who will own the loan request.
 */
function createLoanApplicant(string $acctno): AppUser
{
    $user = AppUser::factory()->create(['acctno' => $acctno]);

    $user->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    return $user->fresh()->load('roles.permissions');
}

/**
 * Create a superadmin actor (no member access).
 */
function createConflictSuperadmin(): AppUser
{
    $user = AppUser::factory()->create(['acctno' => null]);

    $user->roles()->sync(
        Role::query()->where('name', Role::SUPERADMIN)->pluck('id')->all(),
    );

    StaffAccessControl::query()->create([
        'user_id' => $user->user_id,
        'status' => StaffAccessControl::STATUS_ACTIVE,
        'suspended_at' => null,
        'suspended_by' => null,
        'suspension_reason' => null,
    ]);

    return $user->fresh()->load('adminProfile', 'roles.permissions', 'staffAccessControl');
}

test('loan processor cannot claim their own application', function (): void {
    $hybridProcessor = createHybridMemberStaff('701001', [Role::LOAN_PROCESSOR]);

    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $hybridProcessor->user_id,
        'acctno' => $hybridProcessor->acctno,
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $this->actingAs($hybridProcessor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertForbidden();
});

test('loan processor cannot be assigned to their own application', function (): void {
    $superadmin = createConflictSuperadmin();
    $hybridProcessor = createHybridMemberStaff('701002', [Role::LOAN_PROCESSOR]);

    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $hybridProcessor->user_id,
        'acctno' => $hybridProcessor->acctno,
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $this->actingAs($superadmin)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $loanRequest), [
            'action' => 'assign',
            'officer_user_id' => $hybridProcessor->user_id,
            'reason' => 'Conflict of interest assignment attempt.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['officer_user_id']);
});

test('loan manager cannot approve their own application', function (): void {
    $hybridManager = createHybridMemberStaff('701003', [Role::LOAN_MANAGER]);

    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $hybridManager->user_id,
        'acctno' => $hybridManager->acctno,
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this->actingAs($hybridManager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => '50000',
            'approved_term' => 12,
        ])
        ->assertForbidden();
});

test('loan manager cannot decline their own application', function (): void {
    $hybridManager = createHybridMemberStaff('701004', [Role::LOAN_MANAGER]);

    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $hybridManager->user_id,
        'acctno' => $hybridManager->acctno,
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this->actingAs($hybridManager)
        ->patchJson(route('spa.workflow.loan-requests.decline', $loanRequest), [
            'decline_category' => 'insufficient_income',
            'decline_reason' => 'Test.',
        ])
        ->assertForbidden();
});

test('different loan processor can claim an application that belongs to another member', function (): void {
    $applicant = createLoanApplicant('701005');
    $processor = createPureStaff([Role::LOAN_PROCESSOR]);

    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $applicant->user_id,
        'acctno' => $applicant->acctno,
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $this->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $processor->user_id);
});

test('conflict is detected by acctno match even without user_id match', function (): void {
    $hybridProcessor = createHybridMemberStaff('701006', [Role::LOAN_PROCESSOR]);

    // Use a different user_id but the same acctno to test the acctno ownership branch.
    $differentUser = createLoanApplicant('701007');
    $loanRequest = LoanRequest::factory()->create([
        'user_id' => $differentUser->user_id,
        'acctno' => '701006',
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $this->actingAs($hybridProcessor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertForbidden();
});
