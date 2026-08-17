<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\MemberDependent;
use App\Models\MemberDependentProfile;
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

function createDependentsTestMember(string $acctno): AppUser
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
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Dependents', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

test('getFormData prefills dependents from the member profile and flags it', function (): void {
    $member = createDependentsTestMember('003300');

    $dependentProfile = MemberDependentProfile::query()->create([
        'member_application_profile_id' => $member->memberApplicationProfile->id,
    ]);

    MemberDependent::query()->create([
        'member_dependent_profile_id' => $dependentProfile->id,
        'category' => 'sibling',
        'slot' => 1,
        'name' => 'Profile Sibling',
        'birthdate' => '1995-03-04',
        'cycle_status' => 'Old',
        'cycle_number' => 2,
    ]);

    $dependentProfile->update(['spouse_cycle_status' => 'New']);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['dependentsPrefilledFromProfile'])->toBeTrue();
    expect($formData['dataSections']['dependents']['dependent_sibling_1_name'])->toBe('Profile Sibling');
    expect($formData['dataSections']['dependents']['dependent_sibling_1_birthdate'])->toBe('1995-03-04');
    expect($formData['dataSections']['dependents']['dependent_sibling_1_cycle_status'])->toBe('Old');
    expect($formData['dataSections']['dependents']['dependent_sibling_1_cycle_number'])->toBe('2');
    expect($formData['dataSections']['dependents']['dependent_spouse_cycle_status'])->toBe('New');
});

test('getFormData does not flag dependents prefill when the profile has no dependent rows', function (): void {
    $member = createDependentsTestMember('003301');

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['dependentsPrefilledFromProfile'])->toBeFalse();
    expect($formData['dataSections']['dependents']['dependent_sibling_1_name'])->toBeNull();
});

test('getFormData does not overwrite dependent values already saved on the draft', function (): void {
    $member = createDependentsTestMember('003302');

    $dependentProfile = MemberDependentProfile::query()->create([
        'member_application_profile_id' => $member->memberApplicationProfile->id,
    ]);

    MemberDependent::query()->create([
        'member_dependent_profile_id' => $dependentProfile->id,
        'category' => 'sibling',
        'slot' => 1,
        'name' => 'Profile Sibling',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    app(\App\Services\LoanRequests\LoanRequestDataService::class)->syncMemberSections($loanRequest, [
        'dependents' => ['dependent_sibling_1_name' => 'Member Edited Sibling'],
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['dependentsPrefilledFromProfile'])->toBeFalse();
    expect($formData['dataSections']['dependents']['dependent_sibling_1_name'])->toBe('Member Edited Sibling');
});

test('submit writes back validated dependent fields to normalized profile tables', function (): void {
    $member = createDependentsTestMember('003303');

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
        'civil_status' => 'Married',
        'educational_attainment' => 'College',
        'number_of_children' => 1,
        'spouse_name' => 'Spouse',
        'spouse_age' => 30,
        'spouse_cell_no' => '09123456780',
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
            'beneficiary_primary_name' => 'Primary',
            'beneficiary_primary_relationship' => 'Spouse',
            'beneficiary_primary_birthdate' => '1992-01-01',
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
            'release_method' => 'ATM',
            'payment_option' => 'ATM Deduction',
            'payout_atm_number' => '9876543210',
            'payout_bank_branch' => 'Main Branch',
            'payout_atm_holder_name' => null,
            'payment_bank_name' => 'WIBS Cooperative Bank',
            'payment_account_name' => 'Loan Member',
            'payment_account_number' => '1234567890',
            'payment_account_type' => 'Savings',
            'payment_atm_number' => '9876543210',
            'payment_bank_branch' => 'Main Branch',
            'payment_atm_holder_name' => null,
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
            'dependent_child_1_name' => 'Submitted Child',
            'dependent_child_1_birthdate' => '2015-06-07',
            'dependent_child_1_cycle_status' => 'Old',
            'dependent_child_1_cycle_number' => 1,
            'dependent_parent_1_name' => 'Submitted Parent',
            'dependent_spouse_cycle_status' => 'New',
            'applicant_cycle_status' => 'New',
        ],
        'applicant' => $person(['sex' => 'Male']),
        'co_maker_1' => $person(),
        'co_maker_2' => $person(),
    ]);

    $profile = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first();

    $dependentProfile = MemberDependentProfile::query()
        ->where('member_application_profile_id', $profile->id)
        ->first();

    expect($dependentProfile)->not->toBeNull();

    $child = MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'child')
        ->where('slot', 1)
        ->first();

    expect($child)->not->toBeNull();
    expect($child->name)->toBe('Submitted Child');
    expect($child->birthdate->toDateString())->toBe('2015-06-07');
    expect($child->cycle_status)->toBe('Old');
    expect($child->cycle_number)->toBe(1);

    expect($dependentProfile->spouse_cycle_status)->toBe('New');

    $parent = MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'parent')
        ->where('slot', 1)
        ->first();

    expect($parent)->not->toBeNull();
    expect($parent->name)->toBe('Submitted Parent');

    $sibling = MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'sibling')
        ->first();

    expect($sibling)->toBeNull();
});

test('submit does not write back dependents on a draft-only save', function (): void {
    $member = createDependentsTestMember('003304');

    app(LoanRequestService::class)->saveDraft($member, [
        'dependents' => ['dependent_child_1_name' => 'Draft Only Child'],
    ]);

    $profile = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first();

    expect($profile?->dependentProfile)->toBeNull();
});
