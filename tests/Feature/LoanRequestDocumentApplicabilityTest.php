<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDocumentCatalog;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;

/**
 * @param  array<string, array{0:string,1:mixed}>  $fields
 */
function applicabilityPersistDataEntries(LoanRequest $loanRequest, array $fields): void
{
    foreach ($fields as $fieldKey => [$valueType, $value]) {
        LoanRequestDataEntry::query()->updateOrCreate(
            [
                'loan_request_id' => $loanRequest->id,
                'field_key' => $fieldKey,
            ],
            [
                'section_key' => 'processing',
                'owner_type' => 'staff',
                'value_type' => $valueType,
                'value_json' => ['value' => $value],
                'is_sensitive' => false,
                'confirmed_by_member' => false,
                'confirmed_by_member_at' => null,
            ],
        );
    }
}

function applicabilityChecklistEntry(LoanRequest $loanRequest, LoanRequestDocumentKey $documentKey): array
{
    $checklist = app(LoanRequestDocumentWorkflowService::class)->serializeChecklist($loanRequest);

    return collect($checklist)->firstWhere('key', $documentKey->value);
}

test('authorization, barangay, loan security agreement, and grepalife are always applicable regardless of legacy flags or blank source fields', function (): void {
    $loanRequest = LoanRequest::factory()->make();
    $catalog = app(LoanRequestDocumentCatalog::class);

    $flatValues = [
        'authorization_required' => false,
        'barangay_required' => false,
        'security_required' => false,
        'insurance_required' => false,
        'payout_bank_name' => null,
        'payout_account_number' => null,
        'barangay_name' => null,
        'barangay_clearance_reference' => null,
        'barangay_locality' => null,
        'notarial_venue' => null,
        'loan_security_rate' => null,
    ];

    expect($catalog->isApplicable(LoanRequestDocumentKey::Authorization, $loanRequest, $flatValues))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::UndertakingBarangay, $loanRequest, $flatValues))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::LoanSecurityAgreement, $loanRequest, $flatValues))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::Grepalife, $loanRequest, $flatValues))->toBeTrue();
});

test('authorization surfaces a non-ready status when its required fields are blank', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::Authorization);

    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value)
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation->value);
});

test('undertaking barangay surfaces incomplete when its required fields are blank', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::UndertakingBarangay);

    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value)
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::Incomplete->value);
});

test('authorization and undertaking barangay become ready to generate once their required fields are filled', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'payout_bank_name' => ['string', 'WIBS Cooperative Bank'],
        'payout_account_number' => ['string', '1234567890'],
        'barangay_name' => ['string', 'Barangay San Isidro'],
        'barangay_clearance_reference' => ['string', 'BCL-2026-030'],
    ]);

    $authorization = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::Authorization);
    $barangay = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::UndertakingBarangay);

    expect($authorization['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
        ->and($barangay['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value);
});

test('loan_security_rate stays required and generates blockers unconditionally, with no flag in the fixture at all', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 1.5,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 1.25],
        'insurance_rate' => ['number', 0.75],
        'insurance_term' => ['number', 12],
        'documentary_stamp_rate' => ['number', 0.2],
        'notarial_fee' => ['number', 250],
        'penalty_rate_per_month' => ['number', 3],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::LoanInformation);

    expect($entry['blockers'])->toContain('Loan security rate must be numeric.');
});

test('a legitimately-set loan_security_rate of exactly 0 is accepted as valid, not a policy-driven zero', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 1.5,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 1.25],
        'insurance_rate' => ['number', 0.75],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0],
        'documentary_stamp_rate' => ['number', 0.2],
        'notarial_fee' => ['number', 250],
        'penalty_rate_per_month' => ['number', 3],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    foreach ([
        LoanRequestDocumentKey::LoanInformation,
        LoanRequestDocumentKey::PlanOfPayment,
        LoanRequestDocumentKey::DisclosureStatement,
        LoanRequestDocumentKey::PromissoryNote,
    ] as $documentKey) {
        $entry = applicabilityChecklistEntry($loanRequest, $documentKey);

        expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
            ->and($entry['blockers'])->not->toContain('Loan security rate must be numeric.');
    }

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['loan_security_rate_raw'])->toBe(0.0)
        ->and($documentData['loan']['loan_security_amount_raw'])->toBe(0.0);
});

test('a nonzero loan_security_rate passes through unmodified with no flag present in the fixture at all', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 1.5,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 1.25],
        'insurance_rate' => ['number', 0.75],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 2.5],
        'documentary_stamp_rate' => ['number', 0.2],
        'notarial_fee' => ['number', 250],
        'penalty_rate_per_month' => ['number', 3],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['loan_security_rate_raw'])->toBe(2.5)
        ->and($documentData['loan']['loan_security_amount_raw'])->toBe(62500.0);
});

test('grepalife is applicable regardless of insurance_required value', function (): void {
    $loanRequest = LoanRequest::factory()->make();
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::Grepalife,
        $loanRequest,
        ['insurance_required' => false],
    ))->toBeTrue()
        ->and($catalog->isApplicable(
            LoanRequestDocumentKey::Grepalife,
            $loanRequest,
            ['insurance_required' => true],
        ))->toBeTrue()
        ->and($catalog->isApplicable(
            LoanRequestDocumentKey::Grepalife,
            $loanRequest,
            [],
        ))->toBeTrue();
});

test('insurance_required no longer strips insurance_rate/insurance_term from the financial documents\' required fields or blockers', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 1.5,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 1.25],
        'insurance_rate' => ['number', 0.75],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.5],
        'documentary_stamp_rate' => ['number', 0.2],
        'notarial_fee' => ['number', 250],
        'penalty_rate_per_month' => ['number', 3],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    foreach ([
        LoanRequestDocumentKey::LoanInformation,
        LoanRequestDocumentKey::PlanOfPayment,
        LoanRequestDocumentKey::DisclosureStatement,
        LoanRequestDocumentKey::PromissoryNote,
    ] as $documentKey) {
        $entry = applicabilityChecklistEntry($loanRequest, $documentKey);

        expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
            ->and($entry['blockers'])->not->toContain('Insurance rate must be numeric.')
            ->and($entry['blockers'])->not->toContain('Insurance term must be greater than zero.');
    }
});

test('blank insurance_rate/insurance_term produce financial blockers unconditionally, with no flag able to suppress them', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 1.5,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 1.25],
        'loan_security_rate' => ['number', 0.5],
        'documentary_stamp_rate' => ['number', 0.2],
        'notarial_fee' => ['number', 250],
        'penalty_rate_per_month' => ['number', 3],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::LoanInformation);

    expect($entry['blockers'])->toContain('Insurance rate must be numeric.')
        ->and($entry['blockers'])->toContain('Insurance term must be greater than zero.');
});

test('insurance_term requires a positive integer (isPositiveIntegerValue) while loan_security_rate only requires a non-negative number (isNumericValue)', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 1.5,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 1.25],
        'insurance_rate' => ['number', 0.75],
        'insurance_term' => ['number', 0],
        'loan_security_rate' => ['number', 0],
        'documentary_stamp_rate' => ['number', 0.2],
        'notarial_fee' => ['number', 250],
        'penalty_rate_per_month' => ['number', 3],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::LoanInformation);

    expect($entry['blockers'])->toContain('Insurance term must be greater than zero.')
        ->and($entry['blockers'])->not->toContain('Loan security rate must be numeric.');
});
