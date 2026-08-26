<?php

use App\LoanRequestPersonRole;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
        });
    }
});

// ---------------------------------------------------------------------------
// Purge migration: co-maker civil/housing nulled, auth data-entry rows deleted
// ---------------------------------------------------------------------------

test('purge migration nulls housing_status and civil_status for co-makers only', function () {
    $loanRequest = LoanRequest::factory()->create();

    $applicant = LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['housing_status' => 'OWNED', 'civil_status' => 'Married']);

    $coMakerOne = LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create(['housing_status' => 'RENT', 'civil_status' => 'Single']);

    $coMakerTwo = LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create(['housing_status' => 'OWNED', 'civil_status' => 'Widowed']);

    // Simulate what the purge migration does.
    LoanRequestPerson::query()
        ->whereIn('role', [
            LoanRequestPersonRole::CoMakerOne->value,
            LoanRequestPersonRole::CoMakerTwo->value,
        ])
        ->update(['civil_status' => null, 'housing_status' => null]);

    expect($applicant->fresh()->housing_status)->toBe('OWNED');
    expect($applicant->fresh()->civil_status)->toBe('Married');
    expect($coMakerOne->fresh()->housing_status)->toBeNull();
    expect($coMakerOne->fresh()->civil_status)->toBeNull();
    expect($coMakerTwo->fresh()->housing_status)->toBeNull();
    expect($coMakerTwo->fresh()->civil_status)->toBeNull();
});

test('purge migration deletes authorization_reason and authorized_recipient_contact data entries', function () {
    $loanRequest = LoanRequest::factory()->create();

    foreach (['authorization_reason', 'authorized_recipient_contact', 'authorized_recipient_name'] as $key) {
        LoanRequestDataEntry::factory()->create([
            'loan_request_id' => $loanRequest->id,
            'field_key' => $key,
            'section_key' => 'processing',
            'owner_type' => 'staff',
        ]);
    }

    expect(LoanRequestDataEntry::query()->where('loan_request_id', $loanRequest->id)->count())->toBe(3);

    // Simulate what the purge migration does.
    LoanRequestDataEntry::query()
        ->whereIn('field_key', ['authorization_reason', 'authorized_recipient_contact'])
        ->delete();

    $remaining = LoanRequestDataEntry::query()
        ->where('loan_request_id', $loanRequest->id)
        ->pluck('field_key')
        ->all();

    expect($remaining)->toBe(['authorized_recipient_name']);
});

// ---------------------------------------------------------------------------
// Validation: SaveDraftRequest ignores civil/housing for co-makers
// ---------------------------------------------------------------------------

test('save draft request does not persist co-maker civil_status or housing_status', function () {
    $user = User::factory()->create(['acctno' => '000801']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $user->user_id]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Draft, Member',
        'fname' => 'Member',
        'lname' => 'Draft',
    ]);

    $this->actingAs($user)->patch(route('client.loan-requests.draft'), [
        'co_maker_1' => [
            'first_name' => 'Co',
            'last_name' => 'Maker',
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
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => 'Quincenal',
        ],
    ]);

    $coMaker = LoanRequestPerson::query()
        ->where('role', LoanRequestPersonRole::CoMakerOne->value)
        ->first();

    expect($coMaker)->not->toBeNull();
    expect($coMaker->housing_status)->toBeNull();
    expect($coMaker->civil_status)->toBeNull();
});

// ---------------------------------------------------------------------------
// Validation: authorization_reason and authorized_recipient_contact rejected
// ---------------------------------------------------------------------------

test('save draft request strips authorized_recipient_contact from authorization payload', function () {
    $user = User::factory()->create(['acctno' => '000802']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $user->user_id]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Draft, Member',
        'fname' => 'Member',
        'lname' => 'Draft',
    ]);

    $response = $this->actingAs($user)->patch(route('client.loan-requests.draft'), [
        'authorization' => [
            'authorized_recipient_name' => 'Test Recipient',
            'authorized_recipient_contact' => '09123456789',
            'authorization_reason' => 'Out of town',
            'release_method' => 'Bank transfer',
        ],
    ]);

    // The request should succeed (fields are stripped, not rejected with 422).
    $response->assertRedirect();

    // Confirm neither removed key was persisted as a data entry.
    expect(
        LoanRequestDataEntry::query()
            ->whereIn('field_key', ['authorized_recipient_contact', 'authorization_reason'])
            ->exists()
    )->toBeFalse();
});

test('loan request correction request no longer accepts authorized_recipient_contact or authorization_reason', function () {
    $admin = User::factory()->create();
    \App\Models\AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    \App\Models\Role::ensureWorkflowDefaults();
    \App\Models\Role::attachNamedRole($admin, \App\Models\Role::LOAN_MANAGER);

    $member = User::factory()->create(['acctno' => '000803']);
    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => \App\LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    // Submitting correction with the removed fields should NOT persist them.
    $this->actingAs($admin)->patchJson("/spa/admin/requests/{$loanRequest->id}/corrections", [
        'authorization' => [
            'authorized_recipient_name' => 'Valid Recipient',
            'authorized_recipient_contact' => '09999999999',
            'authorization_reason' => 'Some reason',
            'release_method' => 'ATM',
        ],
        'change_reason' => 'Correcting auth fields only',
    ]);

    expect(
        LoanRequestDataEntry::query()
            ->where('loan_request_id', $loanRequest->id)
            ->whereIn('field_key', ['authorized_recipient_contact', 'authorization_reason'])
            ->exists()
    )->toBeFalse();
});
