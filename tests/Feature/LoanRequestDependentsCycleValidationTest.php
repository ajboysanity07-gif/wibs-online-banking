<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
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
});

function createDependentsCycleTestMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    \App\Models\MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Cycle', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('sectionDefinitions gates each dependent category by civil_status per the physical form', function (): void {
    $fields = (new LoanRequestDataService)->sectionDefinitions()['dependents']['fields'];

    // Spouse and Children: Married-only.
    expect($fields['dependent_spouse_cycle_status']['visible_when'])->toBe([
        'field' => 'applicant.civil_status',
        'equals' => 'Married',
    ]);
    expect($fields['dependent_child_1_name']['visible_when'])->toBe([
        'field' => 'applicant.civil_status',
        'equals' => 'Married',
    ]);

    // Siblings and Parents: Single-only.
    expect($fields['dependent_sibling_1_name']['visible_when'])->toBe([
        'field' => 'applicant.civil_status',
        'equals' => 'Single',
    ]);
    expect($fields['dependent_parent_1_name']['visible_when'])->toBe([
        'field' => 'applicant.civil_status',
        'equals' => 'Single',
    ]);

    // Extended: never gated.
    expect($fields['dependent_extended_1_name']['visible_when'])->toBeNull();
});

test('sectionDefinitions no longer exposes relationship or occupation for any dependent category', function (): void {
    $fields = (new LoanRequestDataService)->sectionDefinitions()['dependents']['fields'];

    foreach (array_keys($fields) as $fieldKey) {
        expect($fieldKey)->not->toEndWith('_relationship');
        expect($fieldKey)->not->toEndWith('_occupation');
    }
});

test('draft endpoint accepts New/Old cycle status and cycle number for a dependent slot', function (): void {
    $member = createDependentsCycleTestMember('002200');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'dependents' => [
                'dependent_extended_1_name' => 'Aunt Extended',
                'dependent_extended_1_cycle_status' => 'Old',
                'dependent_extended_1_cycle_number' => 2,
            ],
        ])
        ->assertNoContent();

    $values = collect($loanRequest->fresh()->dataEntries)
        ->mapWithKeys(fn ($entry) => [$entry->field_key => $entry->value_json['value'] ?? null]);

    expect($values->get('dependent_extended_1_cycle_status'))->toBe('Old');
    expect($values->get('dependent_extended_1_cycle_number'))->toBe('2');
});

test('draft endpoint rejects a cycle status outside New/Old', function (): void {
    $member = createDependentsCycleTestMember('002201');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'dependents' => [
                'dependent_extended_1_cycle_status' => 'Ancient',
            ],
        ])
        ->assertUnprocessable();
});

test('store endpoint accepts cycle status without cycle number (auto-computed server-side)', function (): void {
    $member = createDependentsCycleTestMember('002202');

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
        'payday' => 'Quincenal',
    ], $overrides);

    $response = $this->actingAs($member)
        ->post(route('client.loan-requests.store'), [
            'typecode' => 'LN-005',
            'requested_amount' => 15000,
            'requested_term' => 12,
            'loan_purpose' => 'Medical expenses',
            'availment_status' => 'New',
            'undertaking_accepted' => true,
            'insurance' => [
                'beneficiary_primary_name' => 'Primary',
                'beneficiary_primary_relationship' => 'Sister',
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
                'dependent_sibling_1_name' => 'Sibling Without Cycle Number',
                'dependent_sibling_1_cycle_status' => 'Old',
            ],
            'applicant' => $person(['sex' => 'Male']),
            'co_maker_1' => $person(),
            'co_maker_2' => $person(),
        ]);

    $response->assertSessionDoesntHaveErrors(['dependents.dependent_sibling_1_cycle_number']);
});

/**
 * @param  array<string, mixed>  $dependentOverrides
 */
function submitLoanRequestForCycleStatusTest(
    AppUser $member,
    array $dependentOverrides,
    string $applicantCivilStatus = 'Single',
): \Illuminate\Testing\TestResponse {
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
        'payday' => 'Quincenal',
    ], $overrides);

    return test()->actingAs($member)
        ->post(route('client.loan-requests.store'), [
            'typecode' => 'LN-005',
            'requested_amount' => 15000,
            'requested_term' => 12,
            'loan_purpose' => 'Medical expenses',
            'availment_status' => 'New',
            'undertaking_accepted' => true,
            'insurance' => [
                'beneficiary_primary_name' => 'Primary',
                'beneficiary_primary_relationship' => 'Sister',
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
            'dependents' => array_merge([
                'applicant_cycle_status' => 'New',
            ], $dependentOverrides),
            'applicant' => $person([
                'sex' => 'Male',
                'civil_status' => $applicantCivilStatus,
            ]),
            'co_maker_1' => $person(),
            'co_maker_2' => $person(),
        ]);
}

test('store endpoint requires applicant cycle status', function (): void {
    $member = createDependentsCycleTestMember('002203');

    $response = submitLoanRequestForCycleStatusTest($member, [
        'applicant_cycle_status' => null,
    ]);

    $response->assertSessionHasErrors(['dependents.applicant_cycle_status']);
});

test('store endpoint requires cycle status for a dependent slot with a name', function (): void {
    $member = createDependentsCycleTestMember('002204');

    $response = submitLoanRequestForCycleStatusTest($member, [
        'dependent_sibling_1_name' => 'Sibling Without Cycle Status',
    ]);

    $response->assertSessionHasErrors(['dependents.dependent_sibling_1_cycle_status']);
});

test('store endpoint requires spouse cycle status when applicant is married', function (): void {
    $member = createDependentsCycleTestMember('002205');

    $response = submitLoanRequestForCycleStatusTest($member, [], 'Married');

    $response->assertSessionHasErrors(['dependents.dependent_spouse_cycle_status']);
});

test('store endpoint does not require spouse cycle status when applicant is single', function (): void {
    $member = createDependentsCycleTestMember('002206');

    $response = submitLoanRequestForCycleStatusTest($member, [], 'Single');

    $response->assertSessionDoesntHaveErrors(['dependents.dependent_spouse_cycle_status']);
});

test('store endpoint accepts a fully completed dependent cycle status payload', function (): void {
    $member = createDependentsCycleTestMember('002207');

    $response = submitLoanRequestForCycleStatusTest($member, [
        'dependent_sibling_1_name' => 'Sibling With Cycle Status',
        'dependent_sibling_1_cycle_status' => 'New',
    ]);

    $response->assertSessionDoesntHaveErrors([
        'dependents.applicant_cycle_status',
        'dependents.dependent_sibling_1_cycle_status',
    ]);
});
