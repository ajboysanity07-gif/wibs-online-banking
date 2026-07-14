<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * Guards the data-wipe hazard called out in the processing-panel split plan.
 *
 * The inline "Processing details" panel does not let staff edit the loan
 * amount/term/purpose, but it MUST still send a `loan_request` passthrough of
 * the current values. The backend's processing payload builder falls back to an
 * all-null loan_request array when the `loan_request` key is absent, which would
 * silently reset requested_amount/term/purpose. This test locks in that a
 * processing-only payload carrying the passthrough preserves the loan request
 * details while still updating the recommendation and processing fields.
 */
test('processing update with loan_request passthrough preserves loan details while updating processing fields', function (): void {
    $processor = createProcessingActor([Role::LOAN_PROCESSOR]);
    $member = createProcessingActor([Role::MEMBER], '950001');

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
        'reason' => 'Recorded verified processing terms.',
        'information_source' => 'Verified staff review',
        // Passthrough of the current (unedited) loan request details. The inline
        // panel always sends this so a processing-only update never wipes them.
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'processing' => [
            'service_charge_rate' => 1.25,
            'notarial_fee' => 250,
        ],
        'recommended_amount' => 24000,
        'recommended_term' => 10,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => '15th & 30th',
        'recommendation_remarks' => 'Recommend approval after full review.',
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertOk()
        ->assertJsonPath('data.loanRequest.requested_amount', '25000.00')
        ->assertJsonPath('data.loanRequest.requested_term', 12)
        ->assertJsonPath('data.loanRequest.loan_purpose', 'Home improvement')
        ->assertJsonPath('data.loanRequest.availment_status', 'New')
        ->assertJsonPath('data.loanRequest.recommended_amount', '24000.00')
        ->assertJsonPath('data.loanRequest.recommended_term', 10);

    $loanRequest->refresh();

    expect($loanRequest->requested_amount)->toBe('25000.00')
        ->and((int) $loanRequest->requested_term)->toBe(12)
        ->and($loanRequest->loan_purpose)->toBe('Home improvement')
        ->and($loanRequest->availment_status)->toBe('New')
        ->and($loanRequest->recommended_amount)->toBe('24000.00');
});

test('processing update rejects a non-canonical recommended_payment_frequency value', function (): void {
    $processor = createProcessingActor([Role::LOAN_PROCESSOR]);
    $member = createProcessingActor([Role::MEMBER], '950002');

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
        'reason' => 'Recorded verified processing terms.',
        'information_source' => 'Verified staff review',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'recommended_payment_frequency' => 'SEMI-MONTHLY',
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['recommended_payment_frequency']);
});

test('processing update accepts the affidavit of undertaking notarization fields', function (): void {
    $processor = createProcessingActor([Role::LOAN_PROCESSOR]);
    $member = createProcessingActor([Role::MEMBER], '950003');

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
        'reason' => 'Recorded notarization details.',
        'information_source' => 'Verified staff review',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'processing' => [
            'signing_place' => 'Tagum City',
            'notarial_province' => 'Davao del Norte',
            'valid_id_number' => 'DL-N01-23-456789',
            'valid_id_issued_at' => 'LTO Tagum',
            'doc_number' => '12',
            'page_number' => '3',
            'book_number' => 'IV',
            'series_year' => '2026',
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertOk();

    $entries = $loanRequest->dataEntries()
        ->whereIn('field_key', [
            'signing_place', 'notarial_province', 'valid_id_number', 'valid_id_issued_at',
            'doc_number', 'page_number', 'book_number', 'series_year',
        ])
        ->get()
        ->pluck('value_json', 'field_key');

    expect($entries['signing_place']['value'])->toBe('Tagum City')
        ->and($entries['notarial_province']['value'])->toBe('Davao del Norte')
        ->and($entries['valid_id_number']['value'])->toBe('DL-N01-23-456789')
        ->and($entries['valid_id_issued_at']['value'])->toBe('LTO Tagum')
        ->and($entries['doc_number']['value'])->toBe('12')
        ->and($entries['page_number']['value'])->toBe('3')
        ->and($entries['book_number']['value'])->toBe('IV')
        ->and($entries['series_year']['value'])->toBe('2026');
});

test('processing update rejects an oversized notarization field value', function (): void {
    $processor = createProcessingActor([Role::LOAN_PROCESSOR]);
    $member = createProcessingActor([Role::MEMBER], '950004');

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
        'reason' => 'Recorded notarization details.',
        'information_source' => 'Verified staff review',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'processing' => [
            'signing_place' => str_repeat('A', 256),
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing.signing_place']);
});

/**
 * @param  list<string>  $roles
 */
function createProcessingActor(array $roles, ?string $acctno = null): AppUser
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
