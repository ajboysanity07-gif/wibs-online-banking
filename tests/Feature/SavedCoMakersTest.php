<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\MemberCoMaker;
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

function createSavedCoMakerTestMember(string $acctno): AppUser
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
        ['fname' => 'CoMaker', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

function coMakerPersonPayload(array $overrides = []): array
{
    return array_merge([
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
}

function submitLoanWithCoMakers(AppUser $member, array $coMakerOneOverrides = [], array $coMakerTwoOverrides = []): void
{
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
        'applicant' => coMakerPersonPayload(['sex' => 'Male']),
        'co_maker_1' => coMakerPersonPayload($coMakerOneOverrides),
        'co_maker_2' => coMakerPersonPayload($coMakerTwoOverrides),
    ]);
}

test('submit does not save a co-maker for reuse unless explicitly opted in', function (): void {
    $member = createSavedCoMakerTestMember('004400');

    submitLoanWithCoMakers($member);

    expect(MemberCoMaker::query()->count())->toBe(0);
});

test('submit saves a co-maker for reuse when the borrower opts in', function (): void {
    $member = createSavedCoMakerTestMember('004401');

    submitLoanWithCoMakers($member, [
        'first_name' => 'Juan',
        'last_name' => 'DelaCruz',
        'save_for_reuse' => true,
        'saved_co_maker_label' => 'Juan - officemate',
    ]);

    $profile = MemberApplicationProfile::query()->where('user_id', $member->user_id)->first();

    $saved = MemberCoMaker::query()
        ->where('member_application_profile_id', $profile->id)
        ->first();

    expect($saved)->not->toBeNull();
    expect($saved->first_name)->toBe('Juan');
    expect($saved->last_name)->toBe('DelaCruz');
    expect($saved->label)->toBe('Juan - officemate');
    expect($saved->last_used_at)->not->toBeNull();
});

test('resubmitting with the same saved co-maker id updates it instead of duplicating', function (): void {
    $member = createSavedCoMakerTestMember('004402');

    submitLoanWithCoMakers($member, [
        'first_name' => 'Juan',
        'save_for_reuse' => true,
    ]);

    $profile = MemberApplicationProfile::query()->where('user_id', $member->user_id)->first();
    $saved = MemberCoMaker::query()->where('member_application_profile_id', $profile->id)->first();

    submitLoanWithCoMakers($member, [
        'first_name' => 'Juan',
        'last_name' => 'Updated',
        'save_for_reuse' => true,
        'saved_co_maker_id' => $saved->id,
    ]);

    expect(MemberCoMaker::query()->where('member_application_profile_id', $profile->id)->count())->toBe(1);
    expect($saved->fresh()->last_name)->toBe('Updated');
});

test('getFormData lists a member saved co-makers', function (): void {
    $member = createSavedCoMakerTestMember('004403');
    $profile = $member->memberApplicationProfile;

    MemberCoMaker::factory()->forProfile($profile)->create([
        'label' => 'Juan - officemate',
        'last_used_at' => now(),
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['savedCoMakers'])->toHaveCount(1);
    expect($formData['savedCoMakers'][0]['label'])->toBe('Juan - officemate');
});

test('a member cannot load or delete another members saved co-maker', function (): void {
    $owner = createSavedCoMakerTestMember('004404');
    $other = createSavedCoMakerTestMember('004405');

    $saved = MemberCoMaker::factory()->forProfile($owner->memberApplicationProfile)->create();

    $this->actingAs($other)
        ->getJson("/client/co-makers/{$saved->id}")
        ->assertNotFound();

    $this->actingAs($other)
        ->deleteJson("/client/co-makers/{$saved->id}")
        ->assertNoContent();

    expect(MemberCoMaker::query()->whereKey($saved->id)->exists())->toBeTrue();
});

test('the owning member can load and delete their own saved co-maker', function (): void {
    $member = createSavedCoMakerTestMember('004406');
    $saved = MemberCoMaker::factory()->forProfile($member->memberApplicationProfile)->create([
        'first_name' => 'Juan',
    ]);

    $this->actingAs($member)
        ->getJson("/client/co-makers/{$saved->id}")
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Juan');

    $this->actingAs($member)
        ->deleteJson("/client/co-makers/{$saved->id}")
        ->assertNoContent();

    expect(MemberCoMaker::query()->whereKey($saved->id)->exists())->toBeFalse();
});

test('barangay flows through applicant/co-maker submission and saved co-maker reuse', function (): void {
    $member = createSavedCoMakerTestMember('004407');

    submitLoanWithCoMakers($member, [
        'first_name' => 'Juan',
        'last_name' => 'DelaCruz',
        'address_barangay' => 'Barangay Uno',
        'employer_business_address_barangay' => 'Barangay Dos',
        'save_for_reuse' => true,
        'saved_co_maker_label' => 'Juan - officemate',
    ]);

    $loanRequest = LoanRequest::query()->first();
    $coMakerOne = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', 'co_maker_1')
        ->first();

    expect($coMakerOne->address_barangay)->toBe('Barangay Uno');
    expect($coMakerOne->employer_business_address_barangay)->toBe('Barangay Dos');
    expect($coMakerOne->composedAddress())->toContain('Barangay Uno');
    expect($coMakerOne->composedEmployerBusinessAddress())->toContain('Barangay Dos');

    $profile = MemberApplicationProfile::query()->where('user_id', $member->user_id)->first();
    $saved = MemberCoMaker::query()
        ->where('member_application_profile_id', $profile->id)
        ->first();

    expect($saved->address_barangay)->toBe('Barangay Uno');
    expect($saved->employer_business_address_barangay)->toBe('Barangay Dos');
});
