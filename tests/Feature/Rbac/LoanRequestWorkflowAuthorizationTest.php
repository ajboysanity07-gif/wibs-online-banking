<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Role::ensureWorkflowDefaults();
});

test('members can create and only view or resubmit their own eligible loan requests', function () {
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100001',
    );
    $otherMember = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100002',
    );

    $ownNeedsRevision = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::NeedsRevision,
    ]);
    $ownUnderReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);
    $otherLoanRequest = LoanRequest::factory()->forUser($otherMember)->create([
        'status' => LoanRequestStatus::NeedsRevision,
    ]);

    expect(Gate::forUser($member)->allows('create', LoanRequest::class))->toBeTrue();
    expect(Gate::forUser($member)->allows('view', $ownNeedsRevision))->toBeTrue();
    expect(Gate::forUser($member)->allows('view', $otherLoanRequest))->toBeFalse();
    expect(Gate::forUser($member)->allows('resubmit', $ownNeedsRevision))->toBeTrue();
    expect(Gate::forUser($member)->allows('update', $ownNeedsRevision))->toBeTrue();
    expect(Gate::forUser($member)->allows('resubmit', $ownUnderReview))->toBeFalse();
});

test('loan officers are limited to review-stage workflow actions', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_OFFICER]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100003',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);
    $underReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);
    $approved = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    expect(Gate::forUser($loanOfficer)->allows('startReview', $pendingReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('requestRevision', $pendingReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('requestRevision', $underReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('reject', $underReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('recommendApproval', $underReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('approve', $recommended))->toBeFalse();
    expect(Gate::forUser($loanOfficer)->allows('decline', $recommended))->toBeFalse();
    expect(Gate::forUser($loanOfficer)->allows('convertToLoan', $approved))->toBeFalse();
});

test('loan managers are limited to recommendation, approval, decline, and conversion stages', function () {
    $loanManager = createWorkflowAuthorizationActor([Role::LOAN_MANAGER]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100004',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);
    $approved = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    expect(Gate::forUser($loanManager)->allows('startReview', $pendingReview))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('requestRevision', $pendingReview))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('reject', $pendingReview))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('recommendApproval', $recommended))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('approve', $recommended))->toBeTrue();
    expect(Gate::forUser($loanManager)->allows('decline', $recommended))->toBeTrue();
    expect(Gate::forUser($loanManager)->allows('convertToLoan', $approved))->toBeTrue();
});

test('users with multiple workflow roles receive the combined permissions', function () {
    $actor = createWorkflowAuthorizationActor([
        Role::LOAN_OFFICER,
        Role::LOAN_MANAGER,
    ]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100005',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);
    $approved = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    expect(Gate::forUser($actor)->allows('startReview', $pendingReview))->toBeTrue();
    expect(Gate::forUser($actor)->allows('approve', $recommended))->toBeTrue();
    expect(Gate::forUser($actor)->allows('decline', $recommended))->toBeTrue();
    expect(Gate::forUser($actor)->allows('convertToLoan', $approved))->toBeTrue();
});

test('loan managers can approve recommended requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor(
        [Role::LOAN_MANAGER],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100006',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($loanManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
            'decision_notes' => 'Manager approval.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Approved->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect($loanRequest->reviewed_by)->toBe($loanManager->user_id);
});

test('loan managers can decline recommended requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor(
        [Role::LOAN_MANAGER],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100007',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($loanManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/decline", [
            'decision_notes' => 'Manager decline.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Declined->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Declined);
    expect($loanRequest->reviewed_by)->toBe($loanManager->user_id);
});

test('loan officers cannot approve recommended requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanOfficer = createWorkflowAuthorizationActor(
        [Role::LOAN_OFFICER],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100008',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::RecommendedForApproval);
});

test('loan managers cannot approve under review requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor(
        [Role::LOAN_MANAGER],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100009',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
});

function createWorkflowAuthorizationActor(
    array $roles,
    bool $withAdminProfile = false,
    ?string $acctno = '900001',
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    if ($withAdminProfile) {
        AdminProfile::factory()->admin()->create([
            'user_id' => $user->user_id,
        ]);
    }

    return syncWorkflowAuthorizationRoles($user, $roles);
}

function syncWorkflowAuthorizationRoles(AppUser $user, array $roles): AppUser
{
    $roleIds = Role::query()
        ->whereIn('name', $roles)
        ->pluck('id')
        ->all();

    $user->roles()->sync($roleIds);
    $user->unsetRelation('roles');

    return $user->load('roles.permissions');
}
