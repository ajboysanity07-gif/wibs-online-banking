<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Services\Admin\StaffManagementService;
use App\Services\LoanRequests\LoanRequestAssignmentService;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('assignment permissions are scoped to explicit workflow roles', function (): void {
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $loanManager = createAssignmentActor([Role::LOAN_MANAGER]);
    $superadmin = createAssignmentActor([Role::SUPERADMIN]);
    $legacyAdmin = createAssignmentActor(
        [Role::ADMIN],
        adminAccessLevel: AdminProfile::ACCESS_LEVEL_ADMIN,
    );
    $member = createAssignmentActor([Role::MEMBER], acctno: '410001');
    $owner = createAssignmentActor([Role::MEMBER], acctno: '410002');

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    expect($loanOfficer->hasPermission(Permission::LOAN_CLAIM))->toBeTrue();
    expect($loanOfficer->hasPermission(Permission::LOAN_RETURN_TO_QUEUE))->toBeTrue();
    expect($loanOfficer->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT))->toBeFalse();

    expect($loanManager->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT))->toBeTrue();
    expect($loanManager->hasPermission(Permission::LOAN_CLAIM))->toBeFalse();
    expect($loanManager->hasPermission(Permission::LOAN_RETURN_TO_QUEUE))->toBeFalse();

    expect($superadmin->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT))->toBeTrue();
    expect($superadmin->hasPermission(Permission::LOAN_REVIEW))->toBeFalse();
    expect(Gate::forUser($superadmin)->allows('manageAssignment', $loanRequest))->toBeTrue();
    expect(Gate::forUser($superadmin)->allows('startReview', $loanRequest))->toBeFalse();

    expect($legacyAdmin->hasPermission(Permission::LOAN_CLAIM))->toBeFalse();
    expect($legacyAdmin->hasPermission(Permission::LOAN_RETURN_TO_QUEUE))->toBeFalse();
    expect($legacyAdmin->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT))->toBeFalse();
    expect(Gate::forUser($legacyAdmin)->allows('manageAssignment', $loanRequest))->toBeFalse();

    expect($member->hasPermission(Permission::LOAN_CLAIM))->toBeFalse();
});

test('loan processor can claim an unassigned operational request and another officer cannot overwrite it', function (): void {
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $secondOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '420001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $loanOfficer->user_id)
        ->assertJsonPath('data.loanRequest.can_claim', false);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $loanOfficer->user_id);

    $this
        ->actingAs($secondOfficer)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertStatus(409)
        ->assertJsonPath(
            'message',
            'This application has already been assigned to another Loan Processor.',
        );

    $loanRequest->refresh();

    expect($loanRequest->assigned_officer_id)->toBe($loanOfficer->user_id);
    expect(LoanRequestChange::query()->count())->toBe(1);
    expect(LoanRequestChange::query()->sole()->action)
        ->toBe(LoanRequestChange::ACTION_ASSIGNMENT_CLAIMED);
});

test('start review auto claims pending review requests and blocks other officers', function (): void {
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $secondOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '430001');

    $unassignedRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $unassignedRequest), [
            'remarks' => 'Starting review from the shared queue.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value)
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $loanOfficer->user_id);

    $lockedRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $this
        ->actingAs($secondOfficer)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $lockedRequest), [
            'remarks' => 'Trying to take over another officer assignment.',
        ])
        ->assertForbidden();

    $selfAssignedRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $selfAssignedRequest), [
            'remarks' => 'Continuing my assigned review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value)
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $loanOfficer->user_id);

    $changes = LoanRequestChange::query()
        ->orderBy('loan_request_id')
        ->get();

    expect($changes)->toHaveCount(2);
    expect($changes->pluck('action')->all())->toBe([
        LoanRequestChange::ACTION_START_REVIEW,
        LoanRequestChange::ACTION_START_REVIEW,
    ]);
});

test('loan processors can only perform review actions on requests assigned to themselves', function (): void {
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $secondOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '440001');

    $revisionRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $rejectRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $recommendRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $loanOfficer->user_id,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'recommended_amount' => 20000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.25,
        'recommended_payment_frequency' => 'Monthly',
    ]);

    $this
        ->actingAs($secondOfficer)
        ->patchJson(route('spa.workflow.loan-requests.request-revision', $revisionRequest), [
            'remarks' => 'This is not my request.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($secondOfficer)
        ->patchJson(route('spa.workflow.loan-requests.reject', $rejectRequest), [
            'rejection_reason' => 'This is not my request.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($secondOfficer)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $recommendRequest), [
            'review_remarks' => 'This is not my request.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.request-revision', $revisionRequest), [
            'remarks' => 'Please correct the employer address.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::NeedsRevision->value);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.reject', $rejectRequest), [
            'rejection_reason' => 'Income documents are insufficient.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Rejected->value);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $recommendRequest), [
            'review_remarks' => 'Ready for manager approval.',
        ])
        ->assertOk()
        ->assertJsonPath(
            'data.loanRequest.status',
            LoanRequestStatus::RecommendedForApproval->value,
        );
});

test('applicants cannot claim or be manually assigned their own application by user id or acctno', function (): void {
    $loanOfficerApplicant = createAssignmentActor(
        [Role::LOAN_PROCESSOR, Role::MEMBER],
        acctno: '450001',
    );
    $loanManager = createAssignmentActor([Role::LOAN_MANAGER]);
    $owner = createAssignmentActor([Role::MEMBER], acctno: '450099');

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => '450001',
    ]);

    $this
        ->actingAs($loanOfficerApplicant)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertForbidden();

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $loanRequest), [
            'action' => 'assign',
            'officer_user_id' => $loanOfficerApplicant->user_id,
            'reason' => 'Attempting an invalid self-assignment.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['officer_user_id']);
});

test('loan managers can assign reassign and return operational requests to queue', function (): void {
    $loanManager = createAssignmentActor([Role::LOAN_MANAGER]);
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $secondOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '460001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $loanRequest), [
            'action' => 'assign',
            'officer_user_id' => $loanOfficer->user_id,
            'reason' => 'Balancing the pending review queue.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $loanOfficer->user_id);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $loanRequest), [
            'action' => 'reassign',
            'officer_user_id' => $secondOfficer->user_id,
            'reason' => 'Reassigning because the first officer is out of office.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $secondOfficer->user_id);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.return-to-queue', $loanRequest), [
            'reason' => 'Return this request to the shared queue.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', null)
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::PendingReview->value);

    $loanRequest->refresh();

    expect($loanRequest->assigned_officer_id)->toBeNull();
    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview);
    expect(LoanRequestChange::query()->pluck('action')->all())->toBe([
        LoanRequestChange::ACTION_ASSIGNMENT_ASSIGNED,
        LoanRequestChange::ACTION_ASSIGNMENT_REASSIGNED,
        LoanRequestChange::ACTION_ASSIGNMENT_RETURNED_TO_QUEUE,
    ]);
});

test('loan managers can reassign while awaiting member information but not once recommended for approval', function (): void {
    $loanManager = createAssignmentActor([Role::LOAN_MANAGER]);
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $secondOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '465001');

    $awaitingInfoRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::AwaitingMemberInformation,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $awaitingInfoRequest), [
            'action' => 'reassign',
            'officer_user_id' => $secondOfficer->user_id,
            'reason' => 'Reassigning while the member gathers additional information.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $secondOfficer->user_id);

    $recommendedRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $recommendedRequest), [
            'action' => 'reassign',
            'officer_user_id' => $secondOfficer->user_id,
            'reason' => 'This should fail because the request is already recommended for approval.',
        ])
        ->assertForbidden();
});

test('manual assignment rejects ineligible officers and terminal requests', function (): void {
    $loanManager = createAssignmentActor([Role::LOAN_MANAGER]);
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $memberOnly = createAssignmentActor([Role::MEMBER], acctno: '470001');
    $suspendedOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $owner = createAssignmentActor([Role::MEMBER], acctno: '470002');

    StaffAccessControl::factory()->suspended($loanManager)->create([
        'user_id' => $suspendedOfficer->user_id,
    ]);

    $pendingRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);
    $recommendedRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => null,
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $pendingRequest), [
            'action' => 'assign',
            'officer_user_id' => $memberOnly->user_id,
            'reason' => 'This should fail because the target is not an officer.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['officer_user_id']);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $pendingRequest), [
            'action' => 'assign',
            'officer_user_id' => $suspendedOfficer->user_id,
            'reason' => 'This should fail because the target is suspended.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['officer_user_id']);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.assignment.update', $recommendedRequest), [
            'action' => 'assign',
            'officer_user_id' => $loanOfficer->user_id,
            'reason' => 'Recommended requests should keep their historical reviewer.',
        ])
        ->assertForbidden();
});

test('assigned loan processors can return their own requests to queue but not another officers request', function (): void {
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $secondOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '480001');

    $ownedRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $otherRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::NeedsRevision,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.return-to-queue', $ownedRequest), [
            'reason' => 'Returning this request because additional review time is needed elsewhere.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', null)
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value);

    $this
        ->actingAs($secondOfficer)
        ->patchJson(route('spa.workflow.loan-requests.return-to-queue', $otherRequest), [
            'reason' => 'Attempting to return another officer request.',
        ])
        ->assertForbidden();
});

test('queue api scopes active work by ownership and exposes assignment metadata', function (): void {
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $secondOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $loanManager = createAssignmentActor([Role::LOAN_MANAGER]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '490001');

    $unassignedRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);
    $ownedActiveRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $otherActiveRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $secondOfficer->user_id,
    ]);
    $ownedHistoryRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $otherHistoryRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $secondOfficer->user_id,
    ]);

    $officerResponse = $this
        ->actingAs($loanOfficer)
        ->getJson(route('spa.staff.loan-requests.index'));

    $officerResponse->assertOk();

    $officerItems = collect($officerResponse->json('data.items'));
    $officerIds = $officerItems->pluck('id')->all();

    expect($officerIds)->toContain(
        $unassignedRequest->id,
        $ownedActiveRequest->id,
        $ownedHistoryRequest->id,
    );
    expect($officerIds)->not->toContain(
        $otherActiveRequest->id,
        $otherHistoryRequest->id,
    );
    expect(collect($officerResponse->json('data.meta.assignmentFilters'))
        ->pluck('value')
        ->all())
        ->toBe(['unassigned', 'mine']);

    $unassignedPayload = $officerItems->firstWhere('id', $unassignedRequest->id);

    expect($unassignedPayload['assigned_officer'])->toBeNull();
    expect($unassignedPayload['can_claim'])->toBeTrue();
    expect($unassignedPayload['assignment_state'])->toBe('unassigned');

    $managerResponse = $this
        ->actingAs($loanManager)
        ->getJson(route('spa.staff.loan-requests.index', [
            'assignment' => 'all',
        ]));

    $managerResponse->assertOk();

    $managerIds = collect($managerResponse->json('data.items'))
        ->pluck('id')
        ->all();

    expect($managerIds)->toContain(
        $unassignedRequest->id,
        $ownedActiveRequest->id,
        $otherActiveRequest->id,
        $ownedHistoryRequest->id,
        $otherHistoryRequest->id,
    );
    expect(collect($managerResponse->json('data.meta.assignmentFilters'))
        ->pluck('value')
        ->all())
        ->toContain('all');
    expect(collect($managerResponse->json('data.meta.assignmentOfficers'))
        ->pluck('user_id')
        ->all())
        ->toContain($loanOfficer->user_id, $secondOfficer->user_id);
});

test('eligible officer options count only operational workload and warn at the configured threshold', function (): void {
    $loanOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '500001');

    LoanRequest::factory()
        ->count(30)
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::PendingReview,
            'assigned_officer_id' => $loanOfficer->user_id,
        ]);

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $option = collect(app(LoanRequestAssignmentService::class)->eligibleOfficerOptions())
        ->firstWhere('user_id', $loanOfficer->user_id);

    expect($option)->not->toBeNull();
    expect($option['active_assignment_count'])->toBe(30);
    expect($option['has_workload_warning'])->toBeTrue();
    expect($option['workload_warning_label'])->toBe('High workload');
});

test('suspension and loan processor role removal only unassign operational requests', function (): void {
    $superadmin = createAssignmentActor([Role::SUPERADMIN]);
    $suspendedOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $removedOfficer = createAssignmentActor([Role::LOAN_PROCESSOR]);
    $member = createAssignmentActor([Role::MEMBER], acctno: '510001');

    $suspendedPending = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $suspendedOfficer->user_id,
    ]);
    $suspendedUnderReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $suspendedOfficer->user_id,
    ]);
    $suspendedNeedsRevision = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::NeedsRevision,
        'assigned_officer_id' => $suspendedOfficer->user_id,
    ]);
    $suspendedRecommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $suspendedOfficer->user_id,
    ]);

    $removedPending = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $removedOfficer->user_id,
    ]);
    $removedUnderReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $removedOfficer->user_id,
    ]);
    $removedNeedsRevision = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::NeedsRevision,
        'assigned_officer_id' => $removedOfficer->user_id,
    ]);
    $removedApproved = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'assigned_officer_id' => $removedOfficer->user_id,
    ]);

    $staffService = app(StaffManagementService::class);

    $staffService->suspend(
        $suspendedOfficer->fresh(),
        $superadmin,
        'Suspending access during a compliance review.',
    );

    $staffService->removeRole(
        $removedOfficer->fresh(),
        $superadmin,
        Role::LOAN_PROCESSOR,
        'Removing loan processor access after a staffing change.',
    );

    $suspendedPending->refresh();
    $suspendedUnderReview->refresh();
    $suspendedNeedsRevision->refresh();
    $suspendedRecommended->refresh();
    $removedPending->refresh();
    $removedUnderReview->refresh();
    $removedNeedsRevision->refresh();
    $removedApproved->refresh();

    expect($suspendedPending->assigned_officer_id)->toBeNull();
    expect($suspendedUnderReview->assigned_officer_id)->toBeNull();
    expect($suspendedNeedsRevision->assigned_officer_id)->toBeNull();
    expect($suspendedRecommended->assigned_officer_id)
        ->toBe($suspendedOfficer->user_id);
    expect($removedPending->assigned_officer_id)->toBeNull();
    expect($removedUnderReview->assigned_officer_id)->toBeNull();
    expect($removedNeedsRevision->assigned_officer_id)->toBeNull();
    expect($removedApproved->assigned_officer_id)
        ->toBe($removedOfficer->user_id);

    $unassignments = LoanRequestChange::query()
        ->where(
            'action',
            LoanRequestChange::ACTION_ASSIGNMENT_UNASSIGNED_STAFF_UNAVAILABLE,
        )
        ->orderBy('loan_request_id')
        ->get();
    $causes = $unassignments
        ->map(
            static fn (LoanRequestChange $change): ?string => $change->metadata_json['cause'] ?? null,
        )
        ->filter()
        ->values()
        ->all();

    expect($unassignments)->toHaveCount(6);
    expect($causes)->toContain(
        LoanRequestAssignmentService::CAUSE_STAFF_SUSPENDED,
        LoanRequestAssignmentService::CAUSE_ROLE_REMOVED,
    );
});

function createAssignmentActor(
    array $roles,
    ?string $acctno = null,
    ?string $adminAccessLevel = null,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    if ($adminAccessLevel === AdminProfile::ACCESS_LEVEL_SUPERADMIN) {
        AdminProfile::factory()->superadmin()->create([
            'user_id' => $user->user_id,
        ]);
    } elseif ($adminAccessLevel === AdminProfile::ACCESS_LEVEL_ADMIN) {
        AdminProfile::factory()->admin()->create([
            'user_id' => $user->user_id,
        ]);
    }

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    $twoFactorRoles = [Role::SUPERADMIN, Role::LOAN_MANAGER];
    if (! empty(array_intersect($roles, $twoFactorRoles))) {
        $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();
    }

    return $user->fresh()->load(
        'adminProfile',
        'roles.permissions',
        'staffAccessControl',
    );
}
