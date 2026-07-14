<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('generate documents without a document key triggers bulk generation instead of failing validation', function (): void {
    $processor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);
    $processor = $processor->fresh(['roles.permissions', 'staffAccessControl']);

    $member = AppUser::factory()->create([
        'acctno' => '900002',
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    $loanRequest = LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
            'assigned_officer_id' => $processor->user_id,
        ]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.documents.generate', [
                'loanRequest' => $loanRequest,
            ]),
            [],
        );

    $response->assertOk();
    $response->assertJsonMissingValidationErrors('document_key');
    expect($response->json('data.documentResults'))->toBeArray()->not->toBeEmpty();
});
