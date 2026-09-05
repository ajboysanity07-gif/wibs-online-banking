<?php

use App\Models\AppUser;
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

test('a validated submission writes the applicant address back onto the member profile under the home_ prefix', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => '660300',
        'email_verified_at' => now(),
    ]);
    $member->roles()->sync(Role::query()->where('name', Role::MEMBER)->pluck('id')->all());

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
        'home_address1' => 'Old Purok 1',
        'home_address2' => 'Old City',
        'home_address3' => 'Old Province',
        'home_address_barangay' => 'Old Barangay',
    ]);

    $member = $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);

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
            'release_method' => 'Check',
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
        'applicant' => addressSyncPersonPayload([
            'sex' => 'Male',
            'address1' => 'Purok 3',
            'address2' => 'Diatagon',
            'address3' => 'Surigao del Sur',
            'address_barangay' => 'Diatagon',
        ]),
        'co_maker_1' => addressSyncPersonPayload(),
        'co_maker_2' => addressSyncPersonPayload(),
    ]);

    $profile = $member->memberApplicationProfile()->firstOrFail()->refresh();

    expect($profile->home_address1)->toBe('Purok 3')
        ->and($profile->home_address2)->toBe('Diatagon')
        ->and($profile->home_address3)->toBe('Surigao del Sur')
        ->and($profile->home_address_barangay)->toBe('Diatagon');
});

test('a blank address_barangay from the wizard does not clobber an existing profile barangay value', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => '660301',
        'email_verified_at' => now(),
    ]);
    $member->roles()->sync(Role::query()->where('name', Role::MEMBER)->pluck('id')->all());

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
        'home_address1' => 'Purok 3',
        'home_address2' => 'Diatagon',
        'home_address3' => 'Surigao del Sur',
        'home_address_barangay' => 'Diatagon',
        'employer_business_address_barangay' => 'Diatagon',
    ]);

    $member = $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);

    // The wizard never actually renders a barangay field for the applicant
    // section -- it always submits address_barangay / employer_business_address_barangay
    // as an empty string placeholder (see loan-request.tsx initial form data).
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
            'release_method' => 'Check',
            'payment_option' => 'Cash',
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
        'applicant' => addressSyncPersonPayload([
            'sex' => 'Male',
            'address1' => 'Purok 3',
            'address2' => 'Diatagon',
            'address3' => 'Surigao del Sur',
            'address_barangay' => '',
            'employer_business_address_barangay' => '',
        ]),
        'co_maker_1' => addressSyncPersonPayload(),
        'co_maker_2' => addressSyncPersonPayload(),
    ]);

    $profile = $member->memberApplicationProfile()->firstOrFail()->refresh();

    expect($profile->home_address_barangay)->toBe('Diatagon')
        ->and($profile->employer_business_address_barangay)->toBe('Diatagon')
        ->and($member->fresh()->memberApplicationProfileIsComplete())->toBeTrue();
});

function addressSyncPersonPayload(array $overrides = []): array
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
