<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

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
        ['typecode' => 'LN-P7'],
        ['lntype' => 'Prerequisite Test Loan'],
    );
});

function prerequisiteTestMember(string $acctno, bool $withLoanPrerequisites): AppUser
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

    $factory = MemberApplicationProfile::factory()->completed();

    if ($withLoanPrerequisites) {
        $factory = $factory->withLoanPrerequisites();
    }

    $factory->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        [
            'fname' => 'Test',
            'lname' => 'Member',
            'bname' => 'Test Member',
            'birthday' => '1990-04-10',
            'address' => 'Prerequisite Street',
            'civilstat' => 'Single',
            'occupation' => 'Analyst',
        ],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

/**
 * @return array<string, mixed>
 */
function prerequisiteTestStorePayload(): array
{
    return [
        'typecode' => 'LN-P7',
        'requested_amount' => 25000,
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'insurance' => [
            'beneficiary_primary_name' => 'Primary Beneficiary',
            'beneficiary_primary_relationship' => 'Sibling',
            'beneficiary_primary_birthdate' => '1995-03-21',
            'beneficiary_secondary_name' => 'Secondary Beneficiary',
            'beneficiary_secondary_relationship' => 'Parent',
            'beneficiary_secondary_birthdate' => '1970-11-04',
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
            'payout_account_name' => 'Test Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'payout_atm_number' => '9876543210',
            'release_method' => 'Bank Transfer',
            'payment_option' => 'Salary Deduction',
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
            'payday' => '15th',
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
}

test('entry point flags missing loan prerequisites without blocking onboarding', function (): void {
    $member = prerequisiteTestMember('700001', withLoanPrerequisites: false);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanPrerequisitesMet', false)
        );
});

test('entry point reports prerequisites met when profile already has the data', function (): void {
    $member = prerequisiteTestMember('700002', withLoanPrerequisites: true);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanPrerequisitesMet', true)
        );
});

test('saving prerequisites via the entry-point modal endpoint marks them met', function (): void {
    $member = prerequisiteTestMember('700003', withLoanPrerequisites: false);

    $response = $this
        ->actingAs($member)
        ->postJson(route('client.loan-requests.prerequisites'), [
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'ATM',
            'payout_atm_number' => '4109123456789',
            'payout_bank_branch' => 'Makati Branch',
            'payout_atm_holder_name' => 'Test Member',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'TIN',
            'id_type_other' => null,
            'id_number' => '123-456-789',
            'height_cm' => '165',
            'weight_kg' => '68',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanPrerequisitesMet', true)
        ->assertJsonPath('data.loanPrerequisiteProfile.payout_bank_name', 'BDO')
        ->assertJsonPath('data.loanPrerequisiteProfile.payout_atm_number', '4109123456789')
        ->assertJsonPath('data.loanPrerequisiteProfile.payout_bank_branch', 'Makati Branch')
        ->assertJsonPath('data.loanPrerequisiteProfile.payout_atm_holder_name', 'Test Member')
        ->assertJsonPath('data.loanPrerequisiteProfile.id_type', 'TIN');

    $member->refresh()->loadMissing('memberApplicationProfile');
    expect($member->memberApplicationProfile->hasLoanPrerequisiteFields())->toBeTrue();
    expect($member->memberApplicationProfile->payout_atm_number)->toBe('4109123456789');
    expect($member->memberApplicationProfile->payout_bank_branch)->toBe('Makati Branch');
    expect($member->memberApplicationProfile->payout_atm_holder_name)->toBe('Test Member');
});

test('prerequisite modal endpoint requires id type other when id type is Others', function (): void {
    $member = prerequisiteTestMember('700004', withLoanPrerequisites: false);

    $this
        ->actingAs($member)
        ->postJson(route('client.loan-requests.prerequisites'), [
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Bank Transfer',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'Others',
            'id_type_other' => '',
            'id_number' => '123-456-789',
            'height_cm' => '165',
            'weight_kg' => '68',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id_type_other']);
});

test('prerequisite modal endpoint requires ATM card number and holder name when release method is ATM', function (): void {
    $member = prerequisiteTestMember('700008', withLoanPrerequisites: false);

    $this
        ->actingAs($member)
        ->postJson(route('client.loan-requests.prerequisites'), [
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'ATM',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'TIN',
            'id_type_other' => null,
            'id_number' => '123-456-789',
            'height_cm' => '165',
            'weight_kg' => '68',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payout_atm_number', 'payout_atm_holder_name']);
});

test('prerequisite modal endpoint requires a release bank name and account number when using a different release account', function (): void {
    $member = prerequisiteTestMember('700009', withLoanPrerequisites: false);

    $this
        ->actingAs($member)
        ->postJson(route('client.loan-requests.prerequisites'), [
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Bank Transfer',
            'release_uses_payout_account' => false,
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'TIN',
            'id_type_other' => null,
            'id_number' => '123-456-789',
            'height_cm' => '165',
            'weight_kg' => '68',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['release_bank_name', 'release_account_name', 'release_account_number', 'release_account_type']);

    $response = $this
        ->actingAs($member)
        ->postJson(route('client.loan-requests.prerequisites'), [
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Bank Transfer',
            'release_uses_payout_account' => false,
            'release_bank_name' => 'Metro City Bank',
            'release_account_name' => 'Test Member',
            'release_account_number' => '5544332211',
            'release_account_type' => 'Savings',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'TIN',
            'id_type_other' => null,
            'id_number' => '123-456-789',
            'height_cm' => '165',
            'weight_kg' => '68',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanPrerequisiteProfile.release_bank_name', 'Metro City Bank')
        ->assertJsonPath('data.loanPrerequisiteProfile.release_account_number', '5544332211');

    $member->refresh()->loadMissing('memberApplicationProfile');
    expect($member->memberApplicationProfile->release_uses_payout_account)->toBeFalse();
    expect($member->memberApplicationProfile->release_bank_name)->toBe('Metro City Bank');
});

test('submitting a loan request is blocked by the safety net when prerequisites are missing', function (): void {
    $member = prerequisiteTestMember('700005', withLoanPrerequisites: false);

    $response = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), prerequisiteTestStorePayload());

    $response->assertSessionHasErrors(['loan_prerequisites']);
    expect(LoanRequest::query()->count())->toBe(0);
});

test('submitting a loan request succeeds once prerequisites are on file', function (): void {
    $member = prerequisiteTestMember('700006', withLoanPrerequisites: true);

    $response = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), prerequisiteTestStorePayload());

    $loanRequest = LoanRequest::query()->first();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));
    expect($loanRequest)->not->toBeNull();
    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview);
});

test('submission blocked mid-session when prerequisites were cleared after the profile was saved', function (): void {
    $member = prerequisiteTestMember('700007', withLoanPrerequisites: true);

    $member->memberApplicationProfile->update(['payout_bank_name' => null]);

    $response = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), prerequisiteTestStorePayload());

    $response->assertSessionHasErrors(['loan_prerequisites']);
    expect(LoanRequest::query()->count())->toBe(0);
});
