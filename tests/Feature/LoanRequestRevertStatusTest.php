<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Role;

beforeEach(function () {
    Role::ensureWorkflowDefaults();
});

test('a superadmin can revert an approved request back to recommended for approval', function () {
    $superadmin = createRevertStatusActor([Role::SUPERADMIN]);
    $member = createRevertStatusActor([Role::MEMBER], acctno: '200001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
        'approved_by' => $superadmin->user_id,
        'approved_at' => now(),
        'approved_amount' => 22000,
        'approved_term' => 18,
        'approved_interest_rate' => 1.25,
        'approval_remarks' => 'Approved by manager.',
        'decision_notes' => 'Approved by manager.',
    ]);

    $response = $this
        ->actingAs($superadmin)
        ->patchJson(
            route('spa.workflow.loan-requests.revert-status', $loanRequest),
            ['reason' => 'Manager approved this by mistake.'],
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.loanRequest.status',
            LoanRequestStatus::RecommendedForApproval->value,
        );

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::RecommendedForApproval);
    expect($loanRequest->approved_by)->toBeNull();
    expect($loanRequest->approved_at)->toBeNull();
    expect($loanRequest->approved_amount)->toBeNull();
    expect($loanRequest->approval_remarks)->toBeNull();
    expect($loanRequest->decision_notes)->toBeNull();

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_REVERT_STATUS);
    expect($change->changed_by)->toBe($superadmin->user_id);
    expect($change->from_status)->toBe(LoanRequestStatus::Approved->value);
    expect($change->to_status)->toBe(LoanRequestStatus::RecommendedForApproval->value);
    expect($change->reason)->toBe('Manager approved this by mistake.');
});

test('a superadmin can revert a recommended request back to under review', function () {
    $superadmin = createRevertStatusActor([Role::SUPERADMIN]);
    $member = createRevertStatusActor([Role::MEMBER], acctno: '200002');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'review_decision' => LoanRequestStatus::RecommendedForApproval->value,
    ]);

    $response = $this
        ->actingAs($superadmin)
        ->patchJson(
            route('spa.workflow.loan-requests.revert-status', $loanRequest),
            ['reason' => 'Processor recommended this by mistake.'],
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.loanRequest.status',
            LoanRequestStatus::UnderReview->value,
        );

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->review_decision)->toBeNull();
});

test('a superadmin cannot revert a converted-to-loan request', function () {
    $superadmin = createRevertStatusActor([Role::SUPERADMIN]);
    $member = createRevertStatusActor([Role::MEMBER], acctno: '200003');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::ConvertedToLoan,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($superadmin)
        ->patchJson(
            route('spa.workflow.loan-requests.revert-status', $loanRequest),
            ['reason' => 'Should not be allowed.'],
        )
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::ConvertedToLoan);
    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('a loan manager cannot revert status', function () {
    $loanManager = createRevertStatusActor([Role::LOAN_MANAGER]);
    $member = createRevertStatusActor([Role::MEMBER], acctno: '200004');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson(
            route('spa.workflow.loan-requests.revert-status', $loanRequest),
            ['reason' => 'Not allowed.'],
        )
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('reverting status requires a reason', function () {
    $superadmin = createRevertStatusActor([Role::SUPERADMIN]);
    $member = createRevertStatusActor([Role::MEMBER], acctno: '200005');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($superadmin)
        ->patchJson(
            route('spa.workflow.loan-requests.revert-status', $loanRequest),
            ['reason' => ''],
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

function createRevertStatusActor(
    array $roles,
    ?string $acctno = null,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    AdminProfile::factory()->admin()->create([
        'user_id' => $user->user_id,
    ]);

    $roleIds = Role::query()
        ->whereIn('name', $roles)
        ->pluck('id')
        ->all();

    $user->roles()->sync($roleIds);
    $user->unsetRelation('roles');

    if (in_array(Role::SUPERADMIN, $roles, true) || in_array(Role::LOAN_MANAGER, $roles, true)) {
        $user->forceFill([
            'two_factor_secret' => 'fakesecret',
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    return $user->load('roles.permissions');
}
