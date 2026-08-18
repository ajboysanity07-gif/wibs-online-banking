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

test('undertaking barangay becomes applicable once staff enter the barangay agency details for a barangay-employer applicant', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'Barangay San Isidro']);

    $catalog = app(LoanRequestDocumentCatalog::class);

    applicabilityPersistDataEntries($loanRequest, [
        'barangay_agency_name' => ['string', 'Barangay San Isidro'],
    ]);

    $flatValues = app(LoanRequestDataService::class)->loadFlatValues($loanRequest);

    expect($catalog->isApplicable(LoanRequestDocumentKey::UndertakingBarangay, $loanRequest->fresh(), $flatValues))->toBeTrue();
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
    $flatValues = ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value];

    expect($catalog->isApplicable(LoanRequestDocumentKey::DepedSalaryDeductionWaiver, $loanRequest->fresh(), $flatValues))->toBeTrue();
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

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'Barangay San Isidro']);

    applicabilityPersistDataEntries($loanRequest, [
        'barangay_agency_name' => ['string', 'Barangay San Isidro'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest->fresh(), LoanRequestDocumentKey::UndertakingBarangay);

    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
        ->and($entry['status'])->not->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value)
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::Incomplete->value);
});

test('undertaking barangay becomes ready to generate once applicable and its required fields are filled', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'Barangay San Isidro']);

    applicabilityPersistDataEntries($loanRequest, [
        'barangay_agency_name' => ['string', 'Barangay San Isidro'],
        'guaranteed_net_take_home_pay' => ['number', 15000],
    ]);

    $barangay = applicabilityChecklistEntry($loanRequest->fresh(), LoanRequestDocumentKey::UndertakingBarangay);

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

test('savings_rate defaults to the 2% institutional constant when not set, matching the Excel-derived defaults for loan_security_rate/documentary_stamp_rate/notarial_fee', function (): void {
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

    // Principal amortization = 24000/12 = 2000.00 per payment.
    // Savings amortization line = 2000.00 * 0.02 (institutional default) = 40.00.
    expect($documentData['loan']['savings_rate_raw'])->toBe(0.02)
        ->and($documentData['loan']['amortization_loan_security_raw'])->toBe(40.0);
});

test('other_charges_amount flows into non_finance_charge_total_raw, deductions_total_raw, and net_proceeds_raw', function (): void {
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
        'loan_security_rate' => ['number', 0],
        'savings_rate' => ['number', 0],
        'documentary_stamp_rate' => ['number', 0],
        'notarial_fee' => ['number', 0],
        'other_charges_amount' => ['number', 350],
        'other_charges_description' => ['string', 'Late renewal fee'],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    // With every other charge zeroed out, non-finance total is just the 350 "other" charge,
    // which is also the full deductions total, so net proceeds = 24000 - 350 = 23650.
    expect($documentData['loan']['other_charges_amount_raw'])->toBe(350.0)
        ->and($documentData['loan']['other_charges_description'])->toBe('Late renewal fee')
        ->and($documentData['loan']['non_finance_charge_total_raw'])->toBe(350.0)
        ->and($documentData['loan']['deductions_total_raw'])->toBe(350.0)
        ->and($documentData['loan']['net_proceeds_raw'])->toBe(23650.0);
});

test('other_charges_amount left unset does not break document generation and contributes nothing to totals', function (): void {
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
        'loan_security_rate' => ['number', 0],
        'savings_rate' => ['number', 0],
        'documentary_stamp_rate' => ['number', 0],
        'notarial_fee' => ['number', 0],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['other_charges_amount_raw'])->toBeNull()
        ->and($documentData['loan']['other_charges_description'])->toBeNull()
        ->and($documentData['loan']['non_finance_charge_total_raw'])->toBe(0.0)
        ->and($documentData['loan']['net_proceeds_raw'])->toBe(24000.0);
});

test('net proceeds matches the Disclosure Statement workbook: interest and service charge are both Finance Charges', function (): void {
    // Exact scenario from the source workbook (PLAN OF PAYMENT,DISC.PROMIS.xlsx,
    // "Disclosure Statement" sheet -- the R.A. 3765 Truth In Lending Act
    // form, not the "Loan Information" sheet): 200,000 loan, 36% p.a.
    // interest, 5% service charge, insurance ₱1.00/₱1,000/month x 12, 2%
    // loan security, 0.75% doc stamps, ₱100 notarial fee.
    //
    // The Disclosure Statement sheet lists "a. Interest" as Finance Charge
    // item (row 14) alongside "e. ... Service Charge" (row 23), summed into
    // "TOTAL FINANCE CHARGES" (row 25). "NET PROCEEDS OF LOAN (A LESS D)"
    // (row 35) is loan amount minus (B) Finance Charges + (C) Non-Finance
    // Charges -- so interest genuinely reduces net proceeds.
    //
    // Workbook figures:
    //   (B) Total Finance Charges     = 82,000 (72,000 interest + 10,000 service charge)
    //   (C) Total Non-Finance Charges =  8,000 (2,400 + 4,000 + 1,500 + 100)
    //   (D) Total Deductions (B + C)  = 90,000
    //   (E) Net Proceeds (A - D)      = 110,000
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 200000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 0.36,
        'recommended_payment_frequency' => 'Monthly',
        'approved_amount' => 200000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 0.05],
        'insurance_rate' => ['number', 1.0],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.02],
        'savings_rate' => ['number', 0.02],
        'documentary_stamp_rate' => ['number', 0.0075],
        'notarial_fee' => ['number', 100],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    // Interest 200000*0.36/12*12 = 72,000. interest_not_deducted_raw keeps
    // its name (it feeds the "Loan Information" sheet's own, independent
    // "Interest Not Deducted" cell) but is no longer excluded from net
    // proceeds -- it's part of the Finance Charges total below.
    expect($documentData['loan']['interest_not_deducted_raw'])->toBe(72000.0)
        ->and($documentData['loan']['service_charge_amount_raw'])->toBe(10000.0)
        ->and($documentData['loan']['insurance_premium_raw'])->toBe(2400.0)
        ->and($documentData['loan']['loan_security_amount_raw'])->toBe(4000.0)
        ->and($documentData['loan']['documentary_stamp_amount_raw'])->toBe(1500.0)
        ->and($documentData['loan']['notarial_fee_raw'])->toBe(100.0)
        ->and($documentData['loan']['finance_charge_total_raw'])->toBe(82000.0)
        ->and($documentData['loan']['non_finance_charge_total_raw'])->toBe(8000.0)
        ->and($documentData['loan']['deductions_total_raw'])->toBe(90000.0)
        ->and($documentData['loan']['net_proceeds_raw'])->toBe(110000.0);
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
        'submitted_at' => now(),
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

test('authority to deduct is applicable with 2 recommended officers for a BLGU employer', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'Barangay San Isidro']);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value];

    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeTrue();

    $guidance = $catalog->authorityToDeductGuidance($loanRequest, $flatValues);
    expect($guidance['category'])->toBe('blgu')
        ->and($guidance['recommended_officers'])->toBe(2);
});

test('authority to deduct is applicable with 2 recommended officers for an LGU employer', function (): void {
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
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value];

    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeTrue();

    $guidance = $catalog->authorityToDeductGuidance($loanRequest, $flatValues);
    expect($guidance['category'])->toBe('lgu')
        ->and($guidance['recommended_officers'])->toBe(2);
});

test('authority to deduct is applicable with 1 recommended officer for an MRDINC employer', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'MRDINC Head Office']);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value];

    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeTrue();

    $guidance = $catalog->authorityToDeductGuidance($loanRequest, $flatValues);
    expect($guidance['category'])->toBe('mrdinc')
        ->and($guidance['recommended_officers'])->toBe(1);
});

test('authority to deduct is applicable with 1 recommended officer for an LDH (healthcare) employer', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'nature_of_business' => 'Healthcare',
            'employer_business_name' => 'Lianga District Hospital',
        ]);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value];

    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeTrue();

    $guidance = $catalog->authorityToDeductGuidance($loanRequest, $flatValues);
    expect($guidance['category'])->toBe('ldh')
        ->and($guidance['recommended_officers'])->toBe(1);
});

test('authority to deduct is not applicable for a teacher, a pensioner, a self-employed applicant, or an ordinary private employer', function (): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    $teacher = LoanRequest::factory()->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($teacher)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employment_type' => 'Government',
            'nature_of_business' => 'Education',
        ]);
    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $teacher->fresh(), []))->toBeFalse();

    $pensioner = LoanRequest::factory()->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($pensioner)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employment_type' => 'Pensioner']);
    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $pensioner->fresh(), []))->toBeFalse();

    $selfEmployed = LoanRequest::factory()->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($selfEmployed)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employment_type' => 'Self Employed']);
    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $selfEmployed->fresh(), []))->toBeFalse();

    $ordinaryPrivate = LoanRequest::factory()->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($ordinaryPrivate)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'Some Private Company']);
    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $ordinaryPrivate->fresh(), []))->toBeFalse();
});

test('authority to deduct is not applicable for an institutional-category employer whose payment option is not Salary Deduction', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'MRDINC Head Office']);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();

    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, ['payment_option' => \App\LoanPaymentOption::Check->value]))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, []))->toBeFalse();

    $guidance = $catalog->authorityToDeductGuidance($loanRequest, ['payment_option' => \App\LoanPaymentOption::Check->value]);
    expect($guidance['applicable'])->toBeFalse()
        ->and($guidance['category'])->toBe('mrdinc');
});

test('deped waiver is applicable and authority to deduct is not for a teacher', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employment_type' => 'Government',
            'nature_of_business' => 'Education',
        ]);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value];

    $waiverApplicability = $catalog->waiverApplicability($loanRequest, $flatValues);

    expect($waiverApplicability['deped']['applicable'])->toBeTrue()
        ->and($waiverApplicability['pension']['applicable'])->toBeFalse()
        ->and($waiverApplicability['atm']['applicable'])->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeFalse();
});

test('pension waiver is applicable and authority to deduct is not for a pensioner', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employment_type' => 'Pensioner']);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value];

    $waiverApplicability = $catalog->waiverApplicability($loanRequest, $flatValues);

    expect($waiverApplicability['pension']['applicable'])->toBeTrue()
        ->and($waiverApplicability['deped']['applicable'])->toBeFalse()
        ->and($waiverApplicability['atm']['applicable'])->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeFalse();
});

test('atm salary deduction waiver is applicable for a non-pensioner, non-institutional employee who chose ATM payout', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employment_type' => 'Private',
            'employer_business_name' => 'Some Private Company',
        ]);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value];

    $waiverApplicability = $catalog->waiverApplicability($loanRequest, $flatValues);

    expect($waiverApplicability['atm']['applicable'])->toBeTrue()
        ->and($waiverApplicability['deped']['applicable'])->toBeFalse()
        ->and($waiverApplicability['pension']['applicable'])->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::AtmSalaryDeductionWaiver, $loanRequest, $flatValues))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeFalse();
});

test('atm salary deduction waiver is not applicable for a BLGU employee whose payment option is ATM Deduction (they belong to an institutional category, not the uncategorized ATM bucket)', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'Barangay San Isidro']);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value];

    // Neither document applies here: the ATM waiver excludes anyone with a
    // resolved institutional category, and Authority to Deduct requires
    // payment_option to be Salary Deduction specifically.
    expect($catalog->isApplicable(LoanRequestDocumentKey::AtmSalaryDeductionWaiver, $loanRequest, $flatValues))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $flatValues))->toBeFalse();

    // With Salary Deduction instead, the same BLGU employer becomes Authority
    // to Deduct applicable, confirming the category detection itself is intact.
    $salaryDeductionFlatValues = ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value];
    expect($catalog->isApplicable(LoanRequestDocumentKey::AuthorityToDeduct, $loanRequest, $salaryDeductionFlatValues))->toBeTrue();
});

test('atm salary deduction waiver is not applicable for a self-employed applicant even with ATM payout', function (): void {
    $loanRequest = LoanRequest::factory()->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employment_type' => 'Self Employed']);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $loanRequest = $loanRequest->fresh();
    $flatValues = ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value];

    expect($catalog->isApplicable(LoanRequestDocumentKey::AtmSalaryDeductionWaiver, $loanRequest, $flatValues))->toBeFalse();
});

test('affidavit of undertaking is applicable only when payment option is ATM Deduction', function (): void {
    $loanRequest = LoanRequest::factory()->make();
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::AffidavitUndertaking,
        $loanRequest,
        ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value],
    ))->toBeTrue()
        ->and($catalog->isApplicable(
            LoanRequestDocumentKey::AffidavitUndertaking,
            $loanRequest,
            ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value],
        ))->toBeFalse()
        ->and($catalog->isApplicable(
            LoanRequestDocumentKey::AffidavitUndertaking,
            $loanRequest,
            [],
        ))->toBeFalse();
});

test('affidavit of undertaking checklist entry surfaces an unavailable_reason note when payment option is not ATM', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'payment_option' => ['string', \App\LoanPaymentOption::SalaryDeduction->value],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::AffidavitUndertaking);

    expect($entry['is_applicable'])->toBeFalse()
        ->and($entry['unavailable_reason'])->toBe('Only available for members using ATM as their payment option.');
});

test('affidavit of undertaking checklist entry has no unavailable_reason once payment option is ATM', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'payment_option' => ['string', \App\LoanPaymentOption::AtmDeduction->value],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::AffidavitUndertaking);

    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['unavailable_reason'])->toBeNull();
});

test('when officers are marked unknown, the generated authority to deduct document data has blank officer fields', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => 'Monthly',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['employer_business_name' => 'MRDINC Head Office']);

    applicabilityPersistDataEntries($loanRequest, [
        'authority_to_deduct_institution_name' => ['string', 'MRDINC Head Office'],
        'authority_to_deduct_officer_1_name' => ['string', 'Should Be Cleared'],
        'authority_to_deduct_officer_1_title' => ['string', 'Should Be Cleared'],
        'authority_to_deduct_officers_unknown' => ['boolean', true],
    ]);

    $workflowService = app(LoanRequestDocumentWorkflowService::class);
    $documentData = (new ReflectionMethod($workflowService, 'documentDataForGeneration'))
        ->invoke($workflowService, $loanRequest->fresh());

    expect($documentData['authority_to_deduct']['institution_name'])->toBe('MRDINC Head Office')
        ->and($documentData['authority_to_deduct']['officer_1_name'])->toBeNull()
        ->and($documentData['authority_to_deduct']['officer_1_title'])->toBeNull();
});

test('generali application form is incomplete while applicant PEP status and cycle status are blank', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::GeneraliApplicationForm);

    // Both fields are sensitive/member-owned, so a blank required value
    // routes to AwaitingMemberConfirmation rather than the generic
    // Incomplete status -- same as grepalife's beneficiary fields.
    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation->value);
});

test('generali application form becomes ready once applicant PEP status and cycle status are answered', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'applicant_pep_status' => ['boolean', false],
        'applicant_cycle_status' => ['string', 'New'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::GeneraliApplicationForm);

    expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value);
});

test('generali application form stays incomplete when PEP is true but its details are blank', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'applicant_pep_status' => ['boolean', true],
        'applicant_cycle_status' => ['string', 'New'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::GeneraliApplicationForm);

    expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation->value);
});

test('generali application form stays incomplete when cycle status is Old but cycle number is blank', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'applicant_pep_status' => ['boolean', false],
        'applicant_cycle_status' => ['string', 'Old'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::GeneraliApplicationForm);

    expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation->value);
});

test('generali application form is ready when PEP true and Old cycle status both have their conditional details filled', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'applicant_pep_status' => ['boolean', true],
        'applicant_pep_status_details' => ['string', 'Barangay Councilor, since 2020'],
        'applicant_cycle_status' => ['string', 'Old'],
        'applicant_cycle_number' => ['number', 3],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::GeneraliApplicationForm);

    expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value);
});

test('generali and generali application form are not applicable for a 1-month Lumpsum', function (): void {
    $loanRequest = LoanRequest::factory()->make([
        'recommended_payment_frequency' => 'Lump sum',
        'recommended_term' => 1,
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::Generali, $loanRequest, []))->toBeFalse()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::GeneraliApplicationForm, $loanRequest, []))->toBeFalse();
});

test('generali and generali application form remain applicable for a 2-month-or-longer Lumpsum', function (): void {
    $loanRequest = LoanRequest::factory()->make([
        'recommended_payment_frequency' => 'Lump sum',
        'recommended_term' => 2,
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::Generali, $loanRequest, []))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::GeneraliApplicationForm, $loanRequest, []))->toBeTrue();
});

test('generali and generali application form remain applicable for non-Lumpsum frequencies', function (): void {
    $loanRequest = LoanRequest::factory()->make([
        'recommended_payment_frequency' => 'Monthly',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(LoanRequestDocumentKey::Generali, $loanRequest, []))->toBeTrue()
        ->and($catalog->isApplicable(LoanRequestDocumentKey::GeneraliApplicationForm, $loanRequest, []))->toBeTrue();
});

test('a Lumpsum recommended payment frequency does not block promissory note generation', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 24000,
        'recommended_term' => 1,
        'recommended_interest_rate' => 0,
        'recommended_payment_frequency' => 'Lump sum',
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 0],
        'insurance_rate' => ['number', 1.0],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.02],
        'documentary_stamp_rate' => ['number', 0.0075],
        'notarial_fee' => ['number', 0],
        'penalty_rate_per_month' => ['number', 0.05],
        'witness_one_name' => ['string', 'Witness One'],
    ]);

    $entry = applicabilityChecklistEntry($loanRequest, LoanRequestDocumentKey::PromissoryNote);

    expect($entry['blockers'])->not->toContain('Recommended payment frequency is not recognized by the official templates.');
});

test('a 1-month Lumpsum zeroes loan security, savings, and insurance, and derives the amortization/maturity month count from the approved term', function (): void {
    // recommended/approved term is the sole source of truth for how many
    // months a Lumpsum payment covers -- amortization count and maturity
    // date must agree, both driven by the same term (1 month here).
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 24000,
        'recommended_term' => 1,
        'recommended_interest_rate' => 0,
        'recommended_payment_frequency' => 'Lump sum',
        'approved_amount' => 24000,
        'approved_term' => 1,
        'approved_interest_rate' => 0,
        'approved_at' => now(),
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 0],
        'insurance_rate' => ['number', 1.0],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.02],
        'savings_rate' => ['number', 0.02],
        'documentary_stamp_rate' => ['number', 0],
        'notarial_fee' => ['number', 0],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['loan_security_rate_raw'])->toBe(0.0)
        ->and($documentData['loan']['savings_rate_raw'])->toBe(0.0)
        ->and($documentData['loan']['loan_security_amount_raw'])->toBe(0.0)
        ->and($documentData['loan']['insurance_rate_raw'])->toBe(0.0)
        ->and($documentData['loan']['insurance_term'])->toBe(0)
        ->and($documentData['loan']['insurance_premium_raw'])->toBe(0.0)
        ->and($documentData['loan']['amortization_count'])->toBe(1)
        ->and($documentData['loan']['payment_mode_workbook'])->toBe('LUMPSUM')
        ->and($documentData['loan']['lumpsum_months'])->toBe(1)
        ->and($documentData['loan']['maturity_date_short'])->toBe(
            $loanRequest->approved_at->copy()->addMonthsNoOverflow(1)->format('m/d/Y'),
        );
});

test('a 2-month-or-longer Lumpsum still charges an insurance premium but keeps loan security/savings zeroed', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'recommended_amount' => 24000,
        'recommended_term' => 2,
        'recommended_interest_rate' => 0,
        'recommended_payment_frequency' => 'Lump sum',
        'approved_amount' => 24000,
        'approved_term' => 2,
        'approved_interest_rate' => 0,
        'approved_at' => now(),
    ]);

    applicabilityPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 0],
        'insurance_rate' => ['number', 1.0],
        'insurance_term' => ['number', 2],
        'loan_security_rate' => ['number', 0.02],
        'savings_rate' => ['number', 0.02],
        'documentary_stamp_rate' => ['number', 0],
        'notarial_fee' => ['number', 0],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['loan_security_rate_raw'])->toBe(0.0)
        ->and($documentData['loan']['savings_rate_raw'])->toBe(0.0)
        ->and($documentData['loan']['loan_security_amount_raw'])->toBe(0.0)
        ->and($documentData['loan']['insurance_rate_raw'])->toBe(1.0)
        ->and($documentData['loan']['insurance_term'])->toBe(2)
        // (24000/1000) * 2 * 1.0 = 48.0
        ->and($documentData['loan']['insurance_premium_raw'])->toBe(48.0)
        ->and($documentData['loan']['amortization_count'])->toBe(2)
        ->and($documentData['loan']['payment_mode_workbook'])->toBe('LUMPSUM')
        ->and($documentData['loan']['lumpsum_months'])->toBe(2);
});

test('documentary stamp defaults to the 0.75% institutional constant when not set', function (): void {
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
        'loan_security_rate' => ['number', 0],
        'savings_rate' => ['number', 0],
        'notarial_fee' => ['number', 0],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    // 24000 * 0.0075 = 180.00
    expect($documentData['loan']['documentary_stamp_rate_raw'])->toBe(0.0075)
        ->and($documentData['loan']['documentary_stamp_amount_raw'])->toBe(180.0);
});

test('notarial fee accepts an arbitrary staff-entered value with no forced default', function (): void {
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
        'loan_security_rate' => ['number', 0],
        'savings_rate' => ['number', 0],
        'documentary_stamp_rate' => ['number', 0],
        'notarial_fee' => ['number', 250],
        'penalty_rate_per_month' => ['number', 0],
        'witness_one_name' => ['string', 'Witness One'],
        'witness_two_name' => ['string', 'Witness Two'],
    ]);

    $documentData = app(ApprovedLoanDocumentService::class)->buildDocumentData($loanRequest);

    expect($documentData['loan']['notarial_fee_raw'])->toBe(250.0);
});
