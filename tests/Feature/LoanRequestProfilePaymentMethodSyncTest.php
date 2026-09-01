<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestNotificationEvent;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestProcessingService;
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

test('profile payment method sync updates in-flight loan requests but skips terminal ones', function (): void {
    $member = paymentSyncMember('660200');
    $activeRequest = paymentSyncLoanRequest($member);
    $approvedRequest = paymentSyncLoanRequest($member);
    $approvedRequest->forceFill(['status' => LoanRequestStatus::Approved])->save();

    $updated = app(LoanRequestProcessingService::class)->syncPaymentMethodFromProfile($member, [
        'payment_option' => 'Cash',
        'release_method' => 'Cash',
    ]);

    expect($updated)->toBe(1);

    $activeFlatValues = app(\App\Services\LoanRequests\LoanRequestDataService::class)
        ->loadFlatValues($activeRequest->refresh());
    expect($activeFlatValues['payment_option'])->toBe('Cash')
        ->and($activeFlatValues['release_method'])->toBe('Cash');

    $approvedFlatValues = app(\App\Services\LoanRequests\LoanRequestDataService::class)
        ->loadFlatValues($approvedRequest->refresh());
    expect($approvedFlatValues['payment_option'])->toBe('Salary Deduction')
        ->and($approvedFlatValues['release_method'])->toBe('Check');

    expect(LoanRequestChange::query()
        ->where('loan_request_id', $activeRequest->id)
        ->where('action', LoanRequestChange::ACTION_MEMBER_UPDATED_PAYMENT_METHOD)
        ->exists())->toBeTrue();
});

test('profile payment method sync is a no-op when the snapshot already matches', function (): void {
    $member = paymentSyncMember('660201');
    $activeRequest = paymentSyncLoanRequest($member);

    $updated = app(LoanRequestProcessingService::class)->syncPaymentMethodFromProfile($member, [
        'payment_option' => 'Salary Deduction',
        'release_method' => 'Check',
    ]);

    expect($updated)->toBe(0);
    expect(LoanRequestChange::query()
        ->where('loan_request_id', $activeRequest->id)
        ->exists())->toBeFalse();
});

test('saving the profile propagates a payment method change to an active loan request and notifies the processor', function (): void {
    $processor = paymentSyncActor([Role::LOAN_PROCESSOR]);
    $member = paymentSyncMember('660202');
    $activeRequest = paymentSyncLoanRequest($member, [
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);

    $this
        ->actingAs($member)
        ->patch(route('profile.update'), paymentSyncProfileUpdatePayload([
            'payment_option' => 'Cash',
            'release_method' => 'Cash',
        ]))
        ->assertSessionHasNoErrors();

    $flatValues = app(\App\Services\LoanRequests\LoanRequestDataService::class)
        ->loadFlatValues($activeRequest->refresh());
    expect($flatValues['payment_option'])->toBe('Cash')
        ->and($flatValues['release_method'])->toBe('Cash');

    expect(LoanRequestNotificationEvent::query()
        ->where('loan_request_id', $activeRequest->id)
        ->where('event_type', 'payment_method_updated_by_member')
        ->where('recipient_user_id', $processor->user_id)
        ->exists())->toBeTrue();
});

test('the loan manager is also notified when the payment method changes while the request awaits their approval decision', function (): void {
    $processor = paymentSyncActor([Role::LOAN_PROCESSOR]);
    $manager = paymentSyncActor([Role::LOAN_MANAGER]);
    $member = paymentSyncMember('660203');
    $loanRequest = paymentSyncLoanRequest($member, [
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $processor->user_id,
    ]);

    app(LoanRequestProcessingService::class)->updatePaymentMethodByMember($loanRequest, $member, [
        'payment_option' => 'Cash',
        'release_method' => 'Cash',
    ]);

    expect(LoanRequestNotificationEvent::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('event_type', 'payment_method_updated_by_member')
        ->where('recipient_user_id', $manager->user_id)
        ->exists())->toBeTrue();
});

test('the loan manager is not notified when the payment method changes before manager review', function (): void {
    $processor = paymentSyncActor([Role::LOAN_PROCESSOR]);
    $manager = paymentSyncActor([Role::LOAN_MANAGER]);
    $member = paymentSyncMember('660204');
    $loanRequest = paymentSyncLoanRequest($member, [
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);

    app(LoanRequestProcessingService::class)->updatePaymentMethodByMember($loanRequest, $member, [
        'payment_option' => 'Cash',
        'release_method' => 'Cash',
    ]);

    expect(LoanRequestNotificationEvent::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('event_type', 'payment_method_updated_by_member')
        ->where('recipient_user_id', $manager->user_id)
        ->exists())->toBeFalse();
});

/**
 * @param  list<string>  $roles
 */
function paymentSyncActor(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()->whereIn('name', $roles)->pluck('id')->all(),
    );

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

function paymentSyncMember(string $acctno): AppUser
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
        'civil_status' => null,
        'payment_option' => 'Salary Deduction',
        'release_method' => 'Check',
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Payment', 'lname' => 'Sync', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

function paymentSyncPersonPayload(array $overrides = []): array
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

/**
 * The loan request is submitted with a "Salary Deduction / Check" payment
 * method -- the snapshot the profile-driven sync must later overwrite once
 * the member's profile default diverges from it (e.g. switches to Cash).
 */
function paymentSyncLoanRequest(AppUser $member, array $extra = []): LoanRequest
{
    $loanRequest = app(LoanRequestService::class)->submit($member, [
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
        'applicant' => paymentSyncPersonPayload(['sex' => 'Male']),
        'co_maker_1' => paymentSyncPersonPayload(),
        'co_maker_2' => paymentSyncPersonPayload(),
    ]);

    if ($extra !== []) {
        $loanRequest->forceFill($extra)->save();
    }

    return $loanRequest->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentSyncProfileUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'PaymentSync',
        'email' => 'payment.sync@example.com',
        'phoneno' => '09123456789',
        'birthplace_city' => 'City of Batac',
        'birthplace_province' => 'Ilocos Norte',
        'educational_attainment' => 'High School',
        'length_of_stay' => '2 years',
        'home_address1' => '123 Main Street',
        'home_address_barangay' => 'Aglipay',
        'home_address2' => 'Batac City',
        'home_address3' => 'Ilocos Norte',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'employment_type' => 'Regular',
        'employer_business_name' => 'Acme Corp',
        'employer_business_address_barangay' => 'Aglipay',
        'employer_business_address2' => 'Batac City',
        'employer_business_address3' => 'Ilocos Norte',
        'current_position' => 'Analyst',
        'gross_monthly_income' => '5000.00',
        'payday' => 'Quincenal',
        'release_method' => 'Cash',
        'height_cm' => '165',
        'weight_kg' => '68',
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_number' => '1234567890',
    ], $overrides);
}
