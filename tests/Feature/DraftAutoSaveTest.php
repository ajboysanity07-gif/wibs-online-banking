<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
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
});

/**
 * Create a fully-authorized member AppUser.
 */
function createDraftMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Draft', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Draft St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('save draft returns 204 for the request owner', function (): void {
    $member = createDraftMember('002001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'loan_purpose' => 'Education',
        ])
        ->assertNoContent();
});

test('save draft returns 403 when the request belongs to a different user', function (): void {
    $owner = createDraftMember('002002');
    $other = createDraftMember('002003');

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $owner->acctno,
    ]);

    $this->actingAs($other)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [])
        ->assertForbidden();
});

test('save draft returns 403 when the request is not in draft status', function (): void {
    $member = createDraftMember('002004');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [])
        ->assertForbidden();
});

test('save draft does not create a LoanRequestChange entry', function (): void {
    $member = createDraftMember('002005');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $changesBefore = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->count();

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'loan_purpose' => 'Home renovation',
        ])
        ->assertNoContent();

    $changesAfter = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->count();

    expect($changesAfter)->toBe($changesBefore);
});

test('save draft accepts partial payload without validation errors', function (): void {
    $member = createDraftMember('002006');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'requested_amount' => '25000',
        ])
        ->assertNoContent();
});

test('manual save-draft button returns 204 not a redirect', function (): void {
    // Confirms the endpoint the manual Save Draft button now calls
    // (save-draft, not the old draft() redirect route).
    $member = createDraftMember('002007');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'loan_purpose' => 'Business capital',
            'requested_amount' => '50000',
        ])
        ->assertNoContent()
        ->assertStatus(204);
});

test('manual save-draft creates draft row on brand new form with no prior draft', function (): void {
    $member = createDraftMember('002008');

    expect(LoanRequest::query()->where('user_id', $member->user_id)->count())->toBe(0);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.draft'), [
            'loan_purpose' => 'Education',
            'requested_amount' => '30000',
        ])
        ->assertOk()
        ->assertJsonStructure(['id', 'reference', 'status', 'updated_at']);

    expect(
        LoanRequest::query()
            ->where('user_id', $member->user_id)
            ->where('status', LoanRequestStatus::Draft->value)
            ->count()
    )->toBe(1);
});

test('save draft with wizard_step persists wizard_current_step data entry', function (): void {
    $member = createDraftMember('002010');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'wizard_step' => 5,
        ])
        ->assertNoContent();

    $entry = $loanRequest->dataEntries()->where('field_key', 'wizard_current_step')->first();
    expect($entry)->not->toBeNull();
    expect($entry->value_json['value'])->toBe(5);
});

test('loan-request create page includes initialStep from saved wizard_current_step', function (): void {
    $member = createDraftMember('002011');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $loanRequest->dataEntries()->create([
        'field_key' => 'wizard_current_step',
        'section_key' => 'system',
        'owner_type' => 'system',
        'is_sensitive' => false,
        'confirmed_by_member' => false,
        'confirmed_by_member_at' => null,
        'value_json' => ['value' => 5],
        'metadata_json' => ['label' => 'Wizard current step', 'type' => 'integer'],
    ]);

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('client/loan-request')
            ->where('initialStep', 5),
        );
});

test('loan-request create page returns initialStep 0 for legacy draft without wizard entry', function (): void {
    $member = createDraftMember('002012');

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('client/loan-request')
            ->where('initialStep', 0),
        );
});

test('save draft with wizard_step 19 persists 19', function (): void {
    $member = createDraftMember('002013');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'wizard_step' => 19,
        ])
        ->assertNoContent();

    $entry = $loanRequest->dataEntries()->where('field_key', 'wizard_current_step')->first();
    expect($entry)->not->toBeNull();
    expect($entry->value_json['value'])->toBe(19);
});

test('save draft accepts wizard_step 24 for the review step', function (): void {
    $member = createDraftMember('002014');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'wizard_step' => 24,
        ])
        ->assertNoContent();

    $entry = $loanRequest->dataEntries()->where('field_key', 'wizard_current_step')->first();
    expect($entry)->not->toBeNull();
    expect($entry->value_json['value'])->toBe(24);
});

test('save draft rejects wizard_step above 24 with 422', function (): void {
    $member = createDraftMember('002016');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'wizard_step' => 25,
        ])
        ->assertUnprocessable();
});

test('create page resumes initialStep 19', function (): void {
    $member = createDraftMember('002015');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $loanRequest->dataEntries()->create([
        'field_key' => 'wizard_current_step',
        'section_key' => 'system',
        'owner_type' => 'system',
        'is_sensitive' => false,
        'confirmed_by_member' => false,
        'confirmed_by_member_at' => null,
        'value_json' => ['value' => 19],
        'metadata_json' => ['label' => 'Wizard current step', 'type' => 'integer'],
    ]);

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('client/loan-request')
            ->where('initialStep', 19),
        );
});

test('draft endpoint accepts full form.data shape with empty strings and returns 200', function (): void {
    // Regression: empty strings for numeric/in fields caused a 422 when the
    // user clicked Save Draft on a freshly opened form (no prior draft).
    $member = createDraftMember('002009');

    $payload = [
        'typecode' => '',
        'requested_amount' => '',
        'requested_term' => '',
        'loan_purpose' => '',
        'availment_status' => '',
        'undertaking_accepted' => false,
        'applicant' => [
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'nickname' => '',
            'birthdate' => '',
            'birthplace_city' => '',
            'birthplace_province' => '',
            'address1' => '',
            'address2' => '',
            'address3' => '',
            'length_of_stay' => '',
            'housing_status' => '',
            'civil_status' => '',
            'cell_no' => '',
            'educational_attainment' => '',
            'number_of_children' => '',
            'spouse_name' => '',
            'spouse_age' => '',
            'spouse_cell_no' => '',
            'employment_type' => '',
            'employer_business_name' => '',
            'employer_business_address1' => '',
            'employer_business_address2' => '',
            'employer_business_address3' => '',
            'telephone_no' => '',
            'current_position' => '',
            'nature_of_business' => '',
            'years_in_work_business' => '',
            'gross_monthly_income' => '',
            'payday' => '',
        ],
        'co_maker_1' => [
            'first_name' => '', 'last_name' => '', 'middle_name' => '',
            'cell_no' => '', 'gross_monthly_income' => '', 'payday' => '',
        ],
        'co_maker_2' => [
            'first_name' => '', 'last_name' => '', 'middle_name' => '',
            'cell_no' => '', 'gross_monthly_income' => '', 'payday' => '',
        ],
        'insurance' => [
            'beneficiary_primary_name' => null,
            'beneficiary_primary_relationship' => null,
            'beneficiary_primary_birthdate' => null,
            'beneficiary_secondary_name' => null,
            'beneficiary_secondary_relationship' => null,
            'beneficiary_secondary_birthdate' => null,
        ],
        'health' => [
            'health_smoker' => null,
            'health_hypertension' => null,
            'health_diabetes' => null,
            'health_recent_hospitalization' => null,
            'health_declaration_notes' => null,
        ],
        'banking' => [
            'payout_bank_name' => null,
            'payout_account_name' => null,
            'payout_account_number' => null,
            'payout_account_type' => null,
            'release_method' => null,
            'payout_atm_number' => null,
            'payout_bank_branch' => null,
            'payout_atm_holder_name' => null,
        ],
        'barangay' => [
            'barangay_official_designation' => null,
            'barangay_agency_name' => null,
            'barangay_agency_address' => null,
        ],
        'declarations' => [
            'declaration_existing_loans' => null,
            'declaration_pending_cases' => null,
            'declaration_truth_confirmation' => null,
            'declaration_data_privacy_consent' => null,
        ],
    ];

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.draft'), $payload)
        ->assertOk()
        ->assertJsonStructure(['id', 'reference', 'status', 'updated_at']);
});
