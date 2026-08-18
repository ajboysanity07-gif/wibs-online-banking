<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestDecisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('birthplace')->nullable();
            $table->string('address')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
            $table->string('zone_number')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
            $table->string('spouse')->nullable();
            $table->string('restype')->nullable();
            $table->string('dependent')->nullable();
        });
    }

    if (! Schema::hasTable('wlnled')) {
        Schema::create('wlnled', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber')->nullable();
        });
    }

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table) {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    Cache::forget('loan_requests.loan_types');
    Cache::forget('loan_requests.loan_type_labels');
});

function lumpsumMemberSectionPayload(array $overrides = []): array
{
    $payload = [
        'insurance' => [
            'beneficiary_primary_name' => 'Primary Beneficiary',
            'beneficiary_primary_relationship' => 'Spouse',
            'beneficiary_primary_birthdate' => '1992-03-21',
            'beneficiary_secondary_name' => 'Secondary Beneficiary',
            'beneficiary_secondary_relationship' => 'Sibling',
            'beneficiary_secondary_birthdate' => '1988-11-04',
        ],
        'health' => [
            'health_smoking_status' => 'none',
            'health_hypertension' => false,
        ],
        'health_glapi' => [
            'health_recent_hospitalization' => false,
        ],
        'banking' => [
            'payout_bank_name' => 'WIBS Cooperative Bank',
            'payout_account_name' => 'Loan Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'payout_atm_number' => '9876543210',
            'release_method' => 'Bank Transfer',
            'payment_option' => 'Salary Deduction',
        ],
        'declarations' => [
            'declaration_existing_loans' => false,
            'declaration_pending_cases' => false,
            'declaration_truth_confirmation' => true,
            'declaration_data_privacy_consent' => true,
        ],
        'dependents' => [
            'applicant_cycle_status' => 'New',
        ],
    ];

    return array_replace_recursive($payload, $overrides);
}

function lumpsumApplicantPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'first_name' => 'Loan',
        'last_name' => 'Member',
        'middle_name' => 'Q',
        'nickname' => 'LM',
        'birthdate' => '1990-04-10',
        'birthplace_city' => 'Manila',
        'birthplace_province' => 'Metro Manila',
        'address1' => 'Loan Street',
        'address2' => 'Manila',
        'address3' => 'Metro Manila',
        'length_of_stay' => '5 years',
        'housing_status' => 'OWNED',
        'cell_no' => '09123456789',
        'civil_status' => 'Single',
        'educational_attainment' => 'College',
        'number_of_children' => 0,
        'spouse_name' => null,
        'spouse_age' => null,
        'spouse_cell_no' => null,
        'employment_type' => 'Private',
        'employer_business_name' => 'Loan Company',
        'employer_business_address1' => 'Loan City Center',
        'employer_business_address2' => 'Manila',
        'employer_business_address3' => 'Metro Manila',
        'telephone_no' => '021234567',
        'current_position' => 'Analyst',
        'nature_of_business' => 'Finance',
        'years_in_work_business' => '3 years',
        'employer_date_employed' => '2018-03-15',
        'gross_monthly_income' => 25000,
        'payday' => '15th & 30th',
    ], $overrides);
}

function lumpsumCoMakerPayload(string $seed): array
{
    return [
        'first_name' => "{$seed}First",
        'last_name' => "{$seed}Last",
        'middle_name' => 'M',
        'nickname' => null,
        'birthdate' => '1989-03-12',
        'birthplace_city' => 'Cebu',
        'birthplace_province' => 'Cebu',
        'address1' => "{$seed} Street",
        'address2' => 'Cebu City',
        'address3' => 'Cebu',
        'length_of_stay' => '4 years',
        'housing_status' => 'RENT',
        'cell_no' => '09998887777',
        'civil_status' => 'Married',
        'educational_attainment' => 'College',
        'employment_type' => 'Government',
        'employer_business_name' => "{$seed} Office",
        'employer_business_address1' => "{$seed} Plaza",
        'employer_business_address2' => 'Cebu City',
        'employer_business_address3' => 'Cebu',
        'telephone_no' => '021234567',
        'current_position' => 'Clerk',
        'nature_of_business' => 'Government',
        'years_in_work_business' => '6 years',
        'gross_monthly_income' => 18000,
        'payday' => '30th',
    ];
}

function setUpLumpsumMember(string $acctno): User
{
    $user = User::factory()->create(['acctno' => $acctno]);

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Loan',
        'fname' => 'Loan',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Loan Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);

    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $user->user_id,
    ]);

    return $user;
}

test('member can submit a 1-month Lumpsum Other Loan request without insurance or health data', function () {
    Storage::fake('public');

    $user = setUpLumpsumMember('000801');

    DB::table('wlntype')->insert([
        'typecode' => '01',
        'lntype' => 'OTHER LOAN',
    ]);

    $payload = [
        'typecode' => '01',
        'requested_amount' => 15000,
        'requested_term' => 1,
        'loan_purpose' => 'Emergency expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'requested_payment_frequency' => 'Lumpsum',
        ...lumpsumMemberSectionPayload([
            'insurance' => [],
            'health' => [],
            'dependents' => [],
        ]),
        'applicant' => lumpsumApplicantPayload(),
        'co_maker_1' => lumpsumCoMakerPayload('CoOne'),
        'co_maker_2' => lumpsumCoMakerPayload('CoTwo'),
    ];
    unset($payload['insurance'], $payload['health'], $payload['dependents']);

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->requested_payment_frequency)->toBe('Lumpsum');
    expect($loanRequest->requested_term)->toBe(1);
});

test('member requesting 2-month Lumpsum still requires insurance and health data', function () {
    Storage::fake('public');

    $user = setUpLumpsumMember('000802');

    DB::table('wlntype')->insert([
        'typecode' => '01',
        'lntype' => 'OTHER LOAN',
    ]);

    $payload = [
        'typecode' => '01',
        'requested_amount' => 15000,
        'requested_term' => 2,
        'loan_purpose' => 'Emergency expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'requested_payment_frequency' => 'Lumpsum',
        ...lumpsumMemberSectionPayload([
            'insurance' => [],
            'health' => [],
        ]),
        'applicant' => lumpsumApplicantPayload(),
        'co_maker_1' => lumpsumCoMakerPayload('CoOne'),
        'co_maker_2' => lumpsumCoMakerPayload('CoTwo'),
    ];
    unset($payload['insurance'], $payload['health']);

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors([
        'insurance.beneficiary_primary_name',
        'health.health_smoking_status',
    ]);
    expect(LoanRequest::query()->count())->toBe(0);
});

test('member cannot request Lumpsum for a loan type other than Other Loan', function () {
    Storage::fake('public');

    $user = setUpLumpsumMember('000803');

    DB::table('wlntype')->insert([
        'typecode' => '02',
        'lntype' => 'MICRO BUSINESS LOAN',
    ]);

    $payload = [
        'typecode' => '02',
        'requested_amount' => 15000,
        'requested_term' => 1,
        'loan_purpose' => 'Emergency expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'requested_payment_frequency' => 'Lumpsum',
        ...lumpsumMemberSectionPayload(),
        'applicant' => lumpsumApplicantPayload(),
        'co_maker_1' => lumpsumCoMakerPayload('CoOne'),
        'co_maker_2' => lumpsumCoMakerPayload('CoTwo'),
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors('requested_payment_frequency');
    expect(LoanRequest::query()->count())->toBe(0);
});

test('staff cannot approve a non-lumpsum recommended frequency when insurance data is missing', function () {
    Queue::fake();

    $admin = User::factory()->create(['acctno' => '000804']);
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create(['acctno' => '000805']);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'requested_payment_frequency' => 'Lumpsum',
        'requested_term' => 1,
        'recommended_payment_frequency' => 'Monthly',
    ]);

    prepareLoanRequestForApproval($loanRequest, $admin);

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('approval');

    $loanRequest->refresh();
    expect($loanRequest->status)->toBe(LoanRequestStatus::RecommendedForApproval);

    $service = app(LoanRequestDecisionService::class);
    expect($service->requiresInsuranceDataBeforeApproval($loanRequest))->toBeTrue();
});
