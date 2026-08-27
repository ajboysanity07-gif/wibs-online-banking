<?php

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
 * Standard charge overrides shared by the MONTHLY and WEEKLY cases so the
 * only thing that differs between them is recommended_payment_frequency.
 *
 * @return array<string, int|float>
 */
function previewChargesOverridePayload(): array
{
    return [
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 0.36,
        'service_charge_rate' => 0.05,
        'insurance_rate' => 1.0,
        'insurance_term' => 12,
        'loan_security_rate' => 0.02,
        'savings_rate' => 0.02,
        'documentary_stamp_rate' => 0.0075,
        'notarial_fee' => 100.0,
        'penalty_rate_per_month' => 0.05,
    ];
}

test('happy path preview computes net proceeds and suggested GNTHP for MONTHLY frequency', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950101');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Monthly',
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            previewChargesOverridePayload(),
        )
        ->assertOk();

    // Hand-computed: interest 25000*0.36/12*12=9000, service charge
    // 25000*0.05=1250, insurance (25000/1000)*12*1.0=300, loan security
    // 25000*0.02=500, doc stamp 25000*0.0075=187.5, notarial fee 100 flat.
    // Interest is disclosed under "Not Deducted From Proceeds of Loan" (it is
    // amortized into the payment schedule), so finance charges are the
    // service charge alone and net proceeds = 25000 - (1250+300+500+187.5+100)
    // = 22662.5.
    $this->assertEqualsWithDelta(22662.5, $response->json('data.net_proceeds_raw'), 0.01);
    $this->assertEqualsWithDelta(25000.0, $response->json('data.approved_amount_raw'), 0.01);
    $this->assertEqualsWithDelta(1250.0, $response->json('data.finance_charge_total_raw'), 0.01);
    $this->assertEqualsWithDelta(1087.5, $response->json('data.non_finance_charge_total_raw'), 0.01);
    $this->assertEqualsWithDelta(2337.5, $response->json('data.deductions_total_raw'), 0.01);

    // amortizationCount for MONTHLY/12 months = 12. principal 25000/12=2083.33,
    // interest 9000/12=750, savings 2083.33*0.02=41.67 (savings_rate is
    // independent from loan_security_rate but set to the same 0.02 here)
    // -> total 2875.00 per payment; MONTHLY multiplier is x1, so
    // monthly-equivalent is 2875.00.
    // suggested GNTHP = 15000 - 2875.00 = 12125.00.
    $this->assertEqualsWithDelta(12125.0, $response->json('data.suggested_gnthp_raw'), 0.01);

    expect($response->json('data.failure_information'))->toBeNull();
});

test('non-monthly frequency converts the suggested GNTHP using the x30/7 weekly multiplier', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950102');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Weekly',
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            previewChargesOverridePayload(),
        )
        ->assertOk();

    // net proceeds does not depend on payment frequency at all, so it must
    // match the MONTHLY case exactly (interest is not deducted from proceeds).
    $this->assertEqualsWithDelta(22662.5, $response->json('data.net_proceeds_raw'), 0.01);

    // WEEKLY amortizationCount = round(12*30/7) = round(51.428...) = 51.
    // principal 25000/51=490.20, interest 9000/51=176.47,
    // savings 490.20*0.02=9.80 -> per-payment total 676.47.
    // Monthly-equivalent multiplier is x30/7 -> 676.47*30/7=2899.16.
    // suggested GNTHP = 15000 - 2899.16 = 12100.84.
    $this->assertEqualsWithDelta(12100.84, $response->json('data.suggested_gnthp_raw'), 0.01);

    expect($response->json('data.failure_information'))->toBeNull();
});

test('unsaved recommended_payment_frequency override is used instead of the persisted DB value', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950108');

    // Persisted frequency is MONTHLY; the request below overrides it to
    // WEEKLY, simulating an unsaved dropdown change on the staff screen.
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Monthly',
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            [
                ...previewChargesOverridePayload(),
                'recommended_payment_frequency' => 'Weekly',
            ],
        )
        ->assertOk();

    // net proceeds does not depend on payment frequency, so it matches the
    // MONTHLY-frequency happy-path case exactly (interest not deducted).
    $this->assertEqualsWithDelta(22662.5, $response->json('data.net_proceeds_raw'), 0.01);

    // If the stale persisted MONTHLY value were used instead of the WEEKLY
    // override, this would equal the MONTHLY case's 12125.0 rather than the
    // WEEKLY case's 12100.84 (see the two happy-path tests above).
    $this->assertEqualsWithDelta(12100.84, $response->json('data.suggested_gnthp_raw'), 0.01);

    $loanRequest->refresh();
    expect($loanRequest->recommended_payment_frequency)->toBe('Monthly');
});

test('preview never persists POSTed override values to the database', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950103');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Quincenal',
        'recommended_amount' => 10000,
        'recommended_term' => 6,
        'recommended_interest_rate' => 0.10,
        'approved_amount' => null,
        'approved_term' => null,
        'approved_interest_rate' => null,
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $beforeSnapshot = $loanRequest->only([
        'approved_amount',
        'approved_term',
        'approved_interest_rate',
        'recommended_amount',
        'recommended_term',
        'recommended_interest_rate',
        'updated_at',
    ]);
    $dataEntryChargeKeys = [
        'service_charge_rate',
        'insurance_rate',
        'insurance_term',
        'loan_security_rate',
        'savings_rate',
        'documentary_stamp_rate',
        'notarial_fee',
        'penalty_rate_per_month',
    ];
    $dataEntryCountBefore = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('field_key', $dataEntryChargeKeys)
        ->count();

    // Deliberately different from the persisted recommended_* values above.
    $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            previewChargesOverridePayload(),
        )
        ->assertOk();

    $loanRequest->refresh();

    expect($loanRequest->only([
        'approved_amount',
        'approved_term',
        'approved_interest_rate',
        'recommended_amount',
        'recommended_term',
        'recommended_interest_rate',
        'updated_at',
    ]))->toEqual($beforeSnapshot);

    $dataEntryCountAfter = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('field_key', $dataEntryChargeKeys)
        ->count();

    expect($dataEntryCountAfter)->toBe($dataEntryCountBefore);
});

test('missing recommended amount, term, and interest rate blocks both figures', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950104');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Monthly',
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            [
                'recommended_amount' => null,
                'recommended_term' => null,
                'recommended_interest_rate' => null,
            ],
        )
        ->assertOk();

    expect($response->json('data.net_proceeds_raw'))->toBeNull()
        ->and($response->json('data.suggested_gnthp_raw'))->toBeNull()
        ->and($response->json('data.failure_information'))->not->toBeNull()
        ->and($response->json('data.failure_information.blockers'))->toContain(
            'Recommended amount is required.',
            'Recommended term is required.',
            'Recommended interest rate is required.',
        );
});

test('missing applicant gross monthly income blocks only the suggested GNTHP figure', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950105');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Monthly',
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => null]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            previewChargesOverridePayload(),
        )
        ->assertOk();

    $this->assertEqualsWithDelta(22662.5, $response->json('data.net_proceeds_raw'), 0.01);

    expect($response->json('data.suggested_gnthp_raw'))->toBeNull()
        ->and($response->json('data.failure_information'))->not->toBeNull()
        ->and($response->json('data.failure_information.blockers'))->toContain(
            "Applicant's gross monthly income is not on file.",
        );
});

test('preview is denied for a user without updateProcessingDetails permission on the request', function (): void {
    $member = previewCreateActor([Role::MEMBER], '950106');
    $otherMember = previewCreateActor([Role::MEMBER], '950107');

    $loanRequest = LoanRequest::factory()->forUser($otherMember)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $this
        ->actingAs($member)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            previewChargesOverridePayload(),
        )
        ->assertForbidden();
});

test('preview accepts Lumpsum frequency, derives the lumpsum month count from the recommended term, zeroes loan security, and still charges insurance for a 2-month-or-longer term', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950103');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Due date',
        'recommended_term' => 1,
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            [
                ...previewChargesOverridePayload(),
                'recommended_payment_frequency' => 'Due date',
            ],
        )
        ->assertOk();

    // interest 25000*0.36/12*12=9000, service charge 25000*0.05=1250,
    // insurance (25000/1000)*12*1.0=300 (a 12-month Lumpsum is not the
    // 1-month waiver case, so insurance still applies), loan security is
    // forced to 0 for any Lumpsum, doc stamp 25000*0.0075=187.5, notarial
    // fee 100 flat. For lumpsum/Due-date, interest is advance interest
    // deducted from proceeds, so finance charges = 1250+9000=10250,
    // net = 25000 - (10250+300+187.5+100) = 14162.5.
    $this->assertEqualsWithDelta(14162.5, $response->json('data.net_proceeds_raw'), 0.01);
});

test('preview zeroes both loan security and insurance for a 1-month Lumpsum', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950105');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Due date',
        'recommended_term' => 1,
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            [
                ...previewChargesOverridePayload(),
                'recommended_amount' => 25000,
                'recommended_term' => 1,
                'recommended_payment_frequency' => 'Due date',
            ],
        )
        ->assertOk();

    // interest 25000*0.36/12*1=750, service charge 25000*0.05=1250, doc
    // stamp 25000*0.0075=187.5, notarial fee 100 flat; loan
    // security/insurance are forced to 0 for a 1-month Lumpsum only.
    // For lumpsum/Due-date, interest is advance interest deducted from
    // proceeds, so finance charges = 1250+750=2000,
    // net = 25000 - (2000+187.5+100) = 22712.5.
    $this->assertEqualsWithDelta(22712.5, $response->json('data.net_proceeds_raw'), 0.01);
});

test('preview rejects Lumpsum frequency without a recommended term to derive the month count from', function (): void {
    $processor = previewCreateActor([Role::LOAN_PROCESSOR]);
    $member = previewCreateActor([Role::MEMBER], '950104');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $payload = previewChargesOverridePayload();
    unset($payload['recommended_term']);

    $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $loanRequest),
            [
                ...$payload,
                'recommended_payment_frequency' => 'Due date',
            ],
        )
        ->assertInvalid(['recommended_term']);
});

/**
 * @param  list<string>  $roles
 */
function previewCreateActor(array $roles, ?string $acctno = null): AppUser
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
