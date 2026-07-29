<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\Role;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDataService;
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

test('loan security agreement and grepalife are always applicable regardless of legacy flags or blank source fields', function (): void {
    $loanRequest = LoanRequest::factory()->make();
    $catalog = app(LoanRequestDocumentCatalog::class);

    $flatValues = [
        'security_required' => false,
        'insurance_required' => false,
        'notarial_venue' => null,
        'loan_security_rate' => null,
    ];

    expect($catalog->isApplicable(LoanRequestDocumentKey::LoanSecurityAgreement, $loanRequest, $flatValues))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::Grepalife, $loanRequest, $flatValues))->toBeTrue();
});

test('undertaking barangay is not applicable when the borrower has no barangay-employment signal', function (): void {
    $loanRequest = LoanRequest::factory()->make();
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::UndertakingBarangay, $loanRequest, []))->toBeFalse();
});

test('undertaking barangay becomes applicable once the member declares barangay information', function (): void {
    $loanRequest = LoanRequest::factory()->create();
    $catalog = app(LoanRequestDocumentCatalog::class);

    applicabilityPersistDataEntries($loanRequest, [
        'barangay_agency_name' => ['string', 'Barangay San Isidro'],
    ]);

    $flatValues = app(LoanRequestDataService::class)->loadFlatValues($loanRequest);

    expect($catalog->isApplicable(LoanRequestDocumentKey::UndertakingBarangay, $loanRequest, $flatValues))->toBeTrue();
});

test('undertaking barangay becomes applicable when the applicant employer name contains "barangay"', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'Barangay Poblacion']);

    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::UndertakingBarangay, $loanRequest->fresh(), []))->toBeTrue();
});

test('undertaking barangay becomes applicable for a government worker whose employer name only contains the "brgy" abbreviation', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employment_type' => 'Government',
            'nature_of_business' => 'Government',
            'employer_business_name' => 'Brgy. San Isidro',
        ]);

    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::UndertakingBarangay, $loanRequest->fresh(), []))->toBeTrue();
});

test('undertaking barangay stays not applicable for the "brgy" abbreviation when employment/nature of business are not both government', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employment_type' => 'Private',
            'nature_of_business' => 'Government',
            'employer_business_name' => 'Brgy. Supplies Trading',
        ]);

    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::UndertakingBarangay, $loanRequest->fresh(), []))->toBeFalse();
});

test('deped salary deduction waiver becomes applicable for Government employment + Education nature of business alone', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employment_type' => 'Government',
            'nature_of_business' => 'Education',
            'employer_business_name' => 'Lianga North Central Elementary School',
        ]);

    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::DepedSalaryDeductionWaiver, $loanRequest->fresh(), []))->toBeTrue();
});

test('deped salary deduction waiver is not applicable for Government employment with a non-Education nature of business and no "deped" keyword', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employment_type' => 'Government',
            'nature_of_business' => 'Government',
            'employer_business_name' => 'Municipality of Lianga',
        ]);

    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::DepedSalaryDeductionWaiver, $loanRequest->fresh(), []))->toBeFalse();
});

test('undertaking barangay is not applicable when required fields are blank and no barangay signal is present', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::UndertakingBarangay);

    expect($entry['is_applicable'])->toBeFalse()
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value);
});

test('undertaking barangay surfaces incomplete when applicable but its required fields are blank', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'barangay_agency_name' => ['string', 'Barangay San Isidro'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::UndertakingBarangay);

    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value)
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::Incomplete->value);
});

test('undertaking barangay becomes ready to generate once applicable and its required fields are filled', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'barangay_agency_name' => ['string', 'Barangay San Isidro'],
        'guaranteed_net_take_home_pay' => ['number', 15000],
    ]);

    $barangay = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::UndertakingBarangay);

    expect($barangay['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value);
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

test('term_days uses a x25 multiplier matching the source workbook (12 month term = 300 days, not 360)', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 24000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 0,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 24000,
        'approved_term' => 12,
        'approved_interest_rate' => 0,
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['term_days'])->toBe(300);
});

test('savings_rate drives the amortization savings figure independently from loan_security_rate', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 24000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 0,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 24000,
        'approved_term' => 12,
        'approved_interest_rate' => 0,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 0],
        'insurance_rate' => ['number', 0],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.02],
        'savings_rate' => ['number', 0.05],
        'documentary_stamp_rate' => ['number', 0],
        'notarial_fee' => ['number', 0],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    // Principal amortization = 24000/12 = 2000.00 per payment.
    // The one-time Loan Security deduction still uses loan_security_rate (0.02):
    // 24000*0.02 = 480.00.
    // The per-payment amortization savings line now uses the independent
    // savings_rate (0.05), not loan_security_rate: 2000.00*0.05 = 100.00
    // (it would be 40.00 if it were still reusing loan_security_rate).
    expect($documentData['loan']['loan_security_rate_raw'])->toBe(0.02)
        ->and($documentData['loan']['savings_rate_raw'])->toBe(0.05)
        ->and($documentData['loan']['loan_security_amount_raw'])->toBe(480.0)
        ->and($documentData['loan']['amortization_principal_raw'])->toBe(2000.0)
        ->and($documentData['loan']['amortization_loan_security_raw'])->toBe(100.0);
});

test('savings_rate defaults to 0.0 when not set, mirroring loan_security_rate\'s default-handling pattern', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 24000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 0,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 24000,
        'approved_term' => 12,
        'approved_interest_rate' => 0,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 0],
        'insurance_rate' => ['number', 0],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.02],
        'documentary_stamp_rate' => ['number', 0],
        'notarial_fee' => ['number', 0],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['savings_rate_raw'])->toBe(0.0)
        ->and($documentData['loan']['amortization_loan_security_raw'])->toBe(0.0);
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

test('witness_two_name is no longer required for loan_information, plan_of_payment, or promissory_note', function (): void {
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
    ]);

    foreach ([
        LoanRequestDocumentKey::LoanInformation,
        LoanRequestDocumentKey::PlanOfPayment,
        LoanRequestDocumentKey::PromissoryNote,
    ] as $documentKey) {
        $entry = applicabilityChecklistEntry($loanRequest, $documentKey);

        expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
            ->and($entry['blockers'])->not->toContain('Witness two name is required.');
    }
});

test('witness_one_name stays required for loan_information, plan_of_payment, and promissory_note', function (): void {
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
    ]);

    foreach ([
        LoanRequestDocumentKey::LoanInformation,
        LoanRequestDocumentKey::PlanOfPayment,
        LoanRequestDocumentKey::PromissoryNote,
    ] as $documentKey) {
        $entry = applicabilityChecklistEntry($loanRequest, $documentKey);

        expect($entry['blockers'])->toContain('Witness one name is required.');
    }
});

/**
 * Owner decision: requests submitted before 2026-07-28 predate the
 * beneficiary/health data model, have no staff/admin UI to backfill it, and
 * so can never satisfy GREPALIFE etc. -- only the Application Form remains
 * generatable for them, and they must not be blocked from recommend-approval
 * by document-readiness gates they can never clear.
 */
test('every document except application_form is not applicable for a loan request submitted before the legacy cutoff', function (): void {
    $loanRequest = LoanRequest::factory()->make([
        'submitted_at' => '2026-07-20 00:00:00',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::ApplicationForm, $loanRequest, []))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::Grepalife, $loanRequest, []))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::LoanSecurityAgreement, $loanRequest, []))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::LoanInformation, $loanRequest, []))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::PlanOfPayment, $loanRequest, []))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::PromissoryNote, $loanRequest, []))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::DisclosureStatement, $loanRequest, []))->toBeFalse();
});

test('a request submitted exactly on the legacy cutoff date is not treated as legacy', function (): void {
    $loanRequest = LoanRequest::factory()->make([
        'submitted_at' => '2026-07-28 00:00:00',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::Grepalife, $loanRequest, []))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::LoanSecurityAgreement, $loanRequest, []))->toBeTrue();
});

test('legacy cutoff falls back to created_at when submitted_at is null', function (): void {
    $loanRequest = LoanRequest::factory()->make([
        'submitted_at' => null,
        'created_at' => '2026-07-01 00:00:00',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::Grepalife, $loanRequest, []))->toBeFalse();
});

test('legacy pre-cutoff checklist shows grepalife and loan security agreement as not applicable', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => '2026-06-01 00:00:00',
    ]);

    $grepalife = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::Grepalife);
    $loanSecurity = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::LoanSecurityAgreement);
    $applicationForm = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::ApplicationForm);

    expect($grepalife['is_applicable'])->toBeFalse()
        ->and($grepalife['status'])->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value)
        ->and($loanSecurity['is_applicable'])->toBeFalse()
        ->and($loanSecurity['status'])->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value)
        ->and($applicationForm['is_applicable'])->toBeTrue();
});

test('a legacy pre-cutoff request can be recommended for approval without any beneficiary or health data', function (): void {
    Role::ensureWorkflowDefaults();

    $processor = AppUser::factory()->create();
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => '2026-06-01 00:00:00',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    // No GREPALIFE beneficiary/health data, no charges & fees data -- this
    // legacy request can never collect it, so it must not block the workflow.
    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => 'Recorded verified processing terms for a legacy record.',
            'information_source' => 'Verified staff review',
            'loan_request' => [],
            'recommended_amount' => 24000,
            'recommended_term' => 10,
            'recommended_interest_rate' => 1.5,
            'recommended_payment_frequency' => 'Monthly',
            'recommendation_remarks' => 'Legacy record, application form only.',
        ])
        ->assertOk();

    // Application Form is the only applicable document for a legacy record --
    // recommend-approval still requires it to actually be generated, not just
    // data-ready, so the processor generates it via the normal checklist action.
    $this
        ->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.documents.generate', $loanRequest), [])
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Forwarding to loan manager.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);
});

test('a non-legacy request submitted on or after the cutoff is still blocked from recommend-approval without GREPALIFE data', function (): void {
    Role::ensureWorkflowDefaults();

    $processor = AppUser::factory()->create();
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => '2026-07-28 00:00:00',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => 'Recorded verified processing terms.',
            'information_source' => 'Verified staff review',
            'loan_request' => [],
            'recommended_amount' => 24000,
            'recommended_term' => 10,
            'recommended_interest_rate' => 1.5,
            'recommended_payment_frequency' => 'Monthly',
            'recommendation_remarks' => 'Recommend approval after full review.',
        ])
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Forwarding to loan manager.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['documents']);
});
