<?php

use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

function kindOfLoanMemberSectionPayload(array $overrides = []): array
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
            'payment_option' => 'ATM Deduction',
            'payment_bank_name' => 'WIBS Cooperative Bank',
            'payment_account_name' => 'Loan Member',
            'payment_account_number' => '1234567890',
            'payment_account_type' => 'Savings',
            'payment_atm_number' => '9876543210',
            'payment_atm_holder_name' => 'Loan Member',
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

function kindOfLoanApplicantPayload(array $overrides = []): array
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

function kindOfLoanCoMakerPayload(string $seed): array
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

function setUpKindOfLoanMember(string $acctno): User
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

function kindOfLoanStorePayload(string $typecode, array $overrides = []): array
{
    $payload = array_replace_recursive([
        'typecode' => $typecode,
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Business capital',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...kindOfLoanMemberSectionPayload(),
        'applicant' => kindOfLoanApplicantPayload(),
        'co_maker_1' => kindOfLoanCoMakerPayload('CoOne'),
        'co_maker_2' => kindOfLoanCoMakerPayload('CoTwo'),
    ], $overrides);

    return $payload;
}

test('member must select a kind of loan when submitting a Micro Business Loan request', function () {
    Storage::fake('public');

    $user = setUpKindOfLoanMember('000901');

    DB::table('wlntype')->insert([
        'typecode' => '02',
        'lntype' => 'MICRO BUSINESS LOAN',
    ]);

    $payload = kindOfLoanStorePayload('02');

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors('kind_of_loan');
    expect(LoanRequest::query()->count())->toBe(0);
});

test('member can submit a Micro Business Loan request with an Emergency kind of loan', function () {
    Storage::fake('public');

    $user = setUpKindOfLoanMember('000902');

    DB::table('wlntype')->insert([
        'typecode' => '02',
        'lntype' => 'MICRO BUSINESS LOAN',
    ]);

    $payload = kindOfLoanStorePayload('02', ['kind_of_loan' => 'Emergency']);

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->kind_of_loan)->toBe('Emergency');
});

test('kind of loan is not required for a non-Micro-Business loan type', function () {
    Storage::fake('public');

    $user = setUpKindOfLoanMember('000903');

    DB::table('wlntype')->insert([
        'typecode' => '01',
        'lntype' => 'OTHER LOAN',
    ]);

    $payload = kindOfLoanStorePayload('01', [
        'other_loan_type_name' => 'Miscellaneous Loan',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->kind_of_loan)->toBeNull();
});

test('member can save a draft with a kind of loan for a Micro Business Loan without other required fields', function () {
    $user = setUpKindOfLoanMember('000904');

    DB::table('wlntype')->insert([
        'typecode' => '02',
        'lntype' => 'MICRO BUSINESS LOAN',
    ]);

    $response = $this
        ->actingAs($user)
        ->patchJson(route('client.loan-requests.draft'), [
            'typecode' => '02',
            'kind_of_loan' => 'Emergency',
        ]);

    $response->assertOk();

    $loanRequest = LoanRequest::query()->first();

    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->kind_of_loan)->toBe('Emergency');
});
