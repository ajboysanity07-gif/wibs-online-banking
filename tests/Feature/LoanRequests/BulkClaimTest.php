<?php

use App\LoanRequestStatus;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('loan processor bulk claims eligible requests', function (): void {
    $processor = User::factory()->create(['acctno' => '500001']);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $member = User::factory()->create(['acctno' => '500002']);

    $requests = LoanRequest::factory()->forUser($member)->count(3)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);

    $response = $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.bulk-claim'), [
            'loan_request_ids' => $requests->pluck('id')->all(),
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.succeeded_count', 3)
        ->assertJsonPath('data.failed_count', 0);

    foreach ($requests as $loanRequest) {
        expect($loanRequest->refresh()->assigned_officer_id)->toBe($processor->user_id);
    }

    expect(LoanRequestChange::query()->count())->toBe(3);
});

test('bulk claim reports per-row failures without blocking the rest of the batch', function (): void {
    $processor = User::factory()->create(['acctno' => '500011']);
    $otherProcessor = User::factory()->create(['acctno' => '500012']);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);
    Role::attachNamedRole($otherProcessor, Role::LOAN_PROCESSOR);

    $member = User::factory()->create(['acctno' => '500013']);

    $claimable = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);
    $alreadyClaimedByOther = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $otherProcessor->user_id,
    ]);
    $wrongStatus = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'assigned_officer_id' => null,
    ]);

    $response = $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.bulk-claim'), [
            'loan_request_ids' => [
                $claimable->id,
                $alreadyClaimedByOther->id,
                $wrongStatus->id,
            ],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.succeeded', [$claimable->id])
        ->assertJsonPath('data.succeeded_count', 1)
        ->assertJsonPath('data.failed_count', 2);

    expect($claimable->refresh()->assigned_officer_id)->toBe($processor->user_id);
    expect($alreadyClaimedByOther->refresh()->assigned_officer_id)->toBe($otherProcessor->user_id);
    expect($wrongStatus->refresh()->assigned_officer_id)->toBeNull();
    expect(LoanRequestChange::query()->count())->toBe(1);
});

test('bulk claim blocks claiming a request the actor owns', function (): void {
    $processor = User::factory()->create(['acctno' => '500021']);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $ownRequest = LoanRequest::factory()->forUser($processor)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);

    $response = $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.bulk-claim'), [
            'loan_request_ids' => [$ownRequest->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.succeeded_count', 0)
        ->assertJsonPath('data.failed_count', 1);

    expect($ownRequest->refresh()->assigned_officer_id)->toBeNull();
});

test('bulk claim validation rejects empty or invalid ids', function (): void {
    $processor = User::factory()->create(['acctno' => '500031']);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.bulk-claim'), [
            'loan_request_ids' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('loan_request_ids');

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.bulk-claim'), [
            'loan_request_ids' => [999999],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('loan_request_ids.0');

    expect(LoanRequestChange::query()->count())->toBe(0);
});
