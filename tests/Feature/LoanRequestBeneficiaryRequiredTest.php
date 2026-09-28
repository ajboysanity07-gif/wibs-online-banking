<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestDataService;
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

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table): void {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    DB::table('wlntype')->updateOrInsert(
        ['typecode' => 'LN-BEN'],
        ['lntype' => 'Beneficiary Required Test Loan'],
    );
});

function beneficiaryRequiredTestMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);

    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
        'home_address1' => 'Beneficiary Street',
        'home_address_barangay' => 'Aglipay',
        'home_address2' => 'Batac City',
        'home_address3' => 'Ilocos Norte',
        'housing_status' => 'OWNED',
        'employer_business_address_barangay' => 'Aglipay',
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        [
            'fname' => 'Test',
            'lname' => 'Member',
            'bname' => 'Test Member',
            'birthday' => '1990-04-10',
            'address' => 'Beneficiary Street',
            'civilstat' => 'Single',
            'occupation' => 'Analyst',
        ],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

/**
 * @return array<string, mixed>
 */
function beneficiaryRequiredTestPayload(?MemberApplicationProfile $profile, array $insuranceOverrides = []): array
{
    return [
        'typecode' => 'LN-BEN',
        'requested_amount' => 25000,
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'insurance' => array_merge([
            'beneficiary_primary_name' => 'Primary Beneficiary',
            'beneficiary_primary_relationship' => 'Sibling',
            'beneficiary_primary_birthdate' => '1995-03-21',
        ], $insuranceOverrides),
        'health' => [
            'health_smoking_status' => 'none',
            'health_hypertension' => false,
        ],
        'health_glapi' => [
            'health_recent_hospitalization' => false,
        ],
        'banking' => [
            'release_method' => 'Bank Transfer',
            'release_saved_account_id' => $profile?->release_saved_account_id,
            'payment_option' => 'ATM Deduction',
            'payment_saved_account_id' => $profile?->payment_saved_account_id,
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
        'applicant' => [
            'first_name' => 'Test',
            'last_name' => 'Member',
            'middle_name' => 'Q',
            'nickname' => null,
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Loan Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
            'length_of_stay' => '5 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09170000011',
            'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'number_of_children' => 0,
            'spouse_name' => null,
            'spouse_age' => null,
            'spouse_cell_no' => null,
            'employment_type' => 'Private',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address1' => 'Acme Street',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Services',
            'years_in_work_business' => '4 years',
            'employer_date_employed' => '2019-05-10',
            'gross_monthly_income' => 20000,
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
}

test('submitting a loan request is blocked when the primary beneficiary is blank', function (): void {
    $member = beneficiaryRequiredTestMember('700020');

    $response = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), beneficiaryRequiredTestPayload($member->memberApplicationProfile, [
            'beneficiary_primary_name' => '',
            'beneficiary_primary_relationship' => '',
            'beneficiary_primary_birthdate' => '',
        ]));

    $response->assertSessionHasErrors([
        'insurance.beneficiary_primary_name',
        'insurance.beneficiary_primary_relationship',
        'insurance.beneficiary_primary_birthdate',
    ]);
    expect(LoanRequest::query()->count())->toBe(0);
});

test('beneficiary relationship fields expose a fixed options list to the frontend', function (): void {
    $definitions = app(LoanRequestDataService::class)->sectionDefinitions();
    $fields = $definitions['insurance']['fields'];

    expect($fields['beneficiary_primary_relationship']['options'])
        ->toBe(['Spouse', 'Child', 'Parent', 'Sibling', 'Other']);
    expect($fields['beneficiary_secondary_relationship']['options'])
        ->toBe(['Spouse', 'Child', 'Parent', 'Sibling', 'Other']);
    expect($fields['beneficiary_primary_name']['options'])->toBeNull();
    expect($fields['beneficiary_primary_birthdate']['type'])->toBe('date');
});

test('submitting a loan request succeeds once a primary beneficiary is on file', function (): void {
    $member = beneficiaryRequiredTestMember('700021');

    $response = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), beneficiaryRequiredTestPayload($member->memberApplicationProfile));

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview);
});
