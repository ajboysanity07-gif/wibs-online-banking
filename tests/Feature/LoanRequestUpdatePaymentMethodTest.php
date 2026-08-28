<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\MemberApplicationProfile;
use App\Models\MemberPaymentAccount;
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

function createPaymentMethodTestMember(string $acctno): AppUser
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
        ['fname' => 'Payment', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Bank St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

function paymentMethodPersonPayload(array $overrides = []): array
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

function submitPaymentMethodTestLoan(AppUser $member): LoanRequest
{
    return app(LoanRequestService::class)->submit($member, [
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
        'applicant' => paymentMethodPersonPayload(['sex' => 'Male']),
        'co_maker_1' => paymentMethodPersonPayload(),
        'co_maker_2' => paymentMethodPersonPayload(),
    ]);
}

test('a member can update their release and repayment method using a saved account while awaiting review', function (): void {
    $member = createPaymentMethodTestMember('005600');
    $loanRequest = submitPaymentMethodTestLoan($member);

    expect($loanRequest->fresh()->status)->toBe(LoanRequestStatus::PendingReview);

    $account = MemberPaymentAccount::factory()->forProfile($member->memberApplicationProfile)->create([
        'bank_name' => 'BPI',
        'account_name' => 'Payment Member',
        'account_number' => '9988776655',
        'account_type' => 'Savings',
        'atm_number' => '1122334455667788',
    ]);

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
            'release_saved_account_id' => $account->id,
            'payment_option' => 'ATM Deduction',
            'payment_saved_account_id' => $account->id,
        ])
        ->assertOk();

    expect(LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('action', LoanRequestChange::ACTION_MEMBER_UPDATED_PAYMENT_METHOD)
        ->exists())->toBeTrue();

    expect($account->fresh()->last_used_at)->not->toBeNull();
});

test('selecting a saved account round-trips bank branch and ATM holder name into the loan request data', function (): void {
    // Guards the regression where the saved-account picker silently dropped
    // the "Bank branch" and "ATM card holder name" inputs, so any account
    // saved through it rendered blank on the Authorization / Affidavit PDFs.
    $member = createPaymentMethodTestMember('005608');
    $loanRequest = submitPaymentMethodTestLoan($member);

    $account = MemberPaymentAccount::factory()->forProfile($member->memberApplicationProfile)->create([
        'bank_name' => 'BPI',
        'account_name' => 'Payment Member',
        'account_number' => '9988776655',
        'account_type' => 'Savings',
        'atm_number' => '1122334455667788',
        'bank_branch' => 'Rizal Ave Branch',
        'atm_holder_name' => 'Payment Member',
    ]);

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
            'release_saved_account_id' => $account->id,
            'payment_option' => 'ATM Deduction',
            'payment_saved_account_id' => $account->id,
        ])
        ->assertOk();

    $loanRequest->unsetRelation('dataEntries');
    $flat = app(\App\Services\LoanRequests\LoanRequestDataService::class)
        ->loadFlatValues($loanRequest->refresh());

    foreach (['payout_', 'payment_'] as $prefix) {
        expect($flat["{$prefix}bank_branch"])->toBe('Rizal Ave Branch')
            ->and($flat["{$prefix}atm_holder_name"])->toBe('Payment Member');
    }
});

test('the selected saved account ids are persisted and returned through serializeSections', function (): void {
    // Guards a regression where the saved-account picker dropped the
    // release_saved_account_id / payment_saved_account_id link, so the review
    // page could never re-display the previously-chosen saved bank/ATM account.
    $member = createPaymentMethodTestMember('005609');
    $loanRequest = submitPaymentMethodTestLoan($member);

    $account = MemberPaymentAccount::factory()->forProfile($member->memberApplicationProfile)->create([
        'bank_name' => 'BPI',
        'account_name' => 'Payment Member',
        'account_number' => '9988776655',
        'account_type' => 'Savings',
    ]);

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
            'release_saved_account_id' => $account->id,
            'payment_option' => 'ATM Deduction',
            'payment_saved_account_id' => $account->id,
        ])
        ->assertOk();

    $loanRequest->unsetRelation('dataEntries');
    $sections = app(\App\Services\LoanRequests\LoanRequestDataService::class)
        ->serializeSections($loanRequest->refresh());

    expect($sections['banking']['release_saved_account_id'])->toBe($account->id)
        ->and($sections['banking']['payment_saved_account_id'])->toBe($account->id);
});

test('a member can still update their payment method while a processor is reviewing the request', function (): void {
    $member = createPaymentMethodTestMember('005601');
    $loanRequest = submitPaymentMethodTestLoan($member);

    $loanRequest->forceFill(['status' => LoanRequestStatus::UnderReview])->save();
    $account = MemberPaymentAccount::factory()->forProfile($member->memberApplicationProfile)->create();

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
            'release_saved_account_id' => $account->id,
        ])
        ->assertOk();
});

test('a member cannot update their payment method once the loan manager approves the request', function (): void {
    $member = createPaymentMethodTestMember('005604');
    $loanRequest = submitPaymentMethodTestLoan($member);

    $loanRequest->forceFill(['status' => LoanRequestStatus::Approved])->save();

    $account = MemberPaymentAccount::factory()->forProfile($member->memberApplicationProfile)->create();

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
            'release_saved_account_id' => $account->id,
        ])
        ->assertUnprocessable();
});

test('a member cannot update the payment method of another members loan request', function (): void {
    $owner = createPaymentMethodTestMember('005602');
    $other = createPaymentMethodTestMember('005603');
    $loanRequest = submitPaymentMethodTestLoan($owner);

    $account = MemberPaymentAccount::factory()->forProfile($other->memberApplicationProfile)->create();

    $this->actingAs($other)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
            'release_saved_account_id' => $account->id,
        ])
        ->assertNotFound();
});

test('ATM release method requires a saved account to be selected', function (): void {
    $member = createPaymentMethodTestMember('005604');
    $loanRequest = submitPaymentMethodTestLoan($member);

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['release_saved_account_id']);
});

test('ATM Deduction repayment method requires a saved account to be selected', function (): void {
    $member = createPaymentMethodTestMember('005605');
    $loanRequest = submitPaymentMethodTestLoan($member);

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'payment_option' => 'ATM Deduction',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_saved_account_id']);
});

test('a member cannot use another members saved payment account', function (): void {
    $member = createPaymentMethodTestMember('005606');
    $other = createPaymentMethodTestMember('005607');
    $loanRequest = submitPaymentMethodTestLoan($member);

    $otherAccount = MemberPaymentAccount::factory()->forProfile($other->memberApplicationProfile)->create();

    $this->actingAs($member)
        ->patchJson("/client/loans/requests/{$loanRequest->id}/payment-method", [
            'release_method' => 'ATM',
            'release_saved_account_id' => $otherAccount->id,
        ])
        ->assertUnprocessable();
});
