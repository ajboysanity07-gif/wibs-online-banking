<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;

function grepalifeBeneficiaryChecklistPersistDataEntry(
    LoanRequest $loanRequest,
    string $sectionKey,
    string $fieldKey,
    string $valueType,
    mixed $value,
): void {
    LoanRequestDataEntry::query()->updateOrCreate(
        [
            'loan_request_id' => $loanRequest->id,
            'field_key' => $fieldKey,
        ],
        [
            'section_key' => $sectionKey,
            'owner_type' => 'member',
            'value_type' => $valueType,
            'value_json' => ['value' => $value],
            'is_sensitive' => false,
            'confirmed_by_member' => false,
            'confirmed_by_member_at' => null,
        ],
    );
}

function grepalifeBeneficiaryChecklistLoanRequest(): LoanRequest
{
    $user = User::factory()->create();
    MemberApplicationProfile::factory()->create(['user_id' => $user->user_id]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
            'recommended_amount' => 25000,
            'recommended_term' => 12,
            'recommended_interest_rate' => 0.36,
            'recommended_payment_frequency' => '15th & 30th',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Sample',
            'last_name' => 'Member',
        ]);

    grepalifeBeneficiaryChecklistPersistDataEntry($loanRequest, 'health', 'health_smoking_status', 'string', 'none');
    grepalifeBeneficiaryChecklistPersistDataEntry($loanRequest, 'health', 'health_hypertension', 'boolean', false);
    grepalifeBeneficiaryChecklistPersistDataEntry($loanRequest, 'health', 'gl_health_q02e_diabetes', 'boolean', false);
    grepalifeBeneficiaryChecklistPersistDataEntry($loanRequest, 'health', 'health_recent_hospitalization', 'boolean', false);

    return $loanRequest;
}

function grepalifeBeneficiaryChecklistEntry(LoanRequest $loanRequest): array
{
    $checklist = app(LoanRequestDocumentWorkflowService::class)->serializeChecklist($loanRequest);

    return collect($checklist)->firstWhere('key', LoanRequestDocumentKey::Grepalife->value);
}

test('grepalife still requires the legacy primary beneficiary fields when no dependent is flagged', function (): void {
    $loanRequest = grepalifeBeneficiaryChecklistLoanRequest();

    $entry = grepalifeBeneficiaryChecklistEntry($loanRequest);

    expect($entry['blockers'])->toContain('Primary beneficiary name is required.')
        ->and($entry['blockers'])->toContain('Primary beneficiary relationship is required.')
        ->and($entry['blockers'])->toContain('Primary beneficiary birthdate is required.');
});

test('a dependent flagged as insurance beneficiary satisfies the primary beneficiary requirement', function (): void {
    $loanRequest = grepalifeBeneficiaryChecklistLoanRequest();

    grepalifeBeneficiaryChecklistPersistDataEntry($loanRequest, 'dependents', 'dependent_child_1_is_beneficiary', 'boolean', true);
    grepalifeBeneficiaryChecklistPersistDataEntry($loanRequest, 'dependents', 'dependent_child_1_name', 'string', 'Junior Member');
    grepalifeBeneficiaryChecklistPersistDataEntry($loanRequest, 'dependents', 'dependent_child_1_birthdate', 'date', '2015-01-01');

    $entry = grepalifeBeneficiaryChecklistEntry($loanRequest);

    expect($entry['blockers'])->not->toContain('Primary beneficiary name is required.')
        ->and($entry['blockers'])->not->toContain('Primary beneficiary relationship is required.')
        ->and($entry['blockers'])->not->toContain('Primary beneficiary birthdate is required.');
});
