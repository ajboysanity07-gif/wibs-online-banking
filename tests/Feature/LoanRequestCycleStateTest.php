<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\MemberDependent;
use App\Models\MemberDependentProfile;
use App\Models\Role;
use App\Services\LoanRequests\LoanRequestCycleStateService;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * First time this member has ever been processed: no confirmed record
 * exists yet, so every slot is unlocked with the default New/1 (first
 * enrollment cycle on the Generali form).
 */
test('first processing save for a member is unlocked with New/1 default', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960001');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);

    $initialState = $cycleStateService->resolveState($loanRequest);

    expect($initialState['applicant']['locked'])->toBeFalse()
        ->and($initialState['applicant']['cycle_status'])->toBe('New')
        ->and($initialState['applicant']['cycle_number'])->toBe(1)
        ->and($initialState['child_1']['locked'])->toBeFalse()
        ->and($initialState['child_1']['cycle_status'])->toBe('New')
        ->and($initialState['child_1']['cycle_number'])->toBe(1);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'New',
                'applicant_cycle_number' => 1,
                'dependent_child_1_cycle_status' => 'New',
                'dependent_child_1_cycle_number' => 1,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.cycleState.applicant.locked', false)
        ->assertJsonPath('data.cycleState.applicant.cycle_status', 'New')
        ->assertJsonPath('data.cycleState.applicant.cycle_number', 1);

    $dependentProfile = MemberDependentProfile::query()
        ->whereHas('memberApplicationProfile', fn ($query) => $query->where('user_id', $member->user_id))
        ->firstOrFail();

    expect($dependentProfile->applicant_confirmed_cycle_status)->toBe('New')
        ->and($dependentProfile->applicant_confirmed_cycle_number)->toBe(1);

    $childRow = MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'child')
        ->where('slot', 1)
        ->first();

    expect($childRow)->not->toBeNull()
        ->and($childRow->confirmed_cycle_status)->toBe('New')
        ->and($childRow->confirmed_cycle_number)->toBe(1);
});

/**
 * Cycle number is always required when status is present -- the Generali
 * form labels "New (1st-2nd)" and "Old (3rd cycle & up ___)", so both
 * require a number.
 */
test('submitting Old status with a missing cycle number is rejected', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960005');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'Old',
                'applicant_cycle_number' => null,
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing.applicant_cycle_number']);
});

test('submitting New status with a missing cycle number is also rejected', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960007');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'New',
                'applicant_cycle_number' => null,
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing.applicant_cycle_number']);
});

/**
 * New covers cycles 1-2. Confirming New/1 means the next loan advances
 * to New/2 (still within the "New" range on the Generali form).
 */
test('a slot confirmed as New/1 locks the next loan to New/2', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960006');
    $applicationProfile = MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    MemberDependentProfile::query()->create([
        'member_application_profile_id' => $applicationProfile->id,
        'applicant_confirmed_cycle_status' => 'New',
        'applicant_confirmed_cycle_number' => 1,
    ]);

    $secondLoanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($secondLoanRequest);

    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('New')
        ->and($state['applicant']['cycle_number'])->toBe(2);
});

/**
 * New/2 is the last "New" cycle. The next loan after that flips to Old/3.
 */
test('a slot confirmed as New/2 locks the next loan to Old/3', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960008');
    $applicationProfile = MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    MemberDependentProfile::query()->create([
        'member_application_profile_id' => $applicationProfile->id,
        'applicant_confirmed_cycle_status' => 'New',
        'applicant_confirmed_cycle_number' => 2,
    ]);

    $secondLoanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($secondLoanRequest);

    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('Old')
        ->and($state['applicant']['cycle_number'])->toBe(3);
});

/**
 * Old/N always advances to Old/N+1.
 */
test('a slot confirmed as Old/5 locks the next loan to Old/6', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960009');
    $applicationProfile = MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    MemberDependentProfile::query()->create([
        'member_application_profile_id' => $applicationProfile->id,
        'applicant_confirmed_cycle_status' => 'Old',
        'applicant_confirmed_cycle_number' => 5,
    ]);

    $secondLoanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($secondLoanRequest);

    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('Old')
        ->and($state['applicant']['cycle_number'])->toBe(6);
});

/**
 * The loan request that produced a confirmation remains free to edit it:
 * re-saving processing details on the SAME loan request with a different
 * value must succeed (not be rejected as "locked mismatch"), and the
 * confirmed record updates in place -- still owned by that same loan
 * request, not incremented as if it were a later loan continuing the cycle.
 */
test('the confirming loan request can revise its own confirmed cycle value', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960004');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'New',
                'applicant_cycle_number' => 1,
            ],
        ])
        ->assertOk();

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);

    // Reopening the same loan request: not locked, shows the saved value.
    $reopenedState = $cycleStateService->resolveState($loanRequest->fresh());

    expect($reopenedState['applicant']['locked'])->toBeFalse()
        ->and($reopenedState['applicant']['cycle_status'])->toBe('New')
        ->and($reopenedState['applicant']['cycle_number'])->toBe(1);

    // Correcting the typo on the same loan request succeeds...
    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => 'Correcting a typo in the confirmed cycle value.',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'Old',
                'applicant_cycle_number' => 3,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.cycleState.applicant.locked', false)
        ->assertJsonPath('data.cycleState.applicant.cycle_status', 'Old')
        ->assertJsonPath('data.cycleState.applicant.cycle_number', 3);

    // ...and the confirmed record is updated in place, still owned by this
    // same loan request (not incremented as if it were a later loan).
    $dependentProfile = MemberDependentProfile::query()
        ->whereHas('memberApplicationProfile', fn ($query) => $query->where('user_id', $member->user_id))
        ->firstOrFail();

    expect($dependentProfile->applicant_confirmed_cycle_status)->toBe('Old')
        ->and($dependentProfile->applicant_confirmed_cycle_number)->toBe(3)
        ->and($dependentProfile->applicant_confirmed_by_loan_request_id)->toBe($loanRequest->id);

    // A second, different loan request for the same member sees the slot
    // as locked, continuing from A's confirmed value.
    $secondLoanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $secondState = $cycleStateService->resolveState($secondLoanRequest);

    expect($secondState['applicant']['locked'])->toBeTrue()
        ->and($secondState['applicant']['cycle_status'])->toBe('Old')
        ->and($secondState['applicant']['cycle_number'])->toBe(4);
});

/**
 * A second processed loan for the same member: the applicant and child_1
 * slots now have a confirmed record from the first loan's save, so they
 * must be locked and auto-advanced to the next cycle within the correct
 * status range (New/1 → New/2, not Old/2).
 */
test('a second processed loan advances confirmed slots to the next cycle', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960002');
    $applicationProfile = MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $dependentProfile = MemberDependentProfile::query()->create([
        'member_application_profile_id' => $applicationProfile->id,
        'applicant_confirmed_cycle_status' => 'New',
        'applicant_confirmed_cycle_number' => 1,
    ]);

    MemberDependent::query()->create([
        'member_dependent_profile_id' => $dependentProfile->id,
        'category' => 'child',
        'slot' => 1,
        'name' => 'Junior',
        'confirmed_cycle_status' => 'New',
        'confirmed_cycle_number' => 1,
    ]);

    $secondLoanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($secondLoanRequest);

    // New/1 → New/2 (still within the 1-2 "New" range)
    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('New')
        ->and($state['applicant']['cycle_number'])->toBe(2)
        ->and($state['child_1']['locked'])->toBeTrue()
        ->and($state['child_1']['cycle_status'])->toBe('New')
        ->and($state['child_1']['cycle_number'])->toBe(2);

    // Submitting the exact locked value succeeds...
    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $secondLoanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'New',
                'applicant_cycle_number' => 2,
                'dependent_child_1_cycle_status' => 'New',
                'dependent_child_1_cycle_number' => 2,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.cycleState.applicant.locked', true)
        ->assertJsonPath('data.cycleState.applicant.cycle_number', 2);

    // ...and the confirmed record on file is untouched (still 1, so the
    // next loan continues to compute New/2, not New/3).
    expect($dependentProfile->refresh()->applicant_confirmed_cycle_number)->toBe(1);
});

/**
 * A locked slot cannot be overridden with a value that doesn't match the
 * computed next cycle -- the processor has no choice once a confirmed
 * record exists.
 */
test('submitting a mismatched cycle value for a locked slot is rejected', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '960003');
    $applicationProfile = MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    MemberDependentProfile::query()->create([
        'member_application_profile_id' => $applicationProfile->id,
        'applicant_confirmed_cycle_status' => 'New',
        'applicant_confirmed_cycle_number' => 1,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                // Locked slot expects New/2 -- submitting Old/3 must fail.
                'applicant_cycle_status' => 'Old',
                'applicant_cycle_number' => 3,
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing.applicant_cycle_status']);
});

/**
 * @param  list<string>  $roles
 */
function createCycleStateActor(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}
