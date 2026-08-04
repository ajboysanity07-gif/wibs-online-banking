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
 * GLAPI sensitive fields (PEP/cycle status, dependents) previously had no way
 * to be requested back from the member: the allow-list only covered
 * beneficiary/health/payout/declaration fields, so a processor could never
 * clear the "member confirmation required" block on the Generali form via
 * this endpoint.
 */
test('request member action accepts GLAPI PEP, cycle, and dependent field keys', function (): void {
    $processor = createMemberActionActor([Role::LOAN_PROCESSOR]);
    $member = createMemberActionActor([Role::MEMBER], '960001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $payload = [
        'action_type' => 'awaiting_member_information',
        'message' => 'Please confirm your PEP status and dependent details.',
        'reason' => 'Needed to clear GLAPI document blockers.',
        'field_keys' => [
            'applicant_pep_status',
            'applicant_cycle_status',
            'dependent_child_1_name',
            'dependent_child_1_birthdate',
        ],
    ];

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.request-member-action', $loanRequest), $payload)
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::AwaitingMemberInformation->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::AwaitingMemberInformation)
        ->and($loanRequest->member_action_fields_json)->toBe($payload['field_keys']);
});

function createMemberActionActor(array $roles, ?string $acctno = null): AppUser
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
