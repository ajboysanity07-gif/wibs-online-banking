<?php

use App\LoanRequestStatus;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('cancelled requests are hidden from the default staff queue view', function (): void {
    $processor = User::factory()->create(['acctno' => '500161']);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $member = User::factory()->create(['acctno' => '500162']);

    $visible = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);
    $cancelled = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
    ]);

    $response = $this
        ->actingAs($processor)
        ->getJson('/spa/staff/loan-requests')
        ->assertOk();

    $ids = collect($response->json('data.items'))->pluck('id');

    expect($ids)->toContain($visible->id);
    expect($ids)->not->toContain($cancelled->id);
});

test('a loan processor can still see cancelled requests by explicitly filtering for them', function (): void {
    $processor = User::factory()->create(['acctno' => '500163']);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $member = User::factory()->create(['acctno' => '500164']);

    // Loan-processor queue visibility is scoped to requests assigned to
    // them (LoanWorkflowWorkspaceService::applyVisibleScope); a cancelled
    // request they were never assigned to stays invisible even with the
    // filter applied.
    $cancelled = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'assigned_officer_id' => $processor->user_id,
    ]);

    $response = $this
        ->actingAs($processor)
        ->getJson('/spa/staff/loan-requests?status=cancelled')
        ->assertOk();

    $ids = collect($response->json('data.items'))->pluck('id');

    expect($ids)->toContain($cancelled->id);
});

test('a member does not see their own cancelled loan request in their dashboard summaries', function (): void {
    $member = User::factory()->create(['acctno' => '500165']);

    $active = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);
    $cancelled = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
    ]);

    $summaries = app(App\Services\LoanRequests\LoanRequestLookupService::class)
        ->getMemberRequestSummaries($member);

    $ids = collect($summaries)->pluck('id');

    expect($ids)->toContain($active->id);
    expect($ids)->not->toContain($cancelled->id);
});
