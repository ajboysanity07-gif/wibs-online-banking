<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
            $table->string('spouse')->nullable();
            $table->string('restype')->nullable();
            $table->string('dependent')->nullable();
        });
    }

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table) {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }
});

test('approved client can view the loan request form', function () {
    $user = User::factory()->create([
        'acctno' => '000710',
    ]);
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-001',
        'lntype' => 'Salary/Pension',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->has('loanTypes', 1)
            ->has('applicant')
            ->has('coMakerOne')
            ->has('coMakerTwo')
            ->has('draft')
            ->has('member'));
});

test('clients without completed profiles are redirected away from loan request form', function () {
    $user = User::factory()->create();
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

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response->assertRedirect(route('profile.edit', ['onboarding' => 1]));
});

test('clients can save a loan request draft', function () {
    $user = User::factory()->create([
        'acctno' => '000712',
    ]);
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-003',
        'lntype' => 'Personal',
    ]);

    $payload = [
        'typecode' => 'LN-003',
        'requested_amount' => 12000,
        'requested_term' => 10,
        'loan_purpose' => 'Home repair',
        'availment_status' => 'New',
        'applicant' => [
            'first_name' => 'Loan',
            'last_name' => 'Member',
            'birthdate' => '1990-04-10',
            'birthplace' => 'Manila',
            'address' => 'Loan Street',
            'length_of_stay' => '5 years',
            'housing_status' => 'Owned',
            'cell_no' => '09123456789',
            'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'employment_type' => 'Private',
            'employer_business_name' => 'Loan Company',
            'employer_business_address' => 'Loan City',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '3 years',
            'gross_monthly_income' => 25000,
            'payday' => '15/30',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->patch(route('client.loan-requests.draft'), $payload);

    $response->assertRedirect(route('client.loan-requests.create'));

    $draft = LoanRequest::query()->first();

    expect($draft)->not->toBeNull();
    expect($draft->status)->toBe(LoanRequestStatus::Draft);
    expect($draft->submitted_at)->toBeNull();

    $payload['loan_purpose'] = 'Tuition';

    $this
        ->actingAs($user)
        ->patch(route('client.loan-requests.draft'), $payload);

    expect(LoanRequest::query()->count())->toBe(1);
});

test('loan request form resumes existing draft', function () {
    $user = User::factory()->create([
        'acctno' => '000713',
    ]);
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::Draft,
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Draft',
            'last_name' => 'Member',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('draft.id', $loanRequest->id)
            ->where('applicant.first_name', 'Draft'));
});

test('loan request submissions persist snapshots', function () {
    $user = User::factory()->create([
        'acctno' => '000711',
    ]);
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-002',
        'lntype' => 'Personal',
    ]);

    $payload = [
        'typecode' => 'LN-002',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'applicant' => [
            'first_name' => 'Loan',
            'last_name' => 'Member',
            'middle_name' => 'Q',
            'nickname' => 'LM',
            'birthdate' => '1990-04-10',
            'birthplace' => 'Manila',
            'address' => 'Loan Street',
            'length_of_stay' => '5 years',
            'housing_status' => 'Owned',
            'cell_no' => '09123456789',
            'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'number_of_children' => 0,
            'spouse_name' => null,
            'spouse_age' => null,
            'spouse_cell_no' => null,
            'employment_type' => 'Private',
            'employer_business_name' => 'Loan Company',
            'employer_business_address' => 'Loan City',
            'telephone_no' => '021234567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '3 years',
            'gross_monthly_income' => 25000,
            'payday' => '15/30',
        ],
        'co_maker_1' => [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace' => 'Cebu',
            'address' => 'Co Maker Street',
            'length_of_stay' => '4 years',
            'housing_status' => 'Rent',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Co Maker Office',
            'employer_business_address' => 'Cebu City',
            'telephone_no' => '021234567',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => '30',
        ],
        'co_maker_2' => [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace' => 'Davao',
            'address' => 'Second Street',
            'length_of_stay' => '2 years',
            'housing_status' => 'Owned',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Second Store',
            'employer_business_address' => 'Davao City',
            'telephone_no' => '021234567',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => '15',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->submitted_at)->not->toBeNull();
    expect(LoanRequestPerson::query()->where('loan_request_id', $loanRequest->id)->count())
        ->toBe(3);
});

test('loan request pdf endpoint responds with a pdf', function () {
    $user = User::factory()->create();
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create();

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.pdf', $loanRequest));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('admin requests api returns loan request data', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Loan',
            'last_name' => 'Member',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?perPage=10&page=1');

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $loanRequest->id);
});

test('admin dashboard reports loan requests count', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/dashboard')
        ->where('summary.metrics.requestsCount', 1));
});

test('client loans page lists member loan requests', function () {
    $user = User::factory()->create([
        'acctno' => '000720',
    ]);
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $draft = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::Draft,
            'requested_amount' => 12000,
            'requested_term' => 12,
            'updated_at' => now(),
        ]);

    $submitted = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'requested_amount' => 18000,
            'requested_term' => 18,
            'submitted_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loans'));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loans')
            ->has('summary')
            ->has('loans')
            ->has('loanRequests.items', 2)
            ->where('loanRequests.items.0.id', $draft->id)
            ->where('loanRequests.items.0.status', LoanRequestStatus::Draft->value)
            ->where('loanRequests.items.1.id', $submitted->id)
            ->where('loanRequests.items.1.status', LoanRequestStatus::UnderReview->value));
});

test('draft loan request details redirect to the request form', function () {
    $user = User::factory()->create();
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::Draft,
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.show', $loanRequest));

    $response->assertRedirect(route('client.loan-requests.create'));
});

test('client can view submitted loan request details', function () {
    $user = User::factory()->create();
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
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'submitted_at' => now(),
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.show', $loanRequest));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request-show')
            ->where('loanRequest.id', $loanRequest->id));
});
