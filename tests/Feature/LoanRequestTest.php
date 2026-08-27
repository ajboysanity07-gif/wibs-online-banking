<?php

use App\Jobs\SendLoanDecisionSmsJob;
use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestCorrectionReport;
use App\Models\LoanRequestDocument;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\OrganizationSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestPdfService;
use App\Services\LoanRequests\OfficialLoanManagerResolver;
use App\Services\OrganizationSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validLoanRequestCorrectionPayload(array $overrides = []): array
{
    DB::table('wlntype')->updateOrInsert(
        ['typecode' => 'LN-COR'],
        ['lntype' => 'Corrected Personal'],
    );

    $payload = [
        'change_reason' => 'Corrected submitted request details.',
        'typecode' => 'LN-COR',
        'requested_amount' => 23000,
        'requested_term' => 18,
        'loan_purpose' => 'Corrected purpose',
        'availment_status' => 'Re-Loan',
        'applicant' => [
            'first_name' => 'Corrected',
            'last_name' => 'Applicant',
            'middle_name' => 'A',
            'nickname' => 'CA',
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Corrected Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
            'length_of_stay' => '6 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09123456789',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'number_of_children' => 2,
            'spouse_name' => 'Corrected Spouse',
            'spouse_age' => 35,
            'spouse_cell_no' => '09123456780',
            'employment_type' => 'Private',
            'employer_business_name' => 'Corrected Company',
            'employer_business_address1' => 'Corrected Center',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Supervisor',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '5 years',
            'employer_date_employed' => '2017-05-20',
            'gross_monthly_income' => 32000,
            'payday' => 'Quincenal',
        ],
        'co_maker_1' => [
            'first_name' => 'Corrected',
            'last_name' => 'CoMakerOne',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address1' => 'Corrected Co One Street',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'length_of_stay' => '4 years',
            'housing_status' => 'RENT',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Corrected Office One',
            'employer_business_address1' => 'Corrected Plaza',
            'employer_business_address2' => 'Cebu City',
            'employer_business_address3' => 'Cebu',
            'telephone_no' => '021234568',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => 'Quincenal',
        ],
        'co_maker_2' => [
            'first_name' => 'Corrected',
            'last_name' => 'CoMakerTwo',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address1' => 'Corrected Co Two Street',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'length_of_stay' => '3 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Corrected Store Two',
            'employer_business_address1' => 'Corrected Store',
            'employer_business_address2' => 'Davao City',
            'employer_business_address3' => 'Davao del Sur',
            'telephone_no' => '021234569',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => 'Quincenal',
        ],
        'dependents' => [
            'applicant_cycle_status' => 'New',
            'dependent_spouse_cycle_status' => 'New',
        ],
    ];

    return array_replace_recursive($payload, $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validLoanRequestMemberSectionPayload(array $overrides = []): array
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
        'barangay' => [
            'barangay_official_designation' => null,
            'barangay_agency_name' => null,
            'barangay_agency_address' => null,
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

function sampleSignatureDataUrl(string $variant = 'one'): string
{
    $base64 = match ($variant) {
        'two' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=',
        default => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=',
    };

    return 'data:image/png;base64,'.$base64;
}

function createLoanRequestPeopleSnapshots(LoanRequest $loanRequest): void
{
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Original',
            'last_name' => 'Applicant',
            'birthplace_city' => 'Old City',
            'birthplace_province' => 'Old Province',
            'address1' => 'Old Street',
            'address2' => 'Old City',
            'address3' => 'Old Province',
            'employer_business_name' => 'Old Company',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Original',
            'last_name' => 'CoMakerOne',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'Original',
            'last_name' => 'CoMakerTwo',
        ]);
}

test('loan request people schema excludes spouse occupation', function () {
    expect(Schema::hasColumn('loan_request_people', 'spouse_occupation'))->toBeFalse();
});

test('loan request reference uses the formatted request number', function () {
    $loanRequest = LoanRequest::factory()->create();

    expect($loanRequest->reference)->toBe(sprintf('LNREQ-%06d', $loanRequest->id));
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
        'address1' => '123 Main Street',
        'address2' => 'San Lorenzo',
        'address3' => 'Makati',
        'address4' => 'Metro Manila',
        'zone_number' => '1234',
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
            ->where('applicant.address', '123 Main Street, San Lorenzo, Makati, Metro Manila')
            ->where('applicant.address1', '123 Main Street')
            ->where('applicant.address2', 'Makati')
            ->where('applicant.address3', 'Metro Manila')
            ->where('applicant.address_zip', '1234')
            ->where('applicantReadOnly.address1', true)
            ->where('applicantReadOnly.address2', true)
            ->where('applicantReadOnly.address3', true)
            ->where('applicantReadOnly.address_zip', true)
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
        'employer_business_address_zip' => '8100',
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
            ->where('applicant.employer_business_address_zip', '8100')
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
        'home_address1' => null,
        'home_address_barangay' => null,
        'home_address2' => null,
        'home_address3' => null,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-005',
        'lntype' => 'Salary/Pension',
    ]);

    $service = app(\App\Services\LoanRequests\LoanRequestService::class);
    $payload = $service->getFormData($user);

    expect($payload['applicant']['first_name'])->toBe('Loan');
    expect($payload['applicant']['middle_name'])->toBe('L.');
    expect($payload['applicant']['last_name'])->toBe('Legacy');
    expect($payload['applicant']['birthplace'])->toBe('Cebu City');
    expect($payload['applicant']['address'])->toBe('Legacy Loan Street');
    expect($payload['applicant']['birthplace_city'])->toBe('Cebu City');
    expect($payload['applicant']['birthplace_province'])->toBeNull();
    expect($payload['applicant']['address1'])->toBe('Legacy Loan Street');
    expect($payload['applicant']['address2'])->toBeNull();
    expect($payload['applicant']['address3'])->toBeNull();
    expect($payload['applicantReadOnly']['address1'])->toBeTrue();
    expect($payload['applicantReadOnly']['address2'])->toBeFalse();
    expect($payload['applicantReadOnly']['address3'])->toBeFalse();
    expect($payload['applicantReadOnly']['birthplace_city'])->toBeFalse();
    expect($payload['applicantReadOnly']['birthplace_province'])->toBeFalse();
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

test('loan request form falls back to profile civil status when wmaster civil status is missing', function () {
    $user = User::factory()->create([
        'acctno' => '000726',
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
        'civilstat' => null,
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'civil_status' => 'Married',
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
            ->where('applicant.civil_status', 'Married')
            ->where('applicantReadOnly.civil_status', false));
});

test('loan request form falls back to profile housing status when wmaster housing status is missing', function () {
    $user = User::factory()->create([
        'acctno' => '000727',
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
        'restype' => null,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'housing_status' => 'RENT',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-011',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.housing_status', 'RENT')
            ->where('applicantReadOnly.housing_status', false));
});

test('loan request form keeps wmaster civil and housing status over profile values', function () {
    $user = User::factory()->create([
        'acctno' => '000728',
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
        'restype' => 'OWNED',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'civil_status' => 'Married',
        'housing_status' => 'RENT',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-012',
        'lntype' => 'Personal',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.create'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request')
            ->where('applicant.civil_status', 'Single')
            ->where('applicantReadOnly.civil_status', true)
            ->where('applicant.housing_status', 'OWNED')
            ->where('applicantReadOnly.housing_status', true));
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
    'rented label' => ['Rented', 'RENT', true],
    'missing value uses profile' => [null, 'OWNED', false],
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
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000,
            'payday' => 'Quincenal',
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

test('clients can save applicant PEP status and cycle status via the loan request draft', function () {
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
    DB::table('wlntype')->insert([
        'typecode' => 'LN-004',
        'lntype' => 'Personal',
    ]);

    $payload = [
        'typecode' => 'LN-004',
        'requested_amount' => 12000,
        'requested_term' => 10,
        'loan_purpose' => 'Home repair',
        'availment_status' => 'New',
        'health_glapi' => [
            'applicant_pep_status' => true,
            'applicant_pep_status_details' => 'Barangay Councilor, since 2020',
        ],
        'dependents' => [
            'applicant_cycle_status' => 'Old',
            'applicant_cycle_number' => 3,
        ],
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
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000,
            'payday' => 'Quincenal',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->patch(route('client.loan-requests.draft'), $payload);

    $response->assertRedirect(route('client.loan-requests.create'));

    $draft = LoanRequest::query()->first();
    $flatValues = app(App\Services\LoanRequests\LoanRequestDataService::class)
        ->loadFlatValues($draft);

    expect($flatValues['applicant_pep_status'])->toBeTrue()
        ->and($flatValues['applicant_pep_status_details'])->toBe('Barangay Councilor, since 2020')
        ->and($flatValues['applicant_cycle_status'])->toBe('Old')
        ->and((int) $flatValues['applicant_cycle_number'])->toBe(3);
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
            'birthdate' => '1990-04-10',
            'birthplace' => 'Quezon City',
            'housing_status' => 'Rented',
            'civil_status' => 'MARRIED',
            'payday' => '15/30',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Draft',
            'last_name' => 'CoMakerOne',
            'birthdate' => '1989-03-12',
            'housing_status' => 'Rental',
            'civil_status' => 'Single',
            'payday' => 'weekly',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'Draft',
            'last_name' => 'CoMakerTwo',
            'birthdate' => '1987-02-12',
            'housing_status' => 'Owned',
            'civil_status' => 'WIDOWED',
            'payday' => 'Biweekly',
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
            ->where('applicant.birthplace', 'Quezon City')
            ->where('applicant.birthdate', '1990-04-10')
            ->where('applicant.housing_status', 'RENT')
            ->where('applicant.civil_status', 'Married')
            ->where('applicant.payday', 'Quincenal')
            ->where('coMakerOne.birthdate', '1989-03-12')
            ->where('coMakerOne.housing_status', 'RENT')
            ->where('coMakerOne.payday', 'Weekly')
            ->where('coMakerTwo.birthdate', '1987-02-12')
            ->where('coMakerTwo.housing_status', 'OWNED')
            ->where('coMakerTwo.civil_status', 'Widowed')
            ->where('coMakerTwo.payday', 'Weekly'));
});

test('loan request submissions persist snapshots and enter pending review', function () {
    Storage::fake('public');

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
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
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
        ...validLoanRequestMemberSectionPayload(),
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
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000,
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview);
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
    expect($people[LoanRequestPersonRole::CoMakerOne->value]->housing_status)->toBeNull();
    expect($people[LoanRequestPersonRole::CoMakerTwo->value]->birthplace)->toBe('Davao, Davao del Sur');
    expect($people[LoanRequestPersonRole::CoMakerTwo->value]->housing_status)->toBeNull();
});

test('Other Loan submission requires a loan name', function () {
    Storage::fake('public');

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
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => '01',
        'lntype' => 'OTHER LOAN',
    ]);

    $payload = [
        'typecode' => '01',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
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
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000,
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors('other_loan_type_name');
    expect(LoanRequest::query()->count())->toBe(0);
});

test('Other Loan submission with a loan name persists and is exposed to staff', function () {
    Storage::fake('public');

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
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => '01',
        'lntype' => 'OTHER LOAN',
    ]);

    $payload = [
        'typecode' => '01',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'other_loan_type_name' => 'Motorcycle Loan',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
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
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000,
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->other_loan_type_name)->toBe('Motorcycle Loan');

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    Role::ensureWorkflowDefaults();
    Role::attachNamedRole($admin, Role::LOAN_PROCESSOR);

    $this
        ->actingAs($admin)
        ->get(route('admin.requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanRequest.other_loan_type_name', 'Motorcycle Loan'));
});

test('salary deduction is rejected for a non-institutional employer', function () {
    $user = User::factory()->create(['acctno' => '000712']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
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
    DB::table('wlntype')->insert(['typecode' => 'LN-003', 'lntype' => 'Personal']);

    $payload = [
        'typecode' => 'LN-003',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload([
            'banking' => ['payment_option' => 'Salary Deduction'],
        ]),
        'applicant' => [
            'first_name' => 'Loan', 'last_name' => 'Member', 'middle_name' => 'Q',
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila', 'birthplace_province' => 'Metro Manila',
            'address1' => 'Loan Street', 'address2' => 'Manila', 'address3' => 'Metro Manila',
            'length_of_stay' => '5 years', 'housing_status' => 'OWNED',
            'cell_no' => '09123456789', 'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'employment_type' => 'Private',
            'employer_business_name' => 'Some Private Company',
            'employer_business_address1' => 'Loan City Center',
            'employer_business_address2' => 'Manila', 'employer_business_address3' => 'Metro Manila',
            'current_position' => 'Analyst', 'nature_of_business' => 'Finance',
            'years_in_work_business' => '3 years', 'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000, 'payday' => 'Quincenal',
        ],
    ];

    $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload)
        ->assertSessionHasErrors(['banking.payment_option']);

    expect(LoanRequest::query()->count())->toBe(0);
});

test('salary deduction is accepted for an institutional (MRDINC) employer', function () {
    $user = User::factory()->create(['acctno' => '000713']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
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
    DB::table('wlntype')->insert(['typecode' => 'LN-004', 'lntype' => 'Personal']);

    $payload = [
        'typecode' => 'LN-004',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload([
            'banking' => ['payment_option' => 'Salary Deduction'],
        ]),
        'applicant' => [
            'first_name' => 'Loan', 'last_name' => 'Member', 'middle_name' => 'Q',
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila', 'birthplace_province' => 'Metro Manila',
            'address1' => 'Loan Street', 'address2' => 'Manila', 'address3' => 'Metro Manila',
            'length_of_stay' => '5 years', 'housing_status' => 'OWNED',
            'cell_no' => '09123456789', 'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'number_of_children' => 0,
            'employment_type' => 'Private',
            'employer_business_name' => 'MRDINC Head Office',
            'employer_business_address1' => 'Loan City Center',
            'employer_business_address2' => 'Manila', 'employer_business_address3' => 'Metro Manila',
            'current_position' => 'Analyst', 'nature_of_business' => 'Finance',
            'years_in_work_business' => '3 years', 'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000, 'payday' => 'Quincenal',
        ],
        'co_maker_1' => [
            'first_name' => 'Co', 'last_name' => 'Maker', 'middle_name' => 'One',
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu', 'birthplace_province' => 'Cebu',
            'address1' => 'Co Maker Street', 'address2' => 'Cebu City', 'address3' => 'Cebu',
            'length_of_stay' => '4 years', 'housing_status' => 'RENT',
            'cell_no' => '09998887777', 'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Co Maker Office',
            'employer_business_address1' => 'Co Maker Plaza',
            'employer_business_address2' => 'Cebu City', 'employer_business_address3' => 'Cebu',
            'current_position' => 'Clerk', 'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000, 'payday' => 'Quincenal',
        ],
        'co_maker_2' => [
            'first_name' => 'Second', 'last_name' => 'Maker', 'middle_name' => 'Two',
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao', 'birthplace_province' => 'Davao del Sur',
            'address1' => 'Second Street', 'address2' => 'Davao City', 'address3' => 'Davao del Sur',
            'length_of_stay' => '2 years', 'housing_status' => 'OWNED',
            'cell_no' => '09111112222', 'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Second Store',
            'employer_business_address1' => 'Davao Store',
            'employer_business_address2' => 'Davao City', 'employer_business_address3' => 'Davao del Sur',
            'current_position' => 'Owner', 'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000, 'payday' => 'Quincenal',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionDoesntHaveErrors(['banking.payment_option']);
    expect(LoanRequest::query()->count())->toBe(1);
});

function pensionerPersonPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'first_name' => 'Pension',
        'last_name' => 'Member',
        'middle_name' => null,
        'nickname' => null,
        'birthdate' => '1960-05-15',
        'birthplace_city' => 'Manila',
        'birthplace_province' => 'Metro Manila',
        'address1' => 'Pension Street',
        'address2' => 'Manila',
        'address3' => 'Metro Manila',
        'length_of_stay' => '20 years',
        'housing_status' => 'OWNED',
        'cell_no' => '09111111111',
        'civil_status' => 'Widowed',
        'educational_attainment' => 'College',
        'number_of_children' => 2,
        'spouse_name' => null,
        'spouse_age' => null,
        'spouse_cell_no' => null,
        'employment_type' => 'Pensioner',
        'employer_business_name' => '',
        'employer_business_address1' => '',
        'employer_business_address2' => '',
        'employer_business_address3' => '',
        'telephone_no' => null,
        'current_position' => '',
        'nature_of_business' => '',
        'years_in_work_business' => '',
        'gross_monthly_income' => 15000,
        'payday' => 'Quincenal',
    ], $overrides);
}

test('pensioner applicant may submit without employer fields', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000750']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Pension',
        'fname' => 'Pension',
        'lname' => 'Member',
        'birthday' => '1960-05-15',
        'address' => 'Pension Street',
        'civilstat' => 'Widowed',
        'occupation' => 'Pensioner',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-PEN', 'lntype' => 'Pensioner Loan']);

    $coMakerPayload = [
        'first_name' => 'Co',
        'last_name' => 'Maker',
        'middle_name' => null,
        'nickname' => null,
        'birthdate' => '1985-06-20',
        'birthplace_city' => 'Cebu',
        'birthplace_province' => 'Cebu',
        'address1' => 'Co Street',
        'address2' => 'Cebu City',
        'address3' => 'Cebu',
        'length_of_stay' => '5 years',
        'housing_status' => 'RENT',
        'cell_no' => '09222222222',
        'civil_status' => 'Single',
        'educational_attainment' => 'College',
        'employment_type' => 'Government',
        'employer_business_name' => 'GSIS Office',
        'employer_business_address1' => 'GSIS Plaza',
        'employer_business_address2' => 'Cebu City',
        'employer_business_address3' => 'Cebu',
        'telephone_no' => null,
        'current_position' => 'Clerk',
        'nature_of_business' => 'Government',
        'years_in_work_business' => '10 years',
        'gross_monthly_income' => 18000,
        'payday' => 'Quincenal',
    ];

    $payload = [
        'typecode' => 'LN-PEN',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => pensionerPersonPayload(),
        'co_maker_1' => $coMakerPayload,
        'co_maker_2' => array_merge($coMakerPayload, [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'cell_no' => '09333333333',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'housing_status' => 'OWNED',
        ]),
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview);
});

test('non-pensioner applicant fails validation when employer fields are empty', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000751']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Private',
        'fname' => 'Private',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Private Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-PRV', 'lntype' => 'Private Loan']);

    $payload = [
        'typecode' => 'LN-PRV',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Home repair',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => pensionerPersonPayload(['employment_type' => 'Private']),
        'co_maker_1' => [],
        'co_maker_2' => [],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors(['applicant.employer_business_name']);
});

test('OFW applicant fails validation when employer fields are empty', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000752']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, OFW',
        'fname' => 'OFW',
        'lname' => 'Member',
        'birthday' => '1988-07-22',
        'address' => 'OFW Street',
        'civilstat' => 'Married',
        'occupation' => 'OFW',
    ]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-OFW', 'lntype' => 'OFW Loan']);

    $payload = [
        'typecode' => 'LN-OFW',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Home repair',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => pensionerPersonPayload(['employment_type' => 'OFW']),
        'co_maker_1' => [],
        'co_maker_2' => [],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors(['applicant.employer_business_name']);
});

test('non-pensioner applicant fails validation when employer date employed is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000753']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Private',
        'fname' => 'Private',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Private Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-PRV', 'lntype' => 'Private Loan']);

    $payload = [
        'typecode' => 'LN-PRV',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Home repair',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => array_merge(pensionerPersonPayload(['employment_type' => 'Private']), [
            'employer_date_employed' => '',
        ]),
        'co_maker_1' => [],
        'co_maker_2' => [],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors(['applicant.employer_date_employed']);
});

test('applicant date employed is stored and co-makers do not require it', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000754']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Private',
        'fname' => 'Private',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Private Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-PRV', 'lntype' => 'Private Loan']);

    $payload = [
        'typecode' => 'LN-PRV',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Home repair',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => array_merge(pensionerPersonPayload(['employment_type' => 'Private']), [
            'employer_date_employed' => '2019-06-01',
            'employer_business_name' => 'Loan Company',
            'employer_business_address1' => 'Loan City Center',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '5 years',
        ]),
        'co_maker_1' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'cell_no' => '09222222222',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'gross_monthly_income' => 18000,
        ]),
        'co_maker_2' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'cell_no' => '09333333333',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'gross_monthly_income' => 16000,
        ]),
    ];

    $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload)
        ->assertSessionHasNoErrors();

    $loanRequest = LoanRequest::query()->first();
    $applicant = $loanRequest->people()
        ->where('role', LoanRequestPersonRole::Applicant->value)
        ->first();

    expect($applicant->employer_date_employed->toDateString())->toBe('2019-06-01');
});

test('self-employed applicant may submit without a date employed', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000755']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Owner',
        'fname' => 'Owner',
        'lname' => 'Member',
        'birthday' => '1985-06-20',
        'address' => 'Business Street',
        'civilstat' => 'Married',
        'occupation' => 'Owner',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-SE', 'lntype' => 'Self Employed Loan']);

    $payload = [
        'typecode' => 'LN-SE',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Business expansion',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => array_merge(pensionerPersonPayload(['employment_type' => 'Self Employed']), [
            'employer_date_employed' => '',
            'employer_business_name' => 'Owner Store',
            'employer_business_address1' => 'Market Street',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '3 years',
        ]),
        'co_maker_1' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'cell_no' => '09222222222',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'gross_monthly_income' => 18000,
        ]),
        'co_maker_2' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'cell_no' => '09333333333',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'gross_monthly_income' => 16000,
        ]),
    ];

    $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload)
        ->assertSessionHasNoErrors();

    $loanRequest = LoanRequest::query()->first();
    $applicant = $loanRequest->people()
        ->where('role', LoanRequestPersonRole::Applicant->value)
        ->first();

    expect($applicant->employer_date_employed)->toBeNull();
    expect($applicant->employment_type)->toBe('Self Employed');
});

test('self-employed applicant with hyphenated employment_type may submit without a date employed', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000757']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Owner',
        'fname' => 'Owner',
        'lname' => 'Member',
        'birthday' => '1985-06-20',
        'address' => 'Business Street',
        'civilstat' => 'Married',
        'occupation' => 'Owner',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-SE2', 'lntype' => 'Self Employed Loan Two']);

    $payload = [
        'typecode' => 'LN-SE2',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Business expansion',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => array_merge(pensionerPersonPayload(['employment_type' => 'Self-Employed']), [
            'employer_date_employed' => '',
            'employer_business_name' => 'Owner Store',
            'employer_business_address1' => 'Market Street',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '3 years',
        ]),
        'co_maker_1' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'cell_no' => '09222222222',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'gross_monthly_income' => 18000,
        ]),
        'co_maker_2' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'cell_no' => '09333333333',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'gross_monthly_income' => 16000,
        ]),
    ];

    $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload)
        ->assertSessionHasNoErrors();

    $loanRequest = LoanRequest::query()->first();
    $applicant = $loanRequest->people()
        ->where('role', LoanRequestPersonRole::Applicant->value)
        ->first();

    expect($applicant->employer_date_employed)->toBeNull();
});

test('submitting free-text fields in ALL CAPS normalizes them to title case on save', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000758']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Cruz, Juan',
        'fname' => 'Juan',
        'lname' => 'Cruz',
        'birthday' => '1990-01-01',
        'address' => 'Business Street',
        'civilstat' => 'Single',
        'occupation' => 'Owner',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(['user_id' => $user->user_id]);
    DB::table('wlntype')->insert(['typecode' => 'LN-CAPS', 'lntype' => 'Caps Loan']);

    $payload = [
        'typecode' => 'LN-CAPS',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Business expansion',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => array_merge(pensionerPersonPayload(['employment_type' => 'Self Employed']), [
            'first_name' => 'JUAN',
            'last_name' => 'DELA CRUZ',
            'employer_date_employed' => '',
            'employer_business_name' => "CECIL'S DE GRACIA PHARMACY",
            'employer_business_address1' => 'MARKET STREET',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'current_position' => 'OWNER',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '3 years',
        ]),
        'co_maker_1' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'cell_no' => '09222222222',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'gross_monthly_income' => 18000,
        ]),
        'co_maker_2' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'cell_no' => '09333333333',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'gross_monthly_income' => 16000,
        ]),
    ];

    $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload)
        ->assertSessionHasNoErrors();

    $loanRequest = LoanRequest::query()->first();
    $applicant = $loanRequest->people()
        ->where('role', LoanRequestPersonRole::Applicant->value)
        ->first();

    expect($applicant->first_name)->toBe('Juan');
    expect($applicant->last_name)->toBe('Dela Cruz');
    expect($applicant->employer_business_name)->toBe("Cecil'S De Gracia Pharmacy");
    expect($applicant->current_position)->toBe('Owner');
});

test('loan request form prefills date employed from the member profile', function () {
    $user = User::factory()->create([
        'acctno' => '000756',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Private',
        'fname' => 'Private',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Private Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
        'employer_date_employed' => '2018-02-14',
    ]);

    $service = app(\App\Services\LoanRequests\LoanRequestService::class);
    $payload = $service->getFormData($user);

    expect($payload['applicant']['employer_date_employed'])->toBe('2018-02-14');
});

test('applicant legacy location values need not be selected from the PSGC suggestions', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000760']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Legacy',
        'fname' => 'Legacy',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Legacy Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $user->user_id,
        'home_address1' => 'Legacy Street',
        'home_address_barangay' => 'Aglipay',
        'home_address2' => 'Batac City',
        'home_address3' => 'Ilocos Norte',
        'housing_status' => 'OWNED',
        'employer_business_address_barangay' => 'Aglipay',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-LGC',
        'lntype' => 'Legacy Loan',
    ]);

    $coMakerPayload = [
        'first_name' => 'Co',
        'last_name' => 'Maker',
        'middle_name' => null,
        'nickname' => null,
        'birthdate' => '1985-06-20',
        'birthplace_city' => 'Cebu',
        'birthplace_province' => 'Cebu',
        'address1' => 'Co Street',
        'address2' => 'Cebu City',
        'address3' => 'Cebu',
        'length_of_stay' => '5 years',
        'housing_status' => 'RENT',
        'cell_no' => '09222222222',
        'civil_status' => 'Single',
        'educational_attainment' => 'College',
        'employment_type' => 'Government',
        'employer_business_name' => 'GSIS Office',
        'employer_business_address1' => 'GSIS Plaza',
        'employer_business_address2' => 'Cebu City',
        'employer_business_address3' => 'Cebu',
        'telephone_no' => null,
        'current_position' => 'Clerk',
        'nature_of_business' => 'Government',
        'years_in_work_business' => '10 years',
        'gross_monthly_income' => 18000,
        'payday' => 'Quincenal',
    ];

    $payload = [
        'typecode' => 'LN-LGC',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Personal',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => [
            'first_name' => 'Legacy',
            'last_name' => 'Member',
            'middle_name' => 'L',
            'nickname' => null,
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Old City',
            'birthplace_province' => 'Old Province',
            'address1' => 'Legacy Street',
            'address_barangay' => 'Old Barangay',
            'address2' => 'Old City',
            'address3' => 'Old Province',
            'length_of_stay' => '5 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09123456789',
            'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'number_of_children' => 2,
            'employment_type' => 'Private',
            'employer_business_name' => 'Legacy Company',
            'employer_business_address1' => 'Legacy Center',
            'employer_business_address_barangay' => 'Old Barangay',
            'employer_business_address2' => 'Old City',
            'employer_business_address3' => 'Old Province',
            'telephone_no' => null,
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '5 years',
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 30000,
            'payday' => 'Quincenal',
        ],
        'co_maker_1' => $coMakerPayload,
        'co_maker_2' => array_merge($coMakerPayload, [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'cell_no' => '09333333333',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'housing_status' => 'OWNED',
        ]),
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $loanRequest = LoanRequest::query()->first();

    $response->assertSessionHasNoErrors();
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview);
});

test('co-maker birthplace must still be selected from the PSGC suggestions', function () {
    Storage::fake('public');

    $user = User::factory()->create(['acctno' => '000761']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Strict',
        'fname' => 'Strict',
        'lname' => 'Member',
        'birthday' => '1990-04-10',
        'address' => 'Strict Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $user->user_id,
        'home_address1' => 'Strict Street',
        'home_address_barangay' => 'Aglipay',
        'home_address2' => 'Batac City',
        'home_address3' => 'Ilocos Norte',
        'housing_status' => 'OWNED',
        'employer_business_address_barangay' => 'Aglipay',
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-STR',
        'lntype' => 'Strict Loan',
    ]);

    $payload = [
        'typecode' => 'LN-STR',
        'requested_amount' => 10000,
        'requested_term' => 12,
        'loan_purpose' => 'Personal',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        ...validLoanRequestMemberSectionPayload(),
        'applicant' => pensionerPersonPayload(),
        'co_maker_1' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'cell_no' => '09222222222',
            'birthplace_city' => 'Old City',
            'birthplace_province' => 'Old Province',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'housing_status' => 'RENT',
            'gross_monthly_income' => 18000,
        ]),
        'co_maker_2' => array_merge(pensionerPersonPayload(), [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'cell_no' => '09333333333',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'housing_status' => 'OWNED',
            'gross_monthly_income' => 16000,
        ]),
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors(['co_maker_1.birthplace_city']);
});

test('legacy applicant signature payload is ignored when signatures are collected physically', function () {
    Storage::fake('public');

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
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wlntype')->insert([
        'typecode' => 'LN-008',
        'lntype' => 'Personal',
    ]);

    $payload = [
        'typecode' => 'LN-008',
        'requested_amount' => 12000,
        'requested_term' => 10,
        'loan_purpose' => 'Home repair',
        'availment_status' => 'New',
        'applicant_signature_data' => sampleSignatureDataUrl('one'),
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
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000,
            'payday' => 'Quincenal',
        ],
    ];

    $this
        ->actingAs($user)
        ->patch(route('client.loan-requests.draft'), $payload)
        ->assertRedirect(route('client.loan-requests.create'));

    $loanRequest = LoanRequest::query()->sole();

    expect(Storage::disk('public')->allFiles('loan-requests/signatures'))->toBe([]);

    $payload['applicant_signature_data'] = sampleSignatureDataUrl('two');
    $payload['loan_purpose'] = 'Tuition';

    $this
        ->actingAs($user)
        ->patch(route('client.loan-requests.draft'), $payload)
        ->assertRedirect(route('client.loan-requests.create'));

    expect($loanRequest->fresh()->loan_purpose)->toBe('Tuition');
    expect(Storage::disk('public')->allFiles('loan-requests/signatures'))->toBe([]);
});

test('loan request print preview renders blank wet-ink signature areas', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $reviewer = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $reviewer->user_id,
        'fullname' => 'System Approver',
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
            'reviewed_by' => $reviewer->user_id,
            'reviewed_at' => now(),
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
    $response->assertDontSee('data:image/png;base64,', false);
    $response->assertSeeInOrder([
        'signature-signing-space',
        'signature-name',
        'signature-line',
        'signature-label',
    ], false);

    $content = $response->getContent();

    // Strip the embedded Calibri font-face payload before the text assertions
    // below: it is a multi-megabyte base64 blob, and short substrings like
    // "N/A" turn up in it by pure chance, unrelated to the actual rendered
    // fields this test is guarding.
    $content = is_string($content)
        ? preg_replace('#data:font/ttf;base64,[A-Za-z0-9+/=]+#', 'data:font/ttf;base64,STRIPPED', $content)
        : $content;

    expect($content)->not->toBeFalse();
    expect($loanRequest->fresh()->reviewed_by)->toBe($reviewer->user_id);
    expect($content)->toContain('Loan Manager / Approved By');
    expect($content)->toContain('Annabelle M. Amora');
    expect($content)->toContain('ANNABELLE M. AMORA');
    expect($content)->not->toContain('ANNABELLE MONGADO AMORA');
    expect($content)->not->toContain('N/A');
    expect($content)->not->toContain('System Approver');
    expect($content)->toContain('@page {');
    expect($content)->toContain('@media print {');
    expect($content)->toContain('size: 8.5in 13in;');
    expect($content)->toContain('margin: 0.5in;');
    expect($content)->toContain('font-size: 9pt;');
    expect($content)->toContain('display: flex;');
    expect($content)->toContain('flex-direction: column;');
    expect($content)->toContain('width: 7.5in;');
    expect($content)->toContain('min-height: 12in;');
    expect($content)->toContain('margin: 0 auto;');
    expect($content)->toContain('padding: 8px 10px 10px;');
    expect($content)->toContain('max-height: 75px;');
    expect($content)->toContain('padding: 2px 5px;');
    expect($content)->toContain('font-size: 7.5pt;');
    expect($content)->toContain('font-size: 8.5pt;');
    expect($content)->toContain('font-size: 8.2pt;');
    expect($content)->toContain('font-size: 7.8pt;');
    expect($content)->toContain('font-size: 8pt;');
    expect($content)->toContain('line-height: 1.2;');
    expect($content)->toContain('margin-top: 5px;');
    expect($content)->toContain('height: 10px;');
    expect($content)->toContain('font-size: 9pt;');
    expect($content)->toContain('line-height: 1;');
    expect($content)->toContain('font-size: 8pt;');
    expect($content)->toContain('margin: 0 0 1px;');
    expect($content)->toContain('margin-top: 2px;');
    expect($content)->toContain('window.print();');
    expect(substr_count($content, 'class="signature-signing-space"'))->toBe(4);
    expect(substr_count($content, 'class="signature-line"'))->toBe(4);
    expect(
        preg_match('/\\.signature-name\\s*\\{[^}]*font-size:\\s*8\\.5pt;/s', $content),
    )->toBe(1);
});

test('loan request application form pdf stays on one long bond page', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::Approved,
            'submitted_at' => now(),
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Ana',
            'last_name' => 'Lim',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'Ben',
            'last_name' => 'Reyes',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.application-form', $loanRequest));

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');

    $pdfPath = $response->baseResponse->getFile()->getPathname();

    expect((new \setasign\Fpdi\Fpdi('P', 'mm'))->setSourceFile($pdfPath))
        ->toBe(1);
});

test('loan request pdf service defaults to long bond paper size', function () {
    $service = new LoanRequestPdfService(
        Mockery::mock(OrganizationSettingsService::class),
        new OfficialLoanManagerResolver,
    );

    $resolvePaperSize = new \ReflectionMethod($service, 'resolvePaperSize');
    $resolvePaperSize->setAccessible(true);

    $resolveDompdfPaper = new \ReflectionMethod($service, 'resolveDompdfPaper');
    $resolveDompdfPaper->setAccessible(true);

    expect($resolvePaperSize->invoke($service))->toBe([8.5, 13.0, 'in']);
    expect($resolveDompdfPaper->invoke($service))->toBe([0, 0, 612.0, 936.0]);
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
        ...validLoanRequestMemberSectionPayload(),
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
            'employer_date_employed' => '2018-03-15',
            'gross_monthly_income' => 25000,
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
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
            'payday' => 'Quincenal',
        ],
    ];

    $response = $this
        ->actingAs($user)
        ->post(route('client.loan-requests.store'), $payload);

    $response->assertSessionHasErrors(['applicant.housing_status']);
});

test('loan request pdf endpoint responds with a pdf for pending review requests', function () {
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
            'status' => LoanRequestStatus::PendingReview,
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
    $response->assertSee('report-header--fallback');
    $response->assertSee('report-title');
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
    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'user_id' => $loanRequest->user_id,
        'issue_description' => 'Name mismatch in applicant details.',
        'correct_information' => 'Use legal name from member profile.',
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?perPage=10&page=1');

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $loanRequest->id)
        ->assertJsonPath('data.items.0.reference', $loanRequest->reference)
        ->assertJsonPath('data.items.0.has_open_correction_report', true)
        ->assertJsonPath(
            'data.items.0.latest_correction_report_issue',
            'Name mismatch in applicant details.',
        );
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

test('admin requests api filters under review status and keeps pending review separate', function () {
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
        'status' => LoanRequestStatus::PendingReview,
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?status=under_review');

    $response->assertOk()->assertJsonCount(1, 'data.items');

    $statuses = collect($response->json('data.items'))
        ->pluck('status')
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($statuses)->toBe([
        'under_review',
    ]);
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

test('admin requests api reported filter returns only requests with open correction reports', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $openReported = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);
    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $openReported->id,
        'user_id' => $openReported->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $dismissedReported = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);
    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $dismissedReported->id,
        'user_id' => $dismissedReported->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_DISMISSED,
        'dismissed_at' => now(),
    ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests?reported=1');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $openReported->id)
        ->assertJsonPath('data.items.0.has_open_correction_report', true);
});

test('admin reported requests api returns only requests with open correction reports', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $firstOpenReported = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);
    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $firstOpenReported->id,
        'user_id' => $firstOpenReported->user_id,
        'issue_description' => 'Co-maker address is outdated.',
        'correct_information' => 'Use current branch address record.',
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
        'created_at' => now()->subHour(),
    ]);

    $secondOpenReported = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);
    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $secondOpenReported->id,
        'user_id' => $secondOpenReported->user_id,
        'issue_description' => 'Applicant middle name typo.',
        'correct_information' => 'Correct middle name spelling.',
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
        'created_at' => now(),
    ]);

    $resolvedReported = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);
    LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $resolvedReported->id,
        'user_id' => $resolvedReported->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_RESOLVED,
        'resolved_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/spa/admin/requests/reported');

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $secondOpenReported->id)
        ->assertJsonPath(
            'data.items.0.latest_correction_report_issue',
            'Applicant middle name typo.',
        )
        ->assertJsonPath('data.items.1.id', $firstOpenReported->id);
});

test('non-admin users cannot access admin reported requests routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/spa/admin/requests/reported')
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.requests.reported'))
        ->assertForbidden();
});

test('admin reported requests route does not conflict with loan request show route', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.requests.reported'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/reported-requests'));

    $this
        ->actingAs($admin)
        ->get(route('admin.requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/loan-request-show'));
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
    Role::ensureWorkflowDefaults();
    Role::attachNamedRole($admin, Role::LOAN_PROCESSOR);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Loan',
            'birthdate' => '1990-04-10',
            'housing_status' => 'Owned',
            'civil_status' => 'MARRIED',
            'payday' => '15/30',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Loan',
            'birthdate' => '1989-03-12',
            'housing_status' => 'Rented',
            'civil_status' => 'Single',
            'payday' => 'monthly',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'Loan',
            'birthdate' => '1987-02-12',
            'housing_status' => 'RENTAL',
            'civil_status' => 'WIDOWED',
            'payday' => 'Biweekly',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.show', $loanRequest));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/loan-request-show')
            ->where('loanRequest.id', $loanRequest->id)
            ->where('loanRequest.reference', $loanRequest->reference)
            ->where('loanRequest.status', LoanRequestStatus::UnderReview->value)
            ->where('decision.canDecide', false)
            ->where('decision.canCancel', true)
            ->where('decision.isOwnRequest', false)
            ->where('workflowPermissions', fn ($permissions): bool => collect($permissions)->contains(
                Permission::LOAN_REVIEW,
            ))
            ->where('applicant.first_name', 'Loan')
            ->where('applicant.birthdate', '1990-04-10')
            ->where('applicant.housing_status', 'OWNED')
            ->where('applicant.civil_status', 'Married')
            ->where('applicant.payday', 'Quincenal')
            ->where('coMakerOne.birthdate', '1989-03-12')
            ->where('coMakerOne.housing_status', 'RENT')
            ->where('coMakerOne.payday', 'Monthly')
            ->where('coMakerTwo.birthdate', '1987-02-12')
            ->where('coMakerTwo.housing_status', 'RENT')
            ->where('coMakerTwo.civil_status', 'Widowed')
            ->where('coMakerTwo.payday', 'Weekly'));
});

/**
 * Regression guard: the admin-facing loan request page (routed at
 * admin/requests/{id}, distinct from the staff page) used to omit
 * `dataSections`/`dataSectionDefinitions` entirely, leaving no way to fill in
 * `recommended_amount` etc. before recommending approval on a
 * document_workflow_v2 request viewed through this route -- staff hit a raw
 * "Recommended amount is required before recommendation." validation error
 * with no field to fix it. This pins that the props are present and that
 * saving recommended_amount through the shared processing-details endpoint
 * (the only way to satisfy that validation) persists correctly.
 */
test('admin loan request page exposes processing data sections needed to set recommended amount before recommending approval', function () {
    Role::ensureWorkflowDefaults();

    $processor = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $processor->user_id,
    ]);
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
        ->get(route('admin.requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/loan-request-show')
            ->has('dataSections.processing')
            ->has('dataSectionDefinitions.processing.fields'));

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
        ->assertOk()
        ->assertJsonPath('data.loanRequest.recommended_amount', '24000.00');

    expect($loanRequest->refresh()->recommended_amount)->toBe('24000.00');
});

test('admin loan request page exposes document checklist and generated documents can be viewed through it', function () {
    Role::ensureWorkflowDefaults();

    $processor = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $processor->user_id,
    ]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->get(route('admin.requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/loan-request-show')
            ->has('documentChecklist'));

    $relativePath = sprintf(
        'loan-request-documents/%d/%s/test-preview.pdf',
        $loanRequest->id,
        LoanRequestDocumentKey::LoanInformation->value,
    );
    $absolutePath = Storage::disk('local')->path($relativePath);
    File::ensureDirectoryExists(dirname($absolutePath));
    File::put(
        $absolutePath,
        "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n0\n%%EOF",
    );

    LoanRequestDocument::query()->updateOrCreate(
        [
            'loan_request_id' => $loanRequest->id,
            'document_key' => LoanRequestDocumentKey::LoanInformation->value,
        ],
        [
            'is_applicable' => true,
            'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
            'template_version' => 'test-preview-v1',
            'source_hash' => sha1('loan_information-'.$loanRequest->id),
            'source_version' => 1,
            'generated_version' => 1,
            'generated_disk' => 'local',
            'generated_path' => $relativePath,
            'generated_filename' => 'loan_information.pdf',
            'generated_mime_type' => 'application/pdf',
            'generated_size_bytes' => File::size($absolutePath),
            'generated_by' => $processor->user_id,
            'generated_at' => now(),
        ],
    );

    $this
        ->actingAs($processor)
        ->get(
            route('admin.requests.documents.generated', [
                'loanRequest' => $loanRequest,
                'documentKey' => LoanRequestDocumentKey::LoanInformation->value,
                'download' => 1,
            ]),
        )
        ->assertOk()
        ->assertDownload();
});

test('admin corrected loan request detail uses linked correction report context and open correction flag', function () {
    $admin = User::factory()->create([
        'acctno' => '000890',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000891',
        'username' => 'member.reporter',
    ]);

    $sourceLoanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDays(4)->startOfSecond(),
        'cancelled_at' => now()->subDay()->startOfSecond(),
        'cancellation_reason' => 'Member correction report confirmed.',
    ]);

    $reportCreatedAt = now()->subDays(3)->startOfSecond();
    $report = LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $sourceLoanRequest->id,
        'user_id' => $member->user_id,
        'issue_description' => 'Applicant birthdate is incorrect.',
        'correct_information' => 'Use 1991-03-14 from the member ID.',
        'supporting_note' => 'Government ID screenshot attached.',
        'status' => LoanRequestCorrectionReport::STATUS_RESOLVED,
        'resolved_by' => $admin->user_id,
        'resolved_at' => now()->subDay()->startOfSecond(),
        'created_at' => $reportCreatedAt,
        'updated_at' => $reportCreatedAt,
    ]);

    $correctedLoanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->startOfSecond(),
        'corrected_from_id' => $sourceLoanRequest->id,
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($correctedLoanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Corrected',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.show', [
            'loanRequest' => $correctedLoanRequest,
            'openCorrection' => 1,
        ]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/loan-request-show')
            ->where('loanRequest.id', $correctedLoanRequest->id)
            ->where('loanRequest.corrected_from_id', $sourceLoanRequest->id)
            ->where('loanRequest.correction_saved', false)
            ->where('loanRequest.requires_correction_before_approval', true)
            ->where('openCorrectionOnLoad', true)
            ->has('correctionReports', 1)
            ->where('correctionReports.0.id', $report->id)
            ->where('correctionReports.0.status', LoanRequestCorrectionReport::STATUS_RESOLVED)
            ->where('correctionReports.0.issue_description', 'Applicant birthdate is incorrect.')
            ->where('correctionReports.0.correct_information', 'Use 1991-03-14 from the member ID.')
            ->where('correctionReports.0.supporting_note', 'Government ID screenshot attached.')
            ->where('correctionReports.0.reported_at', $reportCreatedAt->toDateTimeString())
            ->where('correctionReports.0.reported_by.user_id', $member->user_id)
            ->where('correctionReports.0.reported_by.name', 'member.reporter')
            ->where('correctionReports.0.reported_by.acctno', '000891'));
});

test('admin loan request detail marks own requests as not decisionable', function () {
    $admin = User::factory()->create([
        'acctno' => '000700',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($admin)->create([
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
            ->where('decision.canDecide', false)
            ->where('decision.canCancel', false)
            ->where('decision.isOwnRequest', true));
});

test('admin cannot approve an under review loan request', function () {
    Queue::fake();

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
        });
    }

    $admin = User::factory()->create([
        'acctno' => '000500',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000501',
        'phoneno' => '09171234567',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'approved_amount' => 15000,
        'approved_term' => 12,
        'decision_notes' => 'Approved for release.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/approve",
            $payload,
        );

    $response
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect(DB::table('wlnmaster')->count())->toBe(0);
    expect(DB::table('wlnled')->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('admin cannot decline an under review loan request', function () {
    Queue::fake();

    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000502',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'decision_notes' => 'Declined due to incomplete documents.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/decline",
            $payload,
        );

    $response
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->decision_notes)->toBeNull();

    Queue::assertNothingPushed();
});

test('admin can cancel a pending loan request before decision', function (LoanRequestStatus $status) {
    $admin = User::factory()->create([
        'acctno' => '000620',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000621',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'submitted_at' => now()->subHour()->startOfSecond(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Member asked to stop the application.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Cancelled->value)
        ->assertJsonPath('data.loanRequest.reference', $loanRequest->reference)
        ->assertJsonPath('data.loanRequest.cancelled_by.user_id', $admin->user_id)
        ->assertJsonPath('data.loanRequest.cancellation_reason', 'Member asked to stop the application.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Cancelled);
    expect($loanRequest->reviewed_by)->toBeNull();
    expect($loanRequest->reviewed_at)->toBeNull();
    expect($loanRequest->approved_amount)->toBeNull();
    expect($loanRequest->approved_term)->toBeNull();
    expect($loanRequest->decision_notes)->toBeNull();
    expect($loanRequest->cancelled_by)->toBe($admin->user_id);
    expect($loanRequest->cancelled_at)->not->toBeNull();
    expect($loanRequest->cancellation_reason)->toBe('Member asked to stop the application.');

    $change = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->sole();

    expect($change->changed_by)->toBe($admin->user_id);
    expect($change->action)->toBe(LoanRequestChange::ACTION_CANCEL_REQUEST);
    expect($change->reason)->toBe('Member asked to stop the application.');
    expect($change->before_json['status'])->toBe($status->value);
    expect($change->after_json['status'])->toBe(LoanRequestStatus::Cancelled->value);
})->with([
    'pending review' => [LoanRequestStatus::PendingReview],
    'under review' => [LoanRequestStatus::UnderReview],
    'submitted' => [LoanRequestStatus::Submitted],
    'legacy pending co-maker signatures' => [LoanRequestStatus::PendingCoMakerSignatures],
]);

test('loan manager cannot cancel a request no loan processor has picked up yet', function () {
    $manager = User::factory()->create([
        'acctno' => '000622',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $manager->user_id,
    ]);
    Role::attachNamedRole($manager, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000623',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now()->subHour()->startOfSecond(),
    ]);

    $this
        ->actingAs($manager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Trying to cancel before processing.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview);
    expect($loanRequest->cancelled_by)->toBeNull();
});

test('loan manager can cancel a request once a loan processor has started it', function () {
    $manager = User::factory()->create([
        'acctno' => '000624',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $manager->user_id,
    ]);
    Role::attachNamedRole($manager, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000625',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->subHour()->startOfSecond(),
    ]);

    $this
        ->actingAs($manager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Member asked to stop the application.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Cancelled->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Cancelled);
    expect($loanRequest->cancelled_by)->toBe($manager->user_id);
});

test('loan processor can cancel a request even before another processor picks it up', function () {
    $processor = User::factory()->create([
        'acctno' => '000626',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $processor->user_id,
    ]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $member = User::factory()->create([
        'acctno' => '000627',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now()->subHour()->startOfSecond(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Duplicate application.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Cancelled->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Cancelled);
    expect($loanRequest->cancelled_by)->toBe($processor->user_id);
});

test('admin can cancel an approved loan request with a reason', function () {
    $reviewer = User::factory()->create([
        'acctno' => '000610',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $reviewer->user_id,
    ]);

    $admin = User::factory()->create([
        'acctno' => '000611',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000612',
    ]);
    $reviewedAt = now()->subDay()->startOfSecond();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_by' => $reviewer->user_id,
        'reviewed_at' => $reviewedAt,
        'approved_amount' => 25000,
        'approved_term' => 18,
        'decision_notes' => 'Approved before cancellation.',
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Wrong co-maker details.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Cancelled->value)
        ->assertJsonPath('data.loanRequest.reference', $loanRequest->reference)
        ->assertJsonPath('data.loanRequest.reviewed_by.user_id', $reviewer->user_id)
        ->assertJsonPath('data.loanRequest.cancelled_by.user_id', $admin->user_id)
        ->assertJsonPath('data.loanRequest.cancellation_reason', 'Wrong co-maker details.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Cancelled);
    expect($loanRequest->reviewed_by)->toBe($reviewer->user_id);
    expect($loanRequest->reviewed_at?->toDateTimeString())->toBe($reviewedAt->toDateTimeString());
    expect($loanRequest->approved_amount)->toBe('25000.00');
    expect($loanRequest->approved_term)->toBe(18);
    expect($loanRequest->decision_notes)->toBe('Approved before cancellation.');
    expect($loanRequest->cancelled_by)->toBe($admin->user_id);
    expect($loanRequest->cancelled_at)->not->toBeNull();
    expect($loanRequest->cancellation_reason)->toBe('Wrong co-maker details.');

    $change = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->first();

    expect($change)->not->toBeNull();
    expect($change->changed_by)->toBe($admin->user_id);
    expect($change->action)->toBe('cancel_approved_request');
    expect($change->reason)->toBe('Wrong co-maker details.');
    expect($change->before_json['status'])->toBe(LoanRequestStatus::Approved->value);
    expect($change->after_json['status'])->toBe(LoanRequestStatus::Cancelled->value);
    expect($change->after_json['cancelled_by'])->toBe($admin->user_id);
    expect($change->changed_fields_json)->toBe([
        'status',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ]);
});

test('admin cancel response includes correction reports payload', function () {
    $reviewer = User::factory()->create([
        'acctno' => '000614',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $reviewer->user_id,
    ]);
    $admin = User::factory()->create([
        'acctno' => '000615',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    $member = User::factory()->create([
        'acctno' => '000616',
    ]);
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_by' => $reviewer->user_id,
        'reviewed_at' => now()->subDay(),
        'approved_amount' => 15000,
        'approved_term' => 12,
    ]);
    $report = LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'user_id' => $member->user_id,
        'issue_description' => 'Approved amount is incorrect.',
        'correct_information' => 'Use approved amount from signed terms.',
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Correcting approved request details.',
        ]);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'ok',
            'data' => [
                'loanRequest' => ['id', 'status', 'reference'],
                'correctionReports' => [
                    '*' => ['id', 'status', 'issue_description', 'correct_information'],
                ],
            ],
        ])
        ->assertJsonPath('data.correctionReports.0.id', $report->id);
});

test('cancelling reported approved request resolves open correction reports', function () {
    $reviewer = User::factory()->create([
        'acctno' => '000617',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $reviewer->user_id,
    ]);
    $admin = User::factory()->create([
        'acctno' => '000618',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    $member = User::factory()->create([
        'acctno' => '000619',
    ]);
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_by' => $reviewer->user_id,
        'reviewed_at' => now()->subDay(),
        'approved_amount' => 22000,
        'approved_term' => 18,
    ]);
    $firstOpenReport = LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'user_id' => $member->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);
    $secondOpenReport = LoanRequestCorrectionReport::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'user_id' => $member->user_id,
        'status' => LoanRequestCorrectionReport::STATUS_OPEN,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Member correction report confirmed.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Cancelled->value);

    $firstOpenReport->refresh();
    $secondOpenReport->refresh();

    expect($firstOpenReport->status)->toBe(LoanRequestCorrectionReport::STATUS_RESOLVED);
    expect($secondOpenReport->status)->toBe(LoanRequestCorrectionReport::STATUS_RESOLVED);
    expect($firstOpenReport->resolved_by)->toBe($admin->user_id);
    expect($secondOpenReport->resolved_by)->toBe($admin->user_id);
    expect($firstOpenReport->resolved_at)->not->toBeNull();
    expect($secondOpenReport->resolved_at)->not->toBeNull();
});

test('non-admin users cannot cancel approved loan requests', function () {
    $user = User::factory()->create();
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_amount' => 18000,
        'approved_term' => 12,
    ]);

    $this
        ->actingAs($user)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Wrong applicant details.',
        ])
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect($loanRequest->cancelled_by)->toBeNull();
    expect($loanRequest->cancelled_at)->toBeNull();
    expect($loanRequest->cancellation_reason)->toBeNull();
});

test('admins cannot cancel their own approved loan request', function () {
    $admin = User::factory()->create([
        'acctno' => '000613',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($admin)->create([
        'status' => LoanRequestStatus::Approved,
        'approved_amount' => 22000,
        'approved_term' => 10,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Wrong applicant details.',
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors('decision');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect($loanRequest->cancelled_by)->toBeNull();
});

test('only pending or approved loan requests can be cancelled through the admin cancellation endpoint', function (LoanRequestStatus $status) {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => $status,
        'approved_amount' => $status === LoanRequestStatus::Declined ? null : 15000,
        'approved_term' => $status === LoanRequestStatus::Declined ? null : 12,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Wrong applicant details.',
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe($status);
    expect($loanRequest->cancelled_by)->toBeNull();
})->with([
    'declined' => [LoanRequestStatus::Declined],
    'draft' => [LoanRequestStatus::Draft],
    'cancelled' => [LoanRequestStatus::Cancelled],
]);

test('loan request cancellation requires a reason', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_amount' => 18000,
        'approved_term' => 12,
    ]);

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('cancellation_reason');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect($loanRequest->cancelled_by)->toBeNull();
});

test('member can cancel an under review loan request without providing a reason', function () {
    $member = createApprovedMemberForLoanRequestTests('000733');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/cancel", []);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Cancelled->value)
        ->assertJsonPath('data.loanRequest.cancelled_by.user_id', $member->user_id)
        ->assertJsonPath(
            'data.loanRequest.cancellation_reason',
            'Cancelled by member before review decision.',
        );

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Cancelled);
    expect($loanRequest->cancelled_by)->toBe($member->user_id);
    expect($loanRequest->cancelled_at)->not->toBeNull();
    expect($loanRequest->cancellation_reason)
        ->toBe('Cancelled by member before review decision.');

    $change = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->sole();

    expect($change->changed_by)->toBe($member->user_id);
    expect($change->action)->toBe(LoanRequestChange::ACTION_CANCEL_REQUEST);
    expect($change->reason)->toBe('Cancelled by member before review decision.');
});

test('member can cancel a pending loan request with a provided reason', function (LoanRequestStatus $status) {
    $member = createApprovedMemberForLoanRequestTests('000734');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Found a mistake in the amount.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Cancelled->value)
        ->assertJsonPath(
            'data.loanRequest.cancellation_reason',
            'Found a mistake in the amount.',
        );

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Cancelled);
    expect($loanRequest->cancellation_reason)->toBe('Found a mistake in the amount.');
})->with([
    'pending review' => [LoanRequestStatus::PendingReview],
    'submitted' => [LoanRequestStatus::Submitted],
    'legacy pending co-maker signatures' => [LoanRequestStatus::PendingCoMakerSignatures],
]);

test('member cannot cancel a finalized or unavailable loan request', function (LoanRequestStatus $status) {
    $member = createApprovedMemberForLoanRequestTests('000735');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'submitted_at' => now()->subHour(),
    ]);

    $response = $this
        ->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'No longer needed.',
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe($status);
    expect($loanRequest->cancelled_by)->toBeNull();
    expect($loanRequest->cancelled_at)->toBeNull();
})->with([
    'draft' => [LoanRequestStatus::Draft],
    'approved' => [LoanRequestStatus::Approved],
    'declined' => [LoanRequestStatus::Declined],
    'cancelled' => [LoanRequestStatus::Cancelled],
]);

test('member cannot cancel another members loan request', function () {
    $owner = createApprovedMemberForLoanRequestTests('000736');
    $viewer = createApprovedMemberForLoanRequestTests('000737');

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($viewer)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'No longer needed.',
        ])
        ->assertNotFound();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->cancelled_by)->toBeNull();
});

test('loan request decision sms uses branding and reference for approvals', function () {
    Http::fake([
        'https://api.semaphore.co/api/v4/messages' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');
    config()->set('services.semaphore.base_url', 'https://api.semaphore.co/api/v4/messages');
    config()->set('services.semaphore.sender_name', 'MRDINC');

    OrganizationSetting::factory()->create([
        'company_name' => 'MRDINC',
        'portal_label' => 'Member Portal',
        'loan_sms_approved_template' => ' ',
    ]);

    $member = User::factory()->create([
        'acctno' => '000510',
        'phoneno' => '09175551234',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'approved_amount' => 100000,
        'approved_term' => 12,
    ]);

    SendLoanDecisionSmsJob::dispatchSync($loanRequest->id);

    $expectedMessage = sprintf(
        'MRDINC Member Portal: Your loan request (%s) has been APPROVED for Php. 100,000.00 payable over 12 months and is awaiting processing in WIBS.',
        $loanRequest->reference,
    );

    Http::assertSent(function ($request) use ($member, $expectedMessage): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.semaphore.co/api/v4/messages'
            && ($payload['number'] ?? null) === $member->phoneno
            && ($payload['message'] ?? null) === $expectedMessage;
    });
});

test('loan request decision sms avoids duplicate company names for declines', function () {
    Http::fake([
        'https://api.semaphore.co/api/v4/messages' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');
    config()->set('services.semaphore.base_url', 'https://api.semaphore.co/api/v4/messages');
    config()->set('services.semaphore.sender_name', 'MRDINC');

    OrganizationSetting::factory()->create([
        'company_name' => 'MRDINC',
        'portal_label' => 'MRDINC Member Portal',
    ]);

    $member = User::factory()->create([
        'acctno' => '000511',
        'phoneno' => '09175551235',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Declined,
    ]);

    SendLoanDecisionSmsJob::dispatchSync($loanRequest->id);

    $expectedMessage = sprintf(
        'MRDINC Member Portal: Your loan request (%s) has been DECLINED. For questions or clarification, please contact the MRDINC office.',
        $loanRequest->reference,
    );

    Http::assertSent(function ($request) use ($member, $expectedMessage): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.semaphore.co/api/v4/messages'
            && ($payload['number'] ?? null) === $member->phoneno
            && ($payload['message'] ?? null) === $expectedMessage;
    });
});

test('loan request decision sms renders custom approved templates', function () {
    Http::fake([
        'https://api.semaphore.co/api/v4/messages' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');
    config()->set('services.semaphore.base_url', 'https://api.semaphore.co/api/v4/messages');
    config()->set('services.semaphore.sender_name', 'MRDINC');

    OrganizationSetting::factory()->create([
        'company_name' => 'MRDINC',
        'portal_label' => 'Member Portal',
        'loan_sms_approved_template' => '{message_prefix}: Loan {loan_reference} approved for {approved_amount} over {approved_term} months. Please visit {office_name}.',
    ]);

    $member = User::factory()->create([
        'acctno' => '000512',
        'phoneno' => '09175551236',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'approved_amount' => 54000,
        'approved_term' => 18,
    ]);

    SendLoanDecisionSmsJob::dispatchSync($loanRequest->id);

    $expectedMessage = sprintf(
        'MRDINC Member Portal: Loan %s approved for Php. 54,000.00 over 18 months. Please visit MRDINC.',
        $loanRequest->reference,
    );

    Http::assertSent(function ($request) use ($member, $expectedMessage): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.semaphore.co/api/v4/messages'
            && ($payload['number'] ?? null) === $member->phoneno
            && ($payload['message'] ?? null) === $expectedMessage;
    });
});

test('loan request decision sms renders custom declined templates', function () {
    Http::fake([
        'https://api.semaphore.co/api/v4/messages' => Http::response(['ok' => true], 200),
    ]);

    config()->set('services.semaphore.api_key', 'test-key');
    config()->set('services.semaphore.base_url', 'https://api.semaphore.co/api/v4/messages');
    config()->set('services.semaphore.sender_name', 'MRDINC');

    OrganizationSetting::factory()->create([
        'company_name' => 'MRDINC',
        'portal_label' => 'Member Portal',
        'loan_sms_declined_template' => '{message_prefix}: Loan {loan_reference} declined. Please contact {office_name} for assistance.',
    ]);

    $member = User::factory()->create([
        'acctno' => '000513',
        'phoneno' => '09175551237',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Declined,
    ]);

    SendLoanDecisionSmsJob::dispatchSync($loanRequest->id);

    $expectedMessage = sprintf(
        'MRDINC Member Portal: Loan %s declined. Please contact MRDINC for assistance.',
        $loanRequest->reference,
    );

    Http::assertSent(function ($request) use ($member, $expectedMessage): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.semaphore.co/api/v4/messages'
            && ($payload['number'] ?? null) === $member->phoneno
            && ($payload['message'] ?? null) === $expectedMessage;
    });
});

test('admins cannot decide their own loan request by user id', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'acctno' => '000503',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($admin)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 12000,
            'approved_term' => 10,
        ]);

    $response
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    Queue::assertNothingPushed();
});

test('admins cannot decide their own loan request by account number', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'acctno' => '000504',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000999',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $admin->acctno,
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/decline", [
            'decision_notes' => 'Not allowed.',
        ]);

    $response
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    Queue::assertNothingPushed();
});

test('loan requests not under review cannot be decided', function () {
    Queue::fake();

    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 10000,
            'approved_term' => 12,
        ]);

    $response
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    Queue::assertNothingPushed();
});

test('admin corrected request cannot be approved immediately after creation', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'acctno' => '000531',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000532',
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDays(2),
        'cancelled_at' => now()->subDay(),
        'cancellation_reason' => 'Wrong applicant details.',
    ]);

    $corrected = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'corrected_from_id' => $source->id,
    ]);

    LoanRequestChange::query()->create([
        'loan_request_id' => $corrected->id,
        'changed_by' => $admin->user_id,
        'action' => LoanRequestChange::ACTION_ADMIN_CREATE_CORRECTED_REQUEST,
        'reason' => 'Create corrected request from cancelled request.',
        'before_json' => ['loanRequest' => ['id' => $source->id]],
        'after_json' => ['loanRequest' => ['id' => $corrected->id]],
        'changed_fields_json' => [
            'corrected_from_id',
            'copied_loan_details',
            'copied_people_snapshots',
            'admin_correction_reason',
        ],
    ]);

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$corrected->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('approval')
        ->assertJsonPath(
            'errors.approval.0',
            'Please review and save the correction before approving this admin-corrected request.',
        );

    $corrected->refresh();

    expect($corrected->status)->toBe(LoanRequestStatus::RecommendedForApproval);
    expect(
        LoanRequestChange::query()
            ->where('loan_request_id', $corrected->id)
            ->pluck('action')
            ->all(),
    )->toBe([
        LoanRequestChange::ACTION_ADMIN_CREATE_CORRECTED_REQUEST,
    ]);

    Queue::assertNothingPushed();
});

test('admin create corrected request audit alone is not enough to approve', function () {
    $admin = User::factory()->create([
        'acctno' => '000533',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000534',
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDays(2),
        'cancelled_at' => now()->subDay(),
        'cancellation_reason' => 'Wrong applicant details.',
    ]);

    $corrected = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'corrected_from_id' => $source->id,
    ]);

    LoanRequestChange::query()->create([
        'loan_request_id' => $corrected->id,
        'changed_by' => $admin->user_id,
        'action' => LoanRequestChange::ACTION_ADMIN_CREATE_CORRECTED_REQUEST,
        'reason' => 'Create corrected request from cancelled request.',
        'before_json' => ['loanRequest' => ['id' => $source->id]],
        'after_json' => ['loanRequest' => ['id' => $corrected->id]],
        'changed_fields_json' => [
            'corrected_from_id',
            'copied_loan_details',
            'copied_people_snapshots',
            'admin_correction_reason',
        ],
    ]);

    $service = app(LoanRequestDecisionService::class);

    expect($service->hasSavedCorrectionAfterCreation($corrected))->toBeFalse();
    expect($service->requiresSavedCorrectionBeforeApproval($corrected))
        ->toBeTrue();
});

test('admin corrected request can be approved after a saved correction audit exists', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'acctno' => '000535',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000536',
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDays(2),
        'cancelled_at' => now()->subDay(),
        'cancellation_reason' => 'Wrong applicant details.',
    ]);

    $corrected = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'typecode' => 'LN-OLD',
        'loan_type_label_snapshot' => 'Old Loan',
        'requested_amount' => 12000,
        'requested_term' => 10,
        'loan_purpose' => 'Original purpose',
        'availment_status' => 'New',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'corrected_from_id' => $source->id,
    ]);
    createLoanRequestPeopleSnapshots($corrected);

    LoanRequestChange::query()->create([
        'loan_request_id' => $corrected->id,
        'changed_by' => $admin->user_id,
        'action' => LoanRequestChange::ACTION_ADMIN_CREATE_CORRECTED_REQUEST,
        'reason' => 'Create corrected request from cancelled request.',
        'before_json' => ['loanRequest' => ['id' => $source->id]],
        'after_json' => ['loanRequest' => ['id' => $corrected->id]],
        'changed_fields_json' => [
            'corrected_from_id',
            'copied_loan_details',
            'copied_people_snapshots',
            'admin_correction_reason',
        ],
    ]);

    $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$corrected->id}/corrections",
            validLoanRequestCorrectionPayload(),
        )
        ->assertOk()
        ->assertJsonPath('data.loanRequest.correction_saved', true)
        ->assertJsonPath(
            'data.loanRequest.requires_correction_before_approval',
            false,
        );

    $corrected->forceFill([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ])->save();

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$corrected->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertOk();

    $corrected->refresh();

    expect($corrected->status)->toBe(LoanRequestStatus::Approved);
    expect(
        LoanRequestChange::query()
            ->where('loan_request_id', $corrected->id)
            ->where(
                'action',
                LoanRequestChange::ACTION_ADMIN_UPDATE_CORRECTED_REQUEST_DETAILS,
            )
            ->exists(),
    )->toBeTrue();

    Queue::assertPushed(SendLoanDecisionSmsJob::class);
});

test('corrected request update rejects a co-maker address2/address3 that was never selected from the PSGC dropdown', function () {
    $admin = User::factory()->create([
        'acctno' => '000538',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000539',
    ]);

    $corrected = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);
    createLoanRequestPeopleSnapshots($corrected);

    $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$corrected->id}/corrections",
            validLoanRequestCorrectionPayload([
                'co_maker_1' => ['address2' => 'Not A Real City'],
            ]),
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['co_maker_1.address2']);
});

test('corrected request approval is blocked when correction audit history is unavailable', function () {
    Queue::fake();

    $admin = User::factory()->create([
        'acctno' => '000537',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000538',
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDays(2),
        'cancelled_at' => now()->subDay(),
        'cancellation_reason' => 'Wrong applicant details.',
    ]);

    $corrected = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'corrected_from_id' => $source->id,
    ]);

    Schema::drop('loan_request_changes');

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$corrected->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('approval')
        ->assertJsonPath(
            'errors.approval.0',
            'Correction audit history is unavailable. Please save the correction before approving this admin-corrected request.',
        );

    $corrected->refresh();

    expect($corrected->status)->toBe(LoanRequestStatus::RecommendedForApproval);

    Queue::assertNothingPushed();
});

test('admin can correct under review loan request details and people snapshots', function () {
    $admin = User::factory()->create([
        'acctno' => '000520',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000521',
    ]);
    $submittedAt = now()->subDay();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-OLD',
        'loan_type_label_snapshot' => 'Old Loan',
        'requested_amount' => 12000,
        'requested_term' => 10,
        'loan_purpose' => 'Original purpose',
        'availment_status' => 'New',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => $submittedAt,
    ]);
    createLoanRequestPeopleSnapshots($loanRequest);

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            validLoanRequestCorrectionPayload(),
        );

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value)
        ->assertJsonPath('data.loanRequest.requested_amount', '23000.00')
        ->assertJsonPath('data.loanRequest.requested_term', 18)
        ->assertJsonPath('data.loanRequest.loan_purpose', 'Corrected purpose')
        ->assertJsonPath('data.loanRequest.correction_saved', false)
        ->assertJsonPath('data.loanRequest.requires_correction_before_approval', false)
        ->assertJsonPath('data.applicant.first_name', 'Corrected')
        ->assertJsonPath('data.applicant.birthdate', '1990-04-10')
        ->assertJsonPath('data.coMakerOne.first_name', 'Corrected')
        ->assertJsonPath('data.coMakerOne.birthdate', '1989-03-12')
        ->assertJsonPath('data.coMakerTwo.first_name', 'Corrected')
        ->assertJsonPath('data.coMakerTwo.birthdate', '1987-02-12');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->submitted_at?->toDateTimeString())->toBe($submittedAt->toDateTimeString());
    expect($loanRequest->reviewed_by)->toBeNull();
    expect($loanRequest->reviewed_at)->toBeNull();
    expect($loanRequest->approved_amount)->toBeNull();
    expect($loanRequest->approved_term)->toBeNull();
    expect($loanRequest->decision_notes)->toBeNull();
    expect($loanRequest->typecode)->toBe('LN-COR');
    expect($loanRequest->loan_type_label_snapshot)->toBe('Corrected Personal');
    expect($loanRequest->requested_amount)->toBe('23000.00');
    expect($loanRequest->requested_term)->toBe(18);

    $people = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->get()
        ->keyBy('role');

    expect($people)->toHaveCount(3);
    expect($people[LoanRequestPersonRole::Applicant->value]->first_name)->toBe('Corrected');
    expect($people[LoanRequestPersonRole::Applicant->value]->birthplace)->toBe('Manila, Metro Manila');
    expect($people[LoanRequestPersonRole::Applicant->value]->address)->toBe('Corrected Street, Manila, Metro Manila');
    expect($people[LoanRequestPersonRole::CoMakerOne->value]->employer_business_name)->toBe('Corrected Office One');
    expect($people[LoanRequestPersonRole::CoMakerTwo->value]->employer_business_name)->toBe('Corrected Store Two');

    $change = LoanRequestChange::query()->sole();

    expect($change->loan_request_id)->toBe($loanRequest->id);
    expect($change->changed_by)->toBe($admin->user_id);
    expect($change->action)->toBe(
        LoanRequestChange::ACTION_ADMIN_UPDATE_CORRECTED_REQUEST_DETAILS,
    );
    expect($change->reason)->toBe('Corrected submitted request details.');
    expect($change->before_json['loanRequest']['loan_purpose'])->toBe('Original purpose');
    expect($change->after_json['loanRequest']['loan_purpose'])->toBe('Corrected purpose');
    expect($change->after_json['applicant']['first_name'])->toBe('Corrected');
    expect($change->changed_fields_json ?? [])->toContain(
        'loanRequest.loan_purpose',
        'applicant.first_name',
    );
});

test('admin correction persists health, health_glapi, dependents, insurance, and banking edits', function () {
    $admin = User::factory()->create([
        'acctno' => '000523',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000524',
    ]);
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-OLD',
        'loan_type_label_snapshot' => 'Old Loan',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->subDay(),
    ]);
    createLoanRequestPeopleSnapshots($loanRequest);

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            validLoanRequestCorrectionPayload([
                'insurance' => [
                    'beneficiary_primary_name' => 'Corrected Beneficiary',
                ],
                'health' => [
                    'health_smoking_status' => 'light',
                    'health_hypertension' => true,
                ],
                'health_glapi' => [
                    'applicant_pep_status' => true,
                    'applicant_pep_status_details' => 'Barangay Councilor, since 2020',
                ],
                'banking' => [
                    'payout_bank_name' => 'Corrected Bank',
                ],
                'dependents' => [
                    'applicant_cycle_status' => 'Old',
                    'applicant_cycle_number' => 3,
                ],
            ]),
        );

    $response->assertOk();

    $loanRequest->refresh();
    $flatValues = app(App\Services\LoanRequests\LoanRequestDataService::class)
        ->loadFlatValues($loanRequest);

    expect($flatValues['beneficiary_primary_name'])->toBe('Corrected Beneficiary')
        ->and($flatValues['health_smoking_status'])->toBe('light')
        ->and($flatValues['health_hypertension'])->toBeTrue()
        ->and($flatValues['applicant_pep_status'])->toBeTrue()
        ->and($flatValues['applicant_pep_status_details'])->toBe('Barangay Councilor, since 2020')
        ->and($flatValues['payout_bank_name'])->toBe('Corrected Bank')
        ->and($flatValues['applicant_cycle_status'])->toBe('Old')
        ->and((int) $flatValues['applicant_cycle_number'])->toBe(3);

    $dataChanges = App\Models\LoanRequestDataChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->pluck('field_key');

    expect($dataChanges)->toContain(
        'beneficiary_primary_name',
        'health_smoking_status',
        'applicant_pep_status',
        'payout_bank_name',
        'applicant_cycle_status',
    );
});

test('admin correction rejects health_glapi fields outside the narrowed PEP whitelist', function () {
    $admin = User::factory()->create([
        'acctno' => '000525',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000526',
    ]);
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-OLD',
        'loan_type_label_snapshot' => 'Old Loan',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->subDay(),
    ]);
    createLoanRequestPeopleSnapshots($loanRequest);

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            validLoanRequestCorrectionPayload([
                'health_glapi' => [
                    'gl_health_q01_weight_change' => true,
                ],
            ]),
        );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['health_glapi']);
});

test('non admins cannot correct loan requests', function () {
    $member = User::factory()->create([
        'acctno' => '000522',
    ]);
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($member)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            validLoanRequestCorrectionPayload(),
        );

    $response->assertForbidden();

    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('admins cannot correct their own loan request', function () {
    $admin = User::factory()->create([
        'acctno' => '000523',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $loanRequest = LoanRequest::factory()->forUser($admin)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            validLoanRequestCorrectionPayload(),
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('correction');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('approved and declined loan requests cannot be corrected', function (LoanRequestStatus $status) {
    $admin = User::factory()->create([
        'acctno' => '000524',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $loanRequest = LoanRequest::factory()->create([
        'status' => $status,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            validLoanRequestCorrectionPayload(),
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe($status);
    expect(LoanRequestChange::query()->count())->toBe(0);
})->with([
    'approved' => LoanRequestStatus::Approved,
    'declined' => LoanRequestStatus::Declined,
]);

test('forbidden correction fields are rejected and decision fields remain unchanged', function () {
    $admin = User::factory()->create([
        'acctno' => '000525',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);
    $reviewer = User::factory()->create([
        'acctno' => '000526',
    ]);

    $member = User::factory()->create([
        'acctno' => '000527',
    ]);
    $submittedAt = now()->subDays(2);
    $reviewedAt = now()->subDay();
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-OLD',
        'requested_amount' => 12000,
        'requested_term' => 10,
        'loan_purpose' => 'Original purpose',
        'availment_status' => 'New',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => $submittedAt,
        'reviewed_by' => $reviewer->user_id,
        'reviewed_at' => $reviewedAt,
        'approved_amount' => 5000,
        'approved_term' => 6,
        'decision_notes' => 'Existing notes',
    ]);

    $payload = validLoanRequestCorrectionPayload([
        'status' => LoanRequestStatus::Approved->value,
        'approved_amount' => 1,
        'approved_term' => 1,
        'decision_notes' => 'Changed notes',
        'reviewed_by' => $admin->user_id,
        'reviewed_at' => now()->toDateTimeString(),
        'submitted_at' => now()->toDateTimeString(),
        'user_id' => $admin->user_id,
        'acctno' => $admin->acctno,
        'reference' => 'LNREQ-999999',
        'undertaking_accepted' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            $payload,
        );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'status',
            'approved_amount',
            'approved_term',
            'decision_notes',
            'reviewed_by',
            'reviewed_at',
            'submitted_at',
            'user_id',
            'acctno',
            'reference',
            'undertaking_accepted',
        ]);

    $loanRequest->refresh();

    expect($loanRequest->typecode)->toBe('LN-OLD');
    expect($loanRequest->requested_amount)->toBe('12000.00');
    expect($loanRequest->requested_term)->toBe(10);
    expect($loanRequest->loan_purpose)->toBe('Original purpose');
    expect($loanRequest->availment_status)->toBe('New');
    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->submitted_at?->toDateTimeString())->toBe($submittedAt->toDateTimeString());
    expect($loanRequest->reviewed_by)->toBe($reviewer->user_id);
    expect($loanRequest->reviewed_at?->toDateTimeString())->toBe($reviewedAt->toDateTimeString());
    expect($loanRequest->approved_amount)->toBe('5000.00');
    expect($loanRequest->approved_term)->toBe(6);
    expect($loanRequest->decision_notes)->toBe('Existing notes');
    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('loan request decisions succeed even without a phone number', function () {
    Queue::fake();

    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000505',
        'phoneno' => null,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 9000,
            'approved_term' => 6,
        ]);

    $response->assertOk();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);

    Queue::assertPushed(SendLoanDecisionSmsJob::class);
});

test('admin loan request pdf endpoint responds with a pdf for pending review requests', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::PendingReview,
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
    $response->assertSee('report-header--fallback');
    $response->assertSee('report-title');
    $response->assertSee('window.print();', false);
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

test('client loans page excludes member loan requests data', function () {
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

    LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::Draft,
            'requested_amount' => 12000,
            'requested_term' => 12,
            'updated_at' => now(),
        ]);

    LoanRequest::factory()
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
            ->missing('loanRequests')
            ->missing('loanRequestsError'));
});

test('client loan requests page lists member loan requests', function () {
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

    $officer = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $officer->user_id,
        'fullname' => 'Officer Reviewer',
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
            'assigned_officer_id' => $officer->user_id,
        ]);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('client.loan-requests.index'));

    $response
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-requests')
            ->has('loanRequests.items', 2)
            ->where('loanRequests.items.0.id', $draft->id)
            ->where('loanRequests.items.0.reference', $draft->reference)
            ->where('loanRequests.items.0.status', LoanRequestStatus::Draft->value)
            ->where('loanRequests.items.0.assigned_officer', null)
            ->where('loanRequests.items.1.id', $submitted->id)
            ->where('loanRequests.items.1.reference', $submitted->reference)
            ->where('loanRequests.items.1.status', LoanRequestStatus::UnderReview->value)
            ->where('loanRequests.items.1.assigned_officer.user_id', $officer->user_id)
            ->where('loanRequests.items.1.assigned_officer.name', 'Officer Reviewer'));
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
            ->where('loanRequest.reference', $loanRequest->reference)
            ->where('loanRequest.status', LoanRequestStatus::UnderReview->value)
            ->where('applicant.first_name', 'Sample'));
});

test('client can view revision remarks for needs revision workflow requests', function () {
    $user = User::factory()->create([
        'acctno' => '000730A',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Revision',
        'fname' => 'Revision',
        'lname' => 'Member',
        'birthday' => '1991-04-10',
        'address' => 'Revision Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    $reviewer = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $reviewer->user_id,
        'fullname' => 'Loan Processor Review',
    ]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::NeedsRevision,
            'submitted_at' => now(),
            'reviewed_by' => $reviewer->user_id,
            'reviewed_at' => now()->subHour(),
            'review_decision' => 'request_revision',
            'review_remarks' => 'Please correct the employer address before review continues.',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Sample',
        ]);

    $this
        ->actingAs($user)
        ->get(route('client.loan-requests.show', $loanRequest))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request-show')
            ->where('loanRequest.status', LoanRequestStatus::NeedsRevision->value)
            ->where(
                'loanRequest.review_remarks',
                'Please correct the employer address before review continues.',
            )
            ->where('loanRequest.reviewed_by.name', 'Loan Processor Review'));
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

function createApprovedMemberForLoanRequestTests(string $acctno): User
{
    $user = User::factory()->create([
        'acctno' => $acctno,
    ]);

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->updateOrInsert([
        'acctno' => $acctno,
    ], [
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

    return $user;
}

test('atm holder checkbox backfills the applicant name when defaulting to checked with an empty value', function () {
    $contents = file_get_contents(
        base_path('resources/js/components/loan-request/atm-holder-checkbox-field.tsx'),
    );

    // Guards against a regression where a fresh ATM release/payment selection
    // rendered "This is my own ATM card" pre-checked but never wrote
    // applicantFullName into the submitted value, failing the backend's
    // Rule::requiredIf(...) on payout/payment_atm_holder_name even though the
    // checkbox looked correct and required no user action.
    expect($contents)->toContain('isOwnCard && value.trim() === \'\'')
        ->and($contents)->toContain('onChange(applicantFullName);');
});
