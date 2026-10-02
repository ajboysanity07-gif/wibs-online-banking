<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table): void {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
        });
    }
});

function calculatorMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(Role::query()->where('name', Role::MEMBER)->pluck('id')->all());

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Calc', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Calc St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

function calculatorProcessor(): AppUser
{
    $user = AppUser::factory()->create(['acctno' => null, 'phoneno' => null, 'email_verified_at' => now()]);

    $user->roles()->sync(Role::query()->where('name', Role::LOAN_PROCESSOR)->pluck('id')->all());

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

test('the member calculator and the staff processing preview produce the same figures', function (float $amount, int $term): void {
    config([
        'loan_workflow.estimate.interest_rate' => 0.36,
        'loan_workflow.estimate.service_charge_rate' => 0.05,
    ]);

    $member = calculatorMember('007'.str_pad((string) $term, 3, '0', STR_PAD_LEFT));
    $processor = calculatorProcessor();

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => '02',
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'recommended_payment_frequency' => 'Monthly',
        'submitted_at' => now(),
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 30000]);

    $estimate = $this->actingAs($member)
        ->postJson(route('client.loan-requests.estimate'), [
            'amount' => $amount,
            'term' => $term,
            'typecode' => '02',
            'payment_frequency' => 'Monthly',
            'insurance_rate' => 1.0,
        ])
        ->assertOk()
        ->json('data');

    // Staff start from the same institutional defaults (loan security,
    // savings, doc stamp, notarial fee are left to the builder's defaults).
    $preview = $this->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.processing-details.preview', $loanRequest), [
            'recommended_amount' => $amount,
            'recommended_term' => $term,
            'recommended_interest_rate' => 0.36,
            'service_charge_rate' => 0.05,
            'insurance_rate' => 1.0,
            'insurance_term' => $term,
        ])
        ->assertOk()
        ->json('data');

    foreach ([
        'service_charge_amount_raw',
        'insurance_premium_raw',
        'loan_security_amount_raw',
        'documentary_stamp_amount_raw',
        'deductions_total_raw',
        'net_proceeds_raw',
        'amortization_total_raw',
    ] as $key) {
        expect((float) $estimate[$key])->toEqualWithDelta((float) $preview[$key], 0.001, $key);
    }

    expect($estimate['net_proceeds_raw'])->toBeLessThan($amount);
})->with([
    'small, one month' => [7500.0, 1],
    'mid, 12 months' => [25000.0, 12],
    'large, 36 months' => [150000.0, 36],
]);

test('the estimate excludes interest and service charge until they are configured', function (): void {
    config([
        'loan_workflow.estimate.interest_rate' => null,
        'loan_workflow.estimate.service_charge_rate' => null,
    ]);

    $member = calculatorMember('007101');

    $data = $this->actingAs($member)
        ->postJson(route('client.loan-requests.estimate'), ['amount' => 20000, 'term' => 12])
        ->assertOk()
        ->json('data');

    // 5% loan security (non-"Other Loan"), insurance 20 * 12 * 1, doc stamp
    // ceil(20000/200) * 1.50, notarial fee 100.
    expect($data['interest_rate'])->toBeNull()
        ->and($data['service_charge_amount_raw'])->toBeNull()
        ->and((float) $data['net_proceeds_raw'])->toEqualWithDelta(20000 - 1000 - 240 - 150 - 100, 0.001);
});

test('the estimate insures the term up to 12 months', function (int $term, float $premium): void {
    $member = calculatorMember('00711'.$term);

    $data = $this->actingAs($member)
        ->postJson(route('client.loan-requests.estimate'), ['amount' => 20000, 'term' => $term, 'insurance_rate' => 1.0])
        ->assertOk()
        ->json('data');

    expect((float) $data['insurance_premium_raw'])->toEqualWithDelta($premium, 0.001);
})->with([
    'under two months, none' => [1, 0.0],
    'six months' => [6, 120.0],
    'twelve months' => [12, 240.0],
    'twenty-four months, capped' => [24, 240.0],
    'thirty-six months, capped' => [36, 240.0],
]);

test('the estimate rejects a term past the configured maximum', function (): void {
    $member = calculatorMember('007102');

    $this->actingAs($member)
        ->postJson(route('client.loan-requests.estimate'), ['amount' => 20000, 'term' => 37])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('term');
});

test('saved confirmation ticks come back with the draft', function (): void {
    $member = calculatorMember('007103');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'wizard_confirmations' => ['applicant_personal', 'bank_account'],
        ])
        ->assertNoContent();

    expect(LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'wizard_confirmations')
        ->exists())->toBeTrue();

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wizardConfirmations', ['applicant_personal', 'bank_account'])
            ->where('estimateLimits.maxTermMonths', 36));
});

test('an unknown confirmation key is rejected', function (): void {
    $member = calculatorMember('007104');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'wizard_confirmations' => ['everything'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('wizard_confirmations.0');
});

test('the request page shows the confirmation only right after submitting', function (): void {
    $member = calculatorMember('007105');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->withSession(['loanRequestSubmitted' => $loanRequest->id])
        ->get(route('client.loan-requests.show', $loanRequest))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('justSubmitted', true));

    // The flag is flashed, so a later visit (no flash) is plain tracking.
    $this->actingAs($member)
        ->withSession(['loanRequestSubmitted' => null])
        ->get(route('client.loan-requests.show', $loanRequest))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('justSubmitted', false));
});

test('track my application lands on the tracking view, whatever the query string says', function (): void {
    $member = calculatorMember('007107');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    // Only the one-time session flash shows the success screen; a leftover
    // ?justSubmitted query (old "Track my application" URLs) is ignored.
    foreach (['true', 'false', '1'] as $value) {
        $this->actingAs($member)
            ->withSession(['loanRequestSubmitted' => null])
            ->get(route('client.loan-requests.show', [$loanRequest, 'justSubmitted' => $value]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('justSubmitted', false));
    }
});

test('the confirmation page payload carries the fields the confirmation card renders', function (): void {
    $member = calculatorMember('007106');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->withSession(['loanRequestSubmitted' => $loanRequest->id])
        ->get(route('client.loan-requests.show', $loanRequest))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('justSubmitted', true)
            ->where('loanRequest.reference', $loanRequest->reference)
            ->has('loanRequest.requested_amount')
            ->has('loanRequest.requested_term')
            ->where('loanRequest.submitted_at', $loanRequest->submitted_at?->toDateTimeString()));
});
