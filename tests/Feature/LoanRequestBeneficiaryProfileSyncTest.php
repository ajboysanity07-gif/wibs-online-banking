<?php

use App\LoanPaymentOption;
use App\LoanReleaseMethod;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
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

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table): void {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    DB::table('wlntype')->updateOrInsert(
        ['typecode' => 'LN-005'],
        ['lntype' => 'Personal'],
    );
});

function createBeneficiaryTestMember(string $acctno, array $profileOverrides = []): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(array_merge([
        'user_id' => $member->user_id,
    ], $profileOverrides));

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Beneficiary', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

test('getFormData prefills beneficiaries from the member profile and flags it', function (): void {
    $member = createBeneficiaryTestMember('003200', [
        'beneficiary_primary_name' => 'Profile Primary',
        'beneficiary_primary_relationship' => 'Spouse',
        'beneficiary_primary_birthdate' => '1991-05-06',
        'beneficiary_secondary_name' => 'Profile Secondary',
        'beneficiary_secondary_relationship' => 'Sibling',
        'beneficiary_secondary_birthdate' => '1993-08-10',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['insurancePrefilledFromProfile'])->toBeTrue();
    expect($formData['dataSections']['insurance']['beneficiary_primary_name'])->toBe('Profile Primary');
    expect($formData['dataSections']['insurance']['beneficiary_primary_birthdate'])->toBe('1991-05-06');
    expect($formData['dataSections']['insurance']['beneficiary_secondary_name'])->toBe('Profile Secondary');
    expect($formData['dataSections']['insurance']['beneficiary_secondary_birthdate'])->toBe('1993-08-10');
});

test('getFormData does not flag insurance prefill when the profile has no beneficiary data', function (): void {
    $member = createBeneficiaryTestMember('003201');

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['insurancePrefilledFromProfile'])->toBeFalse();
    expect($formData['dataSections']['insurance']['beneficiary_primary_name'])->toBeNull();
});

test('getFormData does not overwrite beneficiary values already saved on the draft', function (): void {
    $member = createBeneficiaryTestMember('003202', [
        'beneficiary_primary_name' => 'Profile Primary',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    app(\App\Services\LoanRequests\LoanRequestDataService::class)->syncMemberSections($loanRequest, [
        'insurance' => ['beneficiary_primary_name' => 'Member Edited Primary'],
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['insurancePrefilledFromProfile'])->toBeFalse();
    expect($formData['dataSections']['insurance']['beneficiary_primary_name'])->toBe('Member Edited Primary');
});

test('submit writes back validated beneficiary fields to the member profile', function (): void {
    $member = createBeneficiaryTestMember('003203', [
        'release_method' => LoanReleaseMethod::BankTransfer->value,
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'TIN',
        'id_number' => '123-456-789',
        'height_cm' => '165',
        'weight_kg' => '68',
    ]);

    $profile = $member->memberApplicationProfile;

    $account = $profile->paymentAccounts()->create([
        'label' => 'Primary',
        'bank_name' => 'WIBS Cooperative Bank',
        'account_name' => 'Loan Member',
        'account_number' => '1234567890',
        'account_type' => 'Savings',
        'atm_number' => '9876543210',
        'bank_branch' => 'Main Branch',
        'atm_holder_name' => null,
    ]);

    $profile->forceFill([
        'payment_option' => LoanPaymentOption::AtmDeduction->value,
        'release_saved_account_id' => $account->id,
        'payment_saved_account_id' => $account->id,
    ])->save();

    $person = fn (array $overrides = []) => array_merge([
        'first_name' => 'First',
        'last_name' => 'Last',
        'middle_name' => 'M',
        'nickname' => 'Nick',
        'birthdate' => '1990-04-10',
        'birthplace_city' => 'Manila',
        'birthplace_province' => 'Metro Manila',
        'address1' => 'Street',
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
        'employer_business_name' => 'Company',
        'employer_business_address1' => 'City Center',
        'employer_business_address2' => 'Manila',
        'employer_business_address3' => 'Metro Manila',
        'telephone_no' => '021234567',
        'current_position' => 'Analyst',
        'nature_of_business' => 'Finance',
        'years_in_work_business' => '3 years',
        'gross_monthly_income' => 25000,
        'payday' => '15th & 30th',
    ], $overrides);

    app(LoanRequestService::class)->submit($member, [
        'typecode' => 'LN-005',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'insurance' => [
            'beneficiary_primary_name' => 'Submitted Primary',
            'beneficiary_primary_relationship' => 'Spouse',
            'beneficiary_primary_birthdate' => '1992-01-01',
            'beneficiary_secondary_name' => 'Submitted Secondary',
            'beneficiary_secondary_relationship' => 'Sibling',
            'beneficiary_secondary_birthdate' => '1994-02-02',
        ],
        'health' => [
            'health_smoking_status' => 'none',
            'health_hypertension' => false,
        ],
        'health_glapi' => [
            'health_recent_hospitalization' => false,
        ],
        'banking' => [
            'release_method' => LoanReleaseMethod::BankTransfer->value,
            'release_saved_account_id' => $account->id,
            'payment_option' => LoanPaymentOption::AtmDeduction->value,
            'payment_saved_account_id' => $account->id,
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
        'applicant' => $person(['sex' => 'Male']),
        'co_maker_1' => $person(),
        'co_maker_2' => $person(),
    ]);

    $profile = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first();

    expect($profile)->not->toBeNull();
    expect($profile->beneficiary_primary_name)->toBe('Submitted Primary');
    expect($profile->beneficiary_primary_relationship)->toBe('Spouse');
    expect($profile->beneficiary_primary_birthdate->toDateString())->toBe('1992-01-01');
    expect($profile->beneficiary_secondary_name)->toBe('Submitted Secondary');
    expect($profile->beneficiary_secondary_birthdate->toDateString())->toBe('1994-02-02');
});

test('submit does not write back beneficiaries on a draft-only save', function (): void {
    $member = createBeneficiaryTestMember('003204');

    app(LoanRequestService::class)->saveDraft($member, [
        'insurance' => ['beneficiary_primary_name' => 'Draft Only Primary'],
    ]);

    $profile = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first();

    expect($profile->beneficiary_primary_name)->toBeNull();
});
