<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

test('profile income sync propagates to non-terminal snapshots and audits non-draft requests only', function (): void {
    $member = incomeSyncMember('440200');
    $activeRequest = incomeSyncLoanRequest($member, LoanRequestStatus::UnderReview, 4000);
    $draftRequest = incomeSyncLoanRequest($member, LoanRequestStatus::Draft, 4000);
    $releasedRequest = incomeSyncLoanRequest($member, LoanRequestStatus::Released, 4000);
    $rejectedRequest = incomeSyncLoanRequest($member, LoanRequestStatus::Rejected, 4000);

    $updated = app(LoanRequestService::class)->syncApplicantIncomeFromProfile($member, 5000.0);

    expect($updated)->toBe(2);

    expect($activeRequest->refresh()->people->first()->gross_monthly_income)->toBe('5000.00');
    expect($draftRequest->refresh()->people->first()->gross_monthly_income)->toBe('5000.00');
    expect($releasedRequest->refresh()->people->first()->gross_monthly_income)->toBe('4000.00');
    expect($rejectedRequest->refresh()->people->first()->gross_monthly_income)->toBe('4000.00');

    expect(LoanRequestChange::query()
        ->where('loan_request_id', $activeRequest->id)
        ->where('action', LoanRequestChange::ACTION_MEMBER_PROFILE_INCOME_SYNCED)
        ->where('changed_by', $member->user_id)
        ->exists())->toBeTrue();

    expect(LoanRequestChange::query()
        ->where('loan_request_id', $draftRequest->id)
        ->exists())->toBeFalse();
});

test('profile income sync is a no-op when the snapshot already matches the profile', function (): void {
    $member = incomeSyncMember('440201');
    $activeRequest = incomeSyncLoanRequest($member, LoanRequestStatus::UnderReview, 5000);

    $updated = app(LoanRequestService::class)->syncApplicantIncomeFromProfile($member, 5000.0);

    expect($updated)->toBe(0);
    expect($activeRequest->refresh()->people->first()->gross_monthly_income)->toBe('5000.00');
    expect(LoanRequestChange::query()
        ->where('loan_request_id', $activeRequest->id)
        ->exists())->toBeFalse();
});

test('profile income sync ignores members without a gross monthly income on file', function (): void {
    $member = incomeSyncMember('440202');
    $member->memberApplicationProfile()->update(['gross_monthly_income' => null]);
    $activeRequest = incomeSyncLoanRequest($member, LoanRequestStatus::UnderReview, 4000);

    $updated = app(LoanRequestService::class)->syncApplicantIncomeFromProfile($member, null);

    expect($updated)->toBe(0);
    expect($activeRequest->refresh()->people->first()->gross_monthly_income)->toBe('4000.00');
});

test('saving the profile propagates the income to active loan request snapshots', function (): void {
    $member = incomeSyncMember('440203');
    $activeRequest = incomeSyncLoanRequest($member, LoanRequestStatus::UnderReview, 4000);

    $this
        ->actingAs($member)
        ->patch(route('profile.update'), profileUpdatePayload([
            'gross_monthly_income' => '5000.00',
        ]))
        ->assertSessionHasNoErrors();

    expect($activeRequest->refresh()->people->first()->gross_monthly_income)->toBe('5000.00');

    expect(LoanRequestChange::query()
        ->where('loan_request_id', $activeRequest->id)
        ->where('action', LoanRequestChange::ACTION_MEMBER_PROFILE_INCOME_SYNCED)
        ->where('changed_by', $member->user_id)
        ->exists())->toBeTrue();
});

test('sync-profile-incomes dry run reports mismatches without writing', function (): void {
    $member = incomeSyncMember('440204');
    $activeRequest = incomeSyncLoanRequest($member, LoanRequestStatus::UnderReview, 4000);

    $this
        ->artisan('loan-requests:sync-profile-incomes')
        ->expectsOutputToContain("member={$member->user_id} loan_request={$activeRequest->id}")
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain('Mismatched applicant snapshots found: 1')
        ->assertSuccessful();

    expect($activeRequest->refresh()->people->first()->gross_monthly_income)->toBe('4000.00');
    expect(LoanRequestChange::query()
        ->where('loan_request_id', $activeRequest->id)
        ->exists())->toBeFalse();
});

test('sync-profile-incomes fix mode applies the income and audits the change', function (): void {
    $member = incomeSyncMember('440205');
    $activeRequest = incomeSyncLoanRequest($member, LoanRequestStatus::UnderReview, 4000);

    $this
        ->artisan('loan-requests:sync-profile-incomes', ['--fix' => true])
        ->expectsOutputToContain('Fix mode enabled')
        ->expectsOutputToContain('Applicant snapshots updated: 1')
        ->assertSuccessful();

    expect($activeRequest->refresh()->people->first()->gross_monthly_income)->toBe('5000.00');

    expect(LoanRequestChange::query()
        ->where('loan_request_id', $activeRequest->id)
        ->where('action', LoanRequestChange::ACTION_MEMBER_PROFILE_INCOME_SYNCED)
        ->where('changed_by', $member->user_id)
        ->exists())->toBeTrue();
});

test('processing details update recomputes GNTHP from the newly submitted applicant income', function (): void {
    $processor = incomeSyncActor([Role::LOAN_PROCESSOR]);
    $member = incomeSyncActor([Role::MEMBER], '440206');
    $activeRequest = incomeSyncLoanRequest($member, LoanRequestStatus::UnderReview, 15000, [
        'assigned_officer_id' => $processor->user_id,
    ]);

    $staleGnthp = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.processing-details.preview', $activeRequest),
            incomeSyncChargesPayload(),
        )
        ->assertOk()
        ->json('data.suggested_gnthp_raw');

    expect($staleGnthp)->not->toBeNull();

    $this
        ->actingAs($processor)
        ->patchJson(
            route('spa.workflow.loan-requests.processing-details', $activeRequest),
            [
                'applicant' => ['gross_monthly_income' => 25000],
                'loan_request' => [
                    'requested_amount' => $activeRequest->requested_amount,
                    'requested_term' => $activeRequest->requested_term,
                    'loan_purpose' => $activeRequest->loan_purpose,
                    'availment_status' => $activeRequest->availment_status,
                ],
                ...incomeSyncChargesPayload(),
            ],
        )
        ->assertOk();

    // The GNTHP is the applicant's income minus the monthly amortization. If
    // the preview had read the stale in-memory applicant snapshot, the
    // persisted figure would still be based on the old 15000 income; with the
    // relation cleared after the upsert it must use the newly submitted 25000.
    $persistedGnthp = data_get(
        LoanRequestDataEntry::query()
            ->where('loan_request_id', $activeRequest->id)
            ->where('section_key', 'processing')
            ->where('field_key', 'guaranteed_net_take_home_pay')
            ->value('value_json'),
        'value',
    );

    $this->assertEqualsWithDelta($staleGnthp + 10000, (float) $persistedGnthp, 0.01);

    expect($activeRequest->refresh()->people->first()->gross_monthly_income)->toBe('25000.00');
});

/**
 * @param  list<string>  $roles
 */
function incomeSyncActor(array $roles, ?string $acctno = null): AppUser
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

function incomeSyncMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
        'civil_status' => null,
        'gross_monthly_income' => 5000.00,
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Income', 'lname' => 'Sync', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

function incomeSyncLoanRequest(AppUser $member, LoanRequestStatus $status, float $income, array $extra = []): LoanRequest
{
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => $status === LoanRequestStatus::Draft ? null : now(),
        ...$extra,
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => $income]);

    return $loanRequest;
}

/**
 * @return array<string, int|float>
 */
function incomeSyncChargesPayload(): array
{
    return [
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 0.36,
        'recommended_payment_frequency' => 'Monthly',
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profileUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'IncomeSync',
        'email' => 'income.sync@example.com',
        'phoneno' => '09123456789',
        'birthplace_city' => 'City of Batac',
        'birthplace_province' => 'Ilocos Norte',
        'educational_attainment' => 'High School',
        'length_of_stay' => '2 years',
        'home_address1' => '123 Main Street',
        'home_address_barangay' => 'Aglipay',
        'home_address2' => 'Batac City',
        'home_address3' => 'Ilocos Norte',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'employment_type' => 'Regular',
        'employer_business_name' => 'Acme Corp',
        'employer_business_address_barangay' => 'Aglipay',
        'employer_business_address2' => 'Batac City',
        'employer_business_address3' => 'Ilocos Norte',
        'current_position' => 'Analyst',
        'gross_monthly_income' => '5000.00',
        'payday' => 'Quincenal',
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Income Sync',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'Cash',
        'height_cm' => '165',
        'weight_kg' => '68',
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_number' => '1234567890',
    ], $overrides);
}
