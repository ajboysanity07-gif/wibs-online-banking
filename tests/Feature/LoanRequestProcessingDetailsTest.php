<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

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

test('processing update rejects the removed doc/page/book/series/valid-id notarization keys', function (): void {
    $processor = createProcessingActor([Role::LOAN_PROCESSOR]);
    $member = createProcessingActor([Role::MEMBER], '950005');

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
        'reason' => 'Attempted to record retired notarization keys.',
        'information_source' => 'Verified staff review',
        'loan_request' => [
            'requested_amount' => '25000.00',
            'requested_term' => 12,
            'loan_purpose' => 'Home improvement',
            'availment_status' => 'New',
        ],
        'processing' => [
            'doc_number' => '12',
            'page_number' => '3',
            'book_number' => 'IV',
            'series_year' => '2026',
            'signing_place' => 'Tagum City',
            'notarial_province' => 'Davao del Norte',
            'valid_id_number' => 'DL-N01-23-456789',
            'valid_id_issued_at' => 'LTO Tagum',
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing']);
});

/**
 * Regression guard for the Charges & Fees "did not appear populated after
 * reload" investigation: confirmed via a real Playwright browser session
 * (typed input -> outgoing PATCH payload -> save response -> hard reload)
 * that the current code round-trips correctly. This pins that behavior at
 * three layers so a future regression can't silently reintroduce it: the
 * save response, a subsequent full-page reload, and the raw DB rows,
 * bypassing every serializer.
 */
test('processing update round-trips all Charges & Fees fields through save response, reload, and raw database', function (): void {
    $processor = createProcessingActor([Role::LOAN_PROCESSOR]);
    $member = createProcessingActor([Role::MEMBER], '950006');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $chargesAndFees = [
        'service_charge_rate' => '0.02',
        'insurance_rate' => '0.01',
        'insurance_term' => '12',
        'loan_security_rate' => '0.01',
        'savings_rate' => '0.03',
        'documentary_stamp_rate' => '0.015',
        'notarial_fee' => '500',
        'penalty_rate_per_month' => '0.02',
    ];

    // Mirrors the full 14-key payload buildInlineProcessingPayload() sends
    // from the real "Processing details" form, not just the 8 fields under
    // test, so this exercises the exact shape the frontend submits.
    $payload = [
        'reason' => 'Regression test save',
        'information_source' => 'Automated regression test',
        'loan_request' => [],
        'processing' => [
            ...$chargesAndFees,
            'notarial_venue' => null,
            'witness_one_name' => null,
            'witness_two_name' => null,
            'barangay_official_name' => null,
            'barangay_official_title' => null,
            'guaranteed_net_take_home_pay' => '21333.34',
        ],
        'recommended_amount' => null,
        'recommended_term' => null,
        'recommended_interest_rate' => null,
        'recommended_payment_frequency' => null,
        'recommendation_remarks' => null,
    ];

    $expectedInsuranceTerm = (int) $chargesAndFees['insurance_term'];

    $saveResponse = $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $payload)
        ->assertOk();

    foreach ($chargesAndFees as $field => $value) {
        $expected = $field === 'insurance_term' ? $expectedInsuranceTerm : $value;

        $saveResponse->assertJsonPath("data.dataSections.processing.{$field}", $expected);
    }

    $reloadResponse = $this
        ->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk();

    $reloadResponse->assertInertia(function (Assert $page) use ($chargesAndFees, $expectedInsuranceTerm): void {
        foreach ($chargesAndFees as $field => $value) {
            $expected = $field === 'insurance_term' ? $expectedInsuranceTerm : $value;

            $page->where("dataSections.processing.{$field}", $expected);
        }
    });

    $rawEntries = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('field_key', array_keys($chargesAndFees))
        ->get()
        ->keyBy('field_key');

    foreach ($chargesAndFees as $field => $value) {
        $expected = $field === 'insurance_term' ? $expectedInsuranceTerm : $value;

        expect($rawEntries->get($field))
            ->not->toBeNull()
            ->and($rawEntries->get($field)->value_json['value'] ?? null)->toBe($expected);
    }
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
