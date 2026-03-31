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
            $table->string('birthplace')->nullable();
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
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

test('loan request people schema excludes spouse occupation', function () {
    expect(Schema::hasColumn('loan_request_people', 'spouse_occupation'))->toBeFalse();
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
            ->has('applicant.employer_business_address1')
            ->has('applicant.employer_business_address2')
            ->has('applicant.employer_business_address3')
            ->has('coMakerOne')
            ->has('coMakerTwo')
            ->has('draft')
            ->has('member'));
});

test('loan request form uses structured wmaster names and address parts', function () {
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
        'mname' => 'Q',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'birthplace' => 'Makati City',
        'address' => 'Legacy Loan Street',
        'address2' => '123 Main Street',
        'address3' => 'Makati',
        'address4' => 'Metro Manila',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'birthplace' => 'Davao City',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-003',
        'lntype' => 'Salary/Pension',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.first_name', 'Loan')
            ->where('applicant.middle_name', 'Q')
            ->where('applicant.last_name', 'Member')
            ->where('applicant.birthplace', 'Makati City')
            ->where('applicant.birthplace_city', 'Makati City')
            ->where('applicant.birthplace_province', null)
            ->where('applicant.address', '123 Main Street, Makati, Metro Manila')
            ->where('applicant.address1', '123 Main Street')
            ->where('applicant.address2', 'Makati')
            ->where('applicant.address3', 'Metro Manila')
            ->where('applicantReadOnly.address1', true)
            ->where('applicantReadOnly.address2', true)
            ->where('applicantReadOnly.address3', true)
            ->where('applicantReadOnly.birthplace_city', true)
            ->where('applicantReadOnly.birthplace_province', false));
});

test('loan request form uses member profile work fields for the applicant', function () {
    $user = User::factory()->create([
        'acctno' => '000716',
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
        'employment_type' => 'Regular',
        'employer_business_name' => 'Acme Corp',
        'employer_business_address' => 'Acme Building',
        'current_position' => 'Supervisor',
        'nature_of_business' => 'Finance',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-010',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.employment_type', 'Regular')
            ->where('applicant.employer_business_name', 'Acme Corp')
            ->where('applicant.employer_business_address', 'Acme Building')
            ->where('applicant.employer_business_address1', 'Acme Building')
            ->where('applicant.employer_business_address2', null)
            ->where('applicant.employer_business_address3', null)
            ->where('applicant.current_position', 'Supervisor')
            ->where('applicant.nature_of_business', 'Finance'));
});

test('loan request snapshot falls back to verified occupation for current position', function () {
    $user = User::factory()->create([
        'acctno' => '000717',
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
    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'current_position' => null,
    ]);

    $service = app(\App\Services\LoanRequests\LoanRequestService::class);
    $payload = $service->getFormData($user);

    expect($payload['applicant']['current_position'])->toBe('Analyst');
});

test('loan request form falls back to legacy wmaster data when structured data is missing', function () {
    $user = User::factory()->create([
        'acctno' => '000714',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Legacy, Loan L.',
        'fname' => null,
        'mname' => null,
        'lname' => null,
        'birthday' => '1990-04-10',
        'birthplace' => null,
        'address' => 'Legacy Loan Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'birthplace' => 'Cebu City',
        'birthplace_city' => 'Cebu City',
        'birthplace_province' => null,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-005',
        'lntype' => 'Salary/Pension',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.first_name', 'Loan')
            ->where('applicant.middle_name', 'L.')
            ->where('applicant.last_name', 'Legacy')
            ->where('applicant.birthplace', 'Cebu City')
            ->where('applicant.address', 'Legacy Loan Street')
            ->where('applicant.birthplace_city', 'Cebu City')
            ->where('applicant.birthplace_province', null)
            ->where('applicant.address1', 'Legacy Loan Street')
            ->where('applicant.address2', null)
            ->where('applicant.address3', null)
            ->where('applicantReadOnly.address1', true)
            ->where('applicantReadOnly.address2', false)
            ->where('applicantReadOnly.address3', false)
            ->where('applicantReadOnly.birthplace_city', false)
            ->where('applicantReadOnly.birthplace_province', false));
});

test('loan request form preserves member number of children values', function (
    $dependent,
    $profileChildren,
    $expected,
    bool $readOnly,
) {
    $user = User::factory()->create([
        'acctno' => '000721',
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
        'dependent' => $dependent,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'number_of_children' => $profileChildren,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-004',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.number_of_children', $expected)
            ->where('applicantReadOnly.number_of_children', $readOnly));
})->with([
    'zero dependents' => [0, 2, '0', true],
    'missing dependents uses profile' => [null, 4, '4', false],
    'non-zero dependents' => [3, 1, '3', true],
]);

test('loan request form falls back to profile children when dependent column is missing', function () {
    Schema::table('wmaster', function (Blueprint $table) {
        $table->dropColumn('dependent');
    });

    $user = User::factory()->create([
        'acctno' => '000723',
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
        'number_of_children' => 5,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-007',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.number_of_children', '5')
            ->where('applicantReadOnly.number_of_children', false));
});

test('loan request form falls back to profile spouse name when wmaster spouse is missing', function () {
    $user = User::factory()->create([
        'acctno' => '000724',
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
        'civilstat' => 'Married',
        'occupation' => 'Analyst',
        'spouse' => null,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'spouse_name' => 'Jamie Lee',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-008',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.spouse_name', 'Jamie Lee')
            ->where('applicantReadOnly.spouse_name', false));
});

test('loan request form locks spouse name when wmaster spouse exists', function () {
    $user = User::factory()->create([
        'acctno' => '000725',
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
        'civilstat' => 'Married',
        'occupation' => 'Analyst',
        'spouse' => 'Miguel Santos',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'spouse_name' => 'Jamie Lee',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-009',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.spouse_name', 'Miguel Santos')
            ->where('applicantReadOnly.spouse_name', true));
});

test('loan request form normalizes housing status values', function (
    ?string $restype,
    ?string $expected,
    bool $readOnly,
) {
    $user = User::factory()->create([
        'acctno' => '000722',
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
        'restype' => $restype,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-006',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.housing_status', $expected)
            ->where('applicantReadOnly.housing_status', $readOnly));
})->with([
    'owned value' => ['OWNED', 'OWNED', true],
    'owned label' => ['Owned', 'OWNED', true],
    'rent value' => ['RENT', 'RENT', true],
    'rental value' => ['RENTAL', 'RENT', true],
    'missing value' => [null, null, false],
]);

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
            'employment_type' => 'Private',
            'employer_business_name' => 'Loan Company',
            'employer_business_address1' => 'Loan City Center',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '3 years',
            'gross_monthly_income' => 25000,
            'payday' => '15th & 30th',
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
    expect(
        LoanRequestPerson::query()
            ->where('loan_request_id', $draft->id)
            ->where('role', LoanRequestPersonRole::Applicant)
            ->value('birthplace'),
    )->toBe('Manila, Metro Manila');
    expect(
        LoanRequestPerson::query()
            ->where('loan_request_id', $draft->id)
            ->where('role', LoanRequestPersonRole::Applicant)
            ->value('housing_status'),
    )->toBe('OWNED');

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
            'birthplace' => 'Quezon City',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('draft.id', $loanRequest->id)
            ->where('applicant.first_name', 'Draft')
            ->where('applicant.birthplace', 'Quezon City'));
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
            'gross_monthly_income' => 25000,
            'payday' => '15th & 30th',
        ],
        'co_maker_1' => [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address1' => 'Co Maker Street',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'length_of_stay' => '4 years',
            'housing_status' => 'RENT',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Co Maker Office',
            'employer_business_address1' => 'Co Maker Plaza',
            'employer_business_address2' => 'Cebu City',
            'employer_business_address3' => 'Cebu',
            'telephone_no' => '021234567',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => '30th',
        ],
        'co_maker_2' => [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address1' => 'Second Street',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'length_of_stay' => '2 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Second Store',
            'employer_business_address1' => 'Davao Store',
            'employer_business_address2' => 'Davao City',
            'employer_business_address3' => 'Davao del Sur',
            'telephone_no' => '021234567',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => '15th',
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
    $people = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->get()
        ->keyBy('role');
    expect($people[LoanRequestPersonRole::Applicant->value]->birthplace)->toBe('Manila, Metro Manila');
    expect($people[LoanRequestPersonRole::Applicant->value]->housing_status)->toBe('OWNED');
    expect($people[LoanRequestPersonRole::CoMakerOne->value]->birthplace)->toBe('Cebu, Cebu');
    expect($people[LoanRequestPersonRole::CoMakerOne->value]->housing_status)->toBe('RENT');
    expect($people[LoanRequestPersonRole::CoMakerTwo->value]->birthplace)->toBe('Davao, Davao del Sur');
    expect($people[LoanRequestPersonRole::CoMakerTwo->value]->housing_status)->toBe('OWNED');
});

test('loan request submission validates housing status values', function () {
    $user = User::factory()->create([
        'acctno' => '000722',
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
        'typecode' => 'LN-005',
        'lntype' => 'Personal',
    ]);

    $payload = [
        'typecode' => 'LN-005',
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
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Loan Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
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
            'employer_business_address1' => 'Loan City Center',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '3 years',
            'gross_monthly_income' => 25000,
            'payday' => '15th & 30th',
        ],
        'co_maker_1' => [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address1' => 'Co Maker Street',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'length_of_stay' => '4 years',
            'housing_status' => 'RENT',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Co Maker Office',
            'employer_business_address1' => 'Co Maker Plaza',
            'employer_business_address2' => 'Cebu City',
            'employer_business_address3' => 'Cebu',
            'telephone_no' => '021234567',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => '30th',
        ],
        'co_maker_2' => [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address1' => 'Second Street',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'length_of_stay' => '2 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Second Store',
            'employer_business_address1' => 'Davao Store',
            'employer_business_address2' => 'Davao City',
            'employer_business_address3' => 'Davao del Sur',
            'telephone_no' => '021234567',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => '15th',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors(['applicant.housing_status']);
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

test('loan request pdf download responds with an attachment', function () {
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
        ->get(route('client.loan-requests.pdf', [
            'loanRequest' => $loanRequest->id,
            'download' => 1,
        ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
});

test('loan request print preview renders for the owner', function () {
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
            'status' => LoanRequestStatus::Approved,
            'submitted_at' => now(),
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
        ->get(route('client.loan-requests.print', $loanRequest));

    $response->assertOk();
    $response->assertViewIs('reports.loan-request-print');
    $response->assertSee('APPLICATION FORM');
    $response->assertSee('&#10003;', false);
});

test('loan request print preview is not available for draft requests', function () {
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
        ->get(route('client.loan-requests.print', $loanRequest));

    $response->assertNotFound();
});

test('loan request print preview rejects non-owners', function () {
    $owner = User::factory()->create([
        'acctno' => '000745',
    ]);
    $viewer = User::factory()->create([
        'acctno' => '000746',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $viewer->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $viewer->acctno,
        'bname' => 'Viewer, Loan',
        'fname' => 'Viewer',
        'lname' => 'Loan',
        'birthday' => '1990-04-10',
        'address' => 'Loan Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $viewer->user_id,
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($owner)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'submitted_at' => now(),
        ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('client.loan-requests.print', $loanRequest));

    $response->assertNotFound();
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

test('admin requests api filters by loan type', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'loan_type_label_snapshot' => 'Salary/Pension',
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'loan_type_label_snapshot' => 'Personal',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?loanType=Personal');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.loan_type', 'Personal');
});

test('admin requests api filters by status and normalizes submitted requests', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Submitted,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?status=under_review');

    $response->assertOk()->assertJsonCount(2, 'data.items');

    $statuses = collect($response->json('data.items'))
        ->pluck('status')
        ->unique()
        ->values()
        ->all();

    expect($statuses)->toBe(['under_review']);
});

test('admin requests api filters by amount range', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'requested_amount' => 500,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'requested_amount' => 1500,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'requested_amount' => 9500,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?minAmount=1000&maxAmount=2000');

    $response->assertOk()->assertJsonCount(1, 'data.items');

    $amount = (float) $response->json('data.items.0.requested_amount');

    expect($amount)->toBe(1500.0);
});

test('admin requests api supports combined filters and search', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $first = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'loan_type_label_snapshot' => 'Personal',
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($first)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Loan',
            'last_name' => 'Smith',
        ]);

    $second = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'loan_type_label_snapshot' => 'Personal',
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($second)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Loan',
            'last_name' => 'Jones',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?search=Smith&loanType=Personal&status=approved');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $first->id);
});

test('admin requests api paginates filtered results', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    LoanRequest::factory()->count(3)->create([
        'status' => LoanRequestStatus::Approved,
        'loan_type_label_snapshot' => 'Salary/Pension',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?loanType=Salary/Pension&perPage=1&page=2');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.meta.page', 2)
        ->assertJsonPath('data.meta.perPage', 1)
        ->assertJsonPath('data.meta.total', 3);
});

test('non-admin users cannot access filtered requests api', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/spa/admin/requests?status=approved')
        ->assertForbidden();
});

test('admin can view loan request details page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Loan',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.show', $loanRequest));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/loan-request-show')
            ->where('loanRequest.id', $loanRequest->id)
            ->where('loanRequest.status', LoanRequestStatus::UnderReview->value)
            ->where('applicant.first_name', 'Loan'));
});

test('admin loan request pdf endpoint responds with a pdf', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
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
        ->actingAs($admin)
        ->get(route('admin.requests.pdf', $loanRequest));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('admin loan request pdf download responds with an attachment', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
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
        ->actingAs($admin)
        ->get(route('admin.requests.pdf', [
            'loanRequest' => $loanRequest->id,
            'download' => 1,
        ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toStartWith('attachment;');
});

test('admin loan request print preview renders', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
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
        ->actingAs($admin)
        ->get(route('admin.requests.print', $loanRequest));

    $response->assertOk();
    $response->assertViewIs('reports.loan-request-print');
    $response->assertSee('APPLICATION FORM');
});

test('admin loan request print preview normalizes uppercase text fields', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
        'loan_type_label_snapshot' => 'SALARY LOAN',
        'loan_purpose' => 'HOME REPAIR',
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'birthplace' => 'DAVAO CITY',
            'address' => 'PUROK 1',
            'spouse_name' => 'MARIA CRUZ',
            'employer_business_name' => 'ACME CORP',
            'employer_business_address' => 'MAIN ROAD',
            'current_position' => 'SENIOR ANALYST',
            'nature_of_business' => 'FINANCE',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'ANA',
            'last_name' => 'LIM',
            'birthplace' => 'CEBU CITY',
            'address' => 'MANGO STREET',
            'employer_business_name' => 'ALPHA TRADERS',
            'employer_business_address' => 'CEBU AVE',
            'current_position' => 'ACCOUNTANT',
            'nature_of_business' => 'RETAIL',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'BEN',
            'last_name' => 'REYES',
            'birthplace' => 'BACOLOD CITY',
            'address' => 'LACSON ST',
            'employer_business_name' => 'BETA SERVICES',
            'employer_business_address' => 'BACOLOD RD',
            'current_position' => 'SUPERVISOR',
            'nature_of_business' => 'SERVICES',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.print', $loanRequest));

    $response->assertOk();
    $response->assertViewIs('reports.loan-request-print');
    $response->assertSee('Juan');
    $response->assertSee('Dela Cruz');
    $response->assertSee('Davao City');
    $response->assertSee('Purok 1');
    $response->assertSee('Maria Cruz');
    $response->assertSee('Acme Corp');
    $response->assertSee('Main Road');
    $response->assertSee('Senior Analyst');
    $response->assertSee('Finance');
    $response->assertSee('Salary Loan');
    $response->assertSee('Home Repair');
});

test('non-admin users cannot access admin loan request routes', function () {
    $user = User::factory()->create();
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $this->actingAs($user)
        ->get(route('admin.requests.show', $loanRequest))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.requests.pdf', $loanRequest))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.requests.print', $loanRequest))
        ->assertForbidden();
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
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Sample',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.show', $loanRequest));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request-show')
            ->where('loanRequest.id', $loanRequest->id)
            ->where('loanRequest.status', LoanRequestStatus::UnderReview->value)
            ->where('applicant.first_name', 'Sample'));
});

test('client cannot view another member loan request details', function () {
    $owner = User::factory()->create([
        'acctno' => '000731',
    ]);
    $viewer = User::factory()->create([
        'acctno' => '000732',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $viewer->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $viewer->acctno,
        'bname' => 'Viewer, Loan',
        'fname' => 'Viewer',
        'lname' => 'Loan',
        'birthday' => '1990-04-10',
        'address' => 'Loan Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $viewer->user_id,
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($owner)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'submitted_at' => now(),
        ]);

    $response = $this
        ->actingAs($viewer)
        ->get(route('client.loan-requests.show', $loanRequest));

    $response->assertNotFound();
});
