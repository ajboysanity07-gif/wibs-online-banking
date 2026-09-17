<?php

use App\LoanInstitutionalEmployerCategory;
use App\LoanPaymentOption;
use App\LoanRequestDocumentKey;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * @param  list<string>  $roles
 */
function checklistPreviewActor(array $roles, ?string $acctno = null): AppUser
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

/**
 * Locks in the live-preview flow behind the Employer Classification dropdown:
 * selecting a category should immediately reflect which documents become
 * applicable (Authority to Deduct here), without persisting anything, ahead
 * of an actual processing-details save.
 */
test('checklist preview reflects an unsaved institutional_employer_category override without persisting it', function (): void {
    $processor = checklistPreviewActor([Role::LOAN_PROCESSOR]);
    $member = checklistPreviewActor([Role::MEMBER], '950101');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['institutional_employer_category' => null]);

    LoanRequestDataEntry::query()->create([
        'loan_request_id' => $loanRequest->id,
        'field_key' => 'payment_option',
        'section_key' => 'processing',
        'owner_type' => 'staff',
        'value_type' => 'string',
        'value_json' => ['value' => LoanPaymentOption::SalaryDeduction->value],
        'is_sensitive' => false,
        'confirmed_by_member' => false,
        'confirmed_by_member_at' => null,
    ]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.checklist-preview', $loanRequest),
            ['institutional_employer_category' => LoanInstitutionalEmployerCategory::Blgu->value],
        )
        ->assertOk();

    $authorityToDeduct = collect($response->json('data'))
        ->firstWhere('key', LoanRequestDocumentKey::AuthorityToDeduct->value);

    expect($authorityToDeduct['is_applicable'])->toBeTrue()
        ->and($authorityToDeduct['unavailable_reason'])->toBeNull();

    $applicant = $loanRequest->fresh()->people
        ->firstWhere('role', LoanRequestPersonRole::Applicant);

    expect($applicant->institutional_employer_category)->toBeNull();
});

test('checklist preview marks authority to deduct not applicable when no category is selected', function (): void {
    $processor = checklistPreviewActor([Role::LOAN_PROCESSOR]);
    $member = checklistPreviewActor([Role::MEMBER], '950102');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'typecode' => 'LN-050',
        'requested_amount' => '25000.00',
        'requested_term' => 12,
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['institutional_employer_category' => LoanInstitutionalEmployerCategory::Blgu]);

    LoanRequestDataEntry::query()->create([
        'loan_request_id' => $loanRequest->id,
        'field_key' => 'payment_option',
        'section_key' => 'processing',
        'owner_type' => 'staff',
        'value_type' => 'string',
        'value_json' => ['value' => LoanPaymentOption::SalaryDeduction->value],
        'is_sensitive' => false,
        'confirmed_by_member' => false,
        'confirmed_by_member_at' => null,
    ]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.checklist-preview', $loanRequest),
            ['institutional_employer_category' => null],
        )
        ->assertOk();

    $authorityToDeduct = collect($response->json('data'))
        ->firstWhere('key', LoanRequestDocumentKey::AuthorityToDeduct->value);

    expect($authorityToDeduct['is_applicable'])->toBeFalse();
});
