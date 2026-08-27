<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestNotificationEvent;
use App\Models\Role;
use App\Services\LoanRequests\LoanRequestDataService;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('loan processor can update payout details directly without a member correction round trip', function (): void {
    $processor = createPayoutDetailsActor([Role::LOAN_PROCESSOR]);
    $member = createPayoutDetailsActor([Role::MEMBER], '960002');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $payload = [
        'payment_option' => 'ATM Deduction',
        'payout_atm_number' => '1234567890',
        'payout_atm_holder_name' => 'Juan Dela Cruz',
        'reason' => 'Member called to report they now have an ATM card.',
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.payout-details', $loanRequest), $payload)
        ->assertOk();

    $loanRequest->refresh();

    // Status must not change -- this is a direct data update, not a
    // needs-revision/awaiting-member-information round trip.
    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);

    $flatValues = app(LoanRequestDataService::class)->loadFlatValues($loanRequest);

    expect($flatValues['payment_option'])->toBe('ATM Deduction')
        ->and($flatValues['payout_atm_number'])->toBe('1234567890')
        ->and($flatValues['payout_atm_holder_name'])->toBe('Juan Dela Cruz');

    $entry = $loanRequest->dataEntries()
        ->where('field_key', 'payment_option')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->confirmed_by_member)->toBeFalse()
        ->and($entry->metadata_json['updated_by_actor_type'] ?? null)->toBe('staff')
        ->and($entry->metadata_json['updated_by_user_id'] ?? null)->toBe($processor->user_id);

    $auditEntry = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('action', LoanRequestChange::ACTION_UPDATE_PAYOUT_DETAILS)
        ->first();

    expect($auditEntry)->not->toBeNull()
        ->and($auditEntry->changed_by)->toBe($processor->user_id)
        ->and($auditEntry->from_status)->toBe(LoanRequestStatus::UnderReview->value)
        ->and($auditEntry->to_status)->toBe(LoanRequestStatus::UnderReview->value);

    $notification = LoanRequestNotificationEvent::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('event_type', 'payout_details_updated_by_staff')
        ->first();

    expect($notification)->not->toBeNull();
});

test('unassigned processor cannot update payout details', function (): void {
    $processor = createPayoutDetailsActor([Role::LOAN_PROCESSOR]);
    $otherProcessor = createPayoutDetailsActor([Role::LOAN_PROCESSOR]);
    $member = createPayoutDetailsActor([Role::MEMBER], '960003');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($otherProcessor)
        ->patchJson(route('spa.workflow.loan-requests.payout-details', $loanRequest), [
            'payment_option' => 'ATM Deduction',
            'payout_atm_number' => '1234567890',
            'reason' => 'Testing unauthorized access.',
        ])
        ->assertForbidden();
});

test('payout details cannot be updated once the request is approved', function (): void {
    $processor = createPayoutDetailsActor([Role::LOAN_PROCESSOR]);
    $member = createPayoutDetailsActor([Role::MEMBER], '960004');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.payout-details', $loanRequest), [
            'payment_option' => 'ATM Deduction',
            'payout_atm_number' => '1234567890',
            'reason' => 'Testing status gate.',
        ])
        ->assertForbidden();
});

function createPayoutDetailsActor(array $roles, ?string $acctno = null): AppUser
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
