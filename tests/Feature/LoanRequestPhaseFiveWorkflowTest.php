<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
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
        ['typecode' => 'LN-P5'],
        ['lntype' => 'Phase Five Personal Loan'],
    );
});

test('member submission stores workflow version v2 and member-owned data entries', function (): void {
    $member = createPhaseFiveMember('000951', 'Phase', 'Member');

    $response = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), phaseFiveLoanRequestPayload($member));

    $loanRequest = LoanRequest::query()->sole();

    $response->assertRedirect(route('client.loan-requests.show', $loanRequest));

    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview)
        ->and($loanRequest->workflow_version)->toBe(
            LoanRequestWorkflowVersion::DocumentWorkflowV2,
        );

    $beneficiaryEntry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'beneficiary_primary_name')
        ->sole();

    $consentEntry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'declaration_data_privacy_consent')
        ->sole();

    expect($beneficiaryEntry->section_key)->toBe('insurance')
        ->and($beneficiaryEntry->confirmed_by_member)->toBeTrue()
        ->and($beneficiaryEntry->value_json)->toBe([
            'value' => 'Primary Beneficiary',
        ])
        ->and($consentEntry->section_key)->toBe('declarations')
        ->and($consentEntry->confirmed_by_member)->toBeTrue()
        ->and($consentEntry->value_json)->toBe([
            'value' => true,
        ]);
});

test('v2 recommendation remains blocked until processing recommendations are complete', function (): void {
    $processor = createPhaseFiveActor([Role::LOAN_PROCESSOR]);
    $member = createPhaseFiveActor([Role::MEMBER], acctno: '000952');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Attempting recommendation without completed document workflow data.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recommended_amount']);
});

test('legacy manager approval bypasses v2 document readiness gates', function (): void {
    $manager = createPhaseFiveActor([Role::LOAN_MANAGER]);
    $member = createPhaseFiveActor([Role::MEMBER], acctno: '000953');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 26000,
            'approved_term' => 18,
            'approved_interest_rate' => 1.25,
            'approved_payment_frequency' => 'Monthly',
            'approval_remarks' => 'Legacy approval path remains available.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Approved->value)
        ->assertJsonPath('data.loanRequest.approval_remarks', 'Legacy approval path remains available.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved)
        ->and($loanRequest->approved_amount)->toBe('26000.00')
        ->and($loanRequest->approved_term)->toBe(18)
        ->and($loanRequest->approved_interest_rate)->toBe('1.2500');
});

function createPhaseFiveMember(
    string $acctno,
    string $firstName,
    string $lastName,
): AppUser {
    $member = createPhaseFiveActor([Role::MEMBER], acctno: $acctno);

    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        [
            'fname' => $firstName,
            'lname' => $lastName,
            'bname' => sprintf('%s %s', $firstName, $lastName),
            'birthday' => '1990-04-10',
            'address' => 'Loan Street',
            'civilstat' => 'Single',
            'occupation' => 'Analyst',
        ],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

/**
 * @param  list<string>  $roles
 */
function createPhaseFiveActor(
    array $roles,
    ?string $acctno = null,
): AppUser {
    $actor = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $actor->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    $twoFactorRoles = [Role::SUPERADMIN, Role::LOAN_MANAGER];
    if (! empty(array_intersect($roles, $twoFactorRoles))) {
        $actor->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();
    }

    return $actor->fresh(['roles.permissions', 'staffAccessControl']);
}

/**
 * @return array<string, mixed>
 */
function phaseFiveLoanRequestPayload(AppUser $member): array
{
    $savedAccountId = $member->fresh('memberApplicationProfile')
        ->memberApplicationProfile
        ->release_saved_account_id;

    return [
        'typecode' => 'LN-P5',
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
            'release_method' => 'Bank Transfer',
            'release_saved_account_id' => $savedAccountId,
            'payment_option' => 'ATM Deduction',
            'payment_saved_account_id' => $savedAccountId,
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
            'first_name' => 'Phase',
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
