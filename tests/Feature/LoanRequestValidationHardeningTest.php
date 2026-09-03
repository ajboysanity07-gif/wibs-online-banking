<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function createHardeningActor(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()->whereIn('name', $roles)->pluck('id')->all(),
    );

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

test('approve rejects an interest rate above 100 percent', function (): void {
    $manager = createHardeningActor([Role::LOAN_MANAGER]);
    $member = createHardeningActor([Role::MEMBER], '960001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 20000,
            'approved_term' => 12,
            'approved_interest_rate' => 500,
            'approved_payment_frequency' => 'Monthly',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['approved_interest_rate']);
});

test('approve requires an interest rate and payment frequency', function (): void {
    $manager = createHardeningActor([Role::LOAN_MANAGER]);
    $member = createHardeningActor([Role::MEMBER], '960002');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 20000,
            'approved_term' => 12,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['approved_interest_rate', 'approved_payment_frequency']);
});

test('approve rejects an approved term over 360 months', function (): void {
    $manager = createHardeningActor([Role::LOAN_MANAGER]);
    $member = createHardeningActor([Role::MEMBER], '960003');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 20000,
            'approved_term' => 400,
            'approved_interest_rate' => 1.25,
            'approved_payment_frequency' => 'Monthly',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['approved_term']);
});

test('a legacy workflow request cannot be recommended for approval without the core financial fields', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960004');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => $processor->user_id,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'recommended_amount' => null,
        'recommended_term' => null,
        'recommended_interest_rate' => null,
        'recommended_payment_frequency' => null,
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready for manager approval.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recommended_amount']);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
});

test('a legacy workflow request recommends successfully once the core financial fields are present', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960005');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => $processor->user_id,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'recommended_amount' => 20000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.25,
        'recommended_payment_frequency' => 'Monthly',
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready for manager approval.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);
});

test('processing update rejects a non-zero insurance term on an Emergency loan', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960006');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'kind_of_loan' => 'Emergency',
        'submitted_at' => now(),
    ]);

    $payload = [
        'reason' => 'Attempting to set insurance on an exempt loan.',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'processing' => [
            'insurance_term' => 12,
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing.insurance_term']);
});

test('processing update accepts a zero insurance term on an Emergency loan', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960007');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'kind_of_loan' => 'Emergency',
        'submitted_at' => now(),
    ]);

    $payload = [
        'reason' => 'Explicitly zeroing insurance on an exempt loan.',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'processing' => [
            'insurance_term' => 0,
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertOk();
});

test('processing update rejects a malformed applicant cell number', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960008');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'submitted_at' => now(),
    ]);

    $payload = [
        'reason' => 'Correcting applicant contact details.',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'applicant' => [
            'cell_no' => '12345',
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['applicant.cell_no']);
});

test('processing update rejects a future applicant birthdate', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960009');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'submitted_at' => now(),
    ]);

    $payload = [
        'reason' => 'Correcting applicant birthdate.',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'applicant' => [
            'birthdate' => now()->addYear()->toDateString(),
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['applicant.birthdate']);
});

test('processing update requires co-maker identity fields once other co-maker data is filled in', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960010');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'submitted_at' => now(),
    ]);

    $payload = [
        'reason' => 'Adding co-maker employer details without a name.',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        // Mirrors how the correction dialog actually submits: the whole
        // person object, including empty identity fields, not just the one
        // field being changed.
        'co_maker_1' => [
            'first_name' => '',
            'last_name' => '',
            'birthdate' => '',
            'employer_business_name' => 'Acme Corp',
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['co_maker_1.first_name', 'co_maker_1.last_name', 'co_maker_1.birthdate']);
});

test('processing update leaves an unused co-maker slot optional', function (): void {
    $processor = createHardeningActor([Role::LOAN_PROCESSOR]);
    $member = createHardeningActor([Role::MEMBER], '960011');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'submitted_at' => now(),
    ]);

    $payload = [
        'reason' => 'Saving processing terms with no second co-maker.',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'co_maker_2' => [
            'first_name' => '',
            'last_name' => '',
        ],
        'processing' => [
            'notarial_fee' => 250,
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertOk();
});
