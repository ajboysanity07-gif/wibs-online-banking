<?php

use App\LoanPaymentOption;
use App\LoanReleaseMethod;
use App\LoanRequestPersonRole;
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
            $table->string('zone_number')->nullable();
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

function createBankingTestMember(string $acctno, array $profileOverrides = []): AppUser
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
        ['fname' => 'Banking', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Bank St', 'zone_number' => '8307'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

/**
 * @return array<string, mixed>
 */
function savedAccountPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Primary',
        'bank_name' => 'WIBS Cooperative Bank',
        'account_name' => 'Loan Member',
        'account_number' => '1234567890',
        'account_type' => 'Savings',
        'atm_number' => '9876543210',
        'bank_branch' => 'Main Branch',
        'atm_holder_name' => null,
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function fullLoanRequestSubmitPayload(int $releaseAccountId): array
{
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
        'address_zip' => '8307',
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
        'employer_business_address_zip' => '8100',
        'telephone_no' => '021234567',
        'current_position' => 'Analyst',
        'nature_of_business' => 'Finance',
        'years_in_work_business' => '3 years',
        'gross_monthly_income' => 25000,
        'payday' => '15th & 30th',
    ], $overrides);

    return [
        'typecode' => 'LN-005',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Medical expenses',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'insurance' => [
            'beneficiary_primary_name' => 'Primary Beneficiary',
            'beneficiary_primary_relationship' => 'Sibling',
            'beneficiary_primary_birthdate' => '1992-01-01',
            'beneficiary_secondary_name' => null,
            'beneficiary_secondary_relationship' => null,
            'beneficiary_secondary_birthdate' => null,
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
            'release_saved_account_id' => $releaseAccountId,
            'payment_option' => LoanPaymentOption::AtmDeduction->value,
            'payment_saved_account_id' => $releaseAccountId,
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
    ];
}

test('getFormData prefills Bank & payout from the member profile and flags it', function (): void {
    $member = createBankingTestMember('003100');
    $profile = $member->memberApplicationProfile;

    $account = $profile->paymentAccounts()->create(savedAccountPayload([
        'bank_name' => 'Profile Bank',
        'account_name' => 'Profile Holder',
        'account_number' => '111222333',
    ]));

    $profile->forceFill([
        'release_method' => LoanReleaseMethod::BankTransfer->value,
        'release_saved_account_id' => $account->id,
        'payment_option' => LoanPaymentOption::AtmDeduction->value,
        'payment_saved_account_id' => $account->id,
    ])->save();

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['bankingPrefilledFromProfile'])->toBeTrue();
    expect($formData['dataSections']['banking']['release_saved_account_id'])->toBe($account->id);
    expect($formData['dataSections']['banking']['payment_saved_account_id'])->toBe($account->id);
});

test('getFormData does not flag prefill when the profile has no banking data', function (): void {
    $member = createBankingTestMember('003101', ['release_method' => null]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['bankingPrefilledFromProfile'])->toBeFalse();
    expect($formData['dataSections']['banking']['release_saved_account_id'])->toBeNull();
    expect($formData['dataSections']['banking']['payment_saved_account_id'])->toBeNull();
});

test('getFormData does not overwrite banking values already saved on the draft', function (): void {
    $member = createBankingTestMember('003102', ['release_method' => null]);
    $profile = $member->memberApplicationProfile;

    $profileAccount = $profile->paymentAccounts()->create(savedAccountPayload([
        'bank_name' => 'Profile Bank',
        'account_name' => 'Profile Holder',
    ]));

    $profile->forceFill([
        'release_saved_account_id' => $profileAccount->id,
    ])->save();

    $draftAccount = $profile->paymentAccounts()->create(savedAccountPayload([
        'bank_name' => 'Draft Bank',
        'account_name' => 'Draft Holder',
    ]));

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    app(\App\Services\LoanRequests\LoanRequestDataService::class)->syncMemberSections($loanRequest, [
        'banking' => ['release_saved_account_id' => $draftAccount->id],
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['bankingPrefilledFromProfile'])->toBeFalse();
    expect($formData['dataSections']['banking']['release_saved_account_id'])->toBe($draftAccount->id);
});

test('submit writes back validated banking and applicant fields to the member profile', function (): void {
    $member = createBankingTestMember('003103', [
        'release_method' => LoanReleaseMethod::BankTransfer->value,
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'TIN',
        'id_number' => '123-456-789',
        'height_cm' => '165',
        'weight_kg' => '68',
    ]);
    $profile = $member->memberApplicationProfile;

    $account = $profile->paymentAccounts()->create(savedAccountPayload([
        'bank_name' => 'Placeholder Bank',
        'account_name' => 'Placeholder Holder',
        'account_number' => '000000000',
    ]));

    $profile->forceFill([
        'payment_option' => LoanPaymentOption::AtmDeduction->value,
        'release_saved_account_id' => $account->id,
        'payment_saved_account_id' => $account->id,
    ])->save();

    app(LoanRequestService::class)->submit($member, fullLoanRequestSubmitPayload($account->id));

    $profile = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first();

    expect($profile)->not->toBeNull();
    expect($profile->release_saved_account_id)->toBe($account->id);
    expect($profile->payment_saved_account_id)->toBe($account->id);
    expect($profile->release_method)->toBe(LoanReleaseMethod::BankTransfer->value);
    expect($profile->employer_business_name)->toBe('Company');
    expect($profile->current_position)->toBe('Analyst');
});

test('submit does not write back on a draft-only save', function (): void {
    $member = createBankingTestMember('003104', ['release_method' => null]);

    app(LoanRequestService::class)->saveDraft($member, [
        'banking' => [
            'release_method' => LoanReleaseMethod::BankTransfer->value,
        ],
    ]);

    $profile = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first();

    expect($profile->release_saved_account_id)->toBeNull();
});

test('submit persists home and office zip codes on the applicant snapshot', function (): void {
    $member = createBankingTestMember('003105', [
        'release_method' => LoanReleaseMethod::BankTransfer->value,
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'TIN',
        'id_number' => '123-456-789',
        'height_cm' => '165',
        'weight_kg' => '68',
    ]);
    $profile = $member->memberApplicationProfile;

    $account = $profile->paymentAccounts()->create(savedAccountPayload());

    $profile->forceFill([
        'payment_option' => LoanPaymentOption::AtmDeduction->value,
        'release_saved_account_id' => $account->id,
        'payment_saved_account_id' => $account->id,
    ])->save();

    $loanRequest = app(LoanRequestService::class)->submit($member, fullLoanRequestSubmitPayload($account->id));

    $applicant = $loanRequest->people()
        ->where('role', LoanRequestPersonRole::Applicant)
        ->first();

    expect($applicant)->not->toBeNull();
    expect($applicant->address_zip)->toBe('8307');
    expect($applicant->employer_business_address_zip)->toBe('8100');
});
