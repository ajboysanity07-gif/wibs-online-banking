<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Notifications\LoanRequestAdminCorrectedCreatedNotification;
use App\Notifications\LoanRequestCancelledNotification;
use App\Notifications\LoanRequestCorrectedNotification;
use Illuminate\Support\Facades\Notification;

test('member cannot create corrected request from cancelled request', function () {
    Notification::fake();

    $member = User::factory()->create([
        'acctno' => '000860',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    $reviewer = User::factory()->create([
        'acctno' => '000861',
    ]);
    $canceller = User::factory()->create([
        'acctno' => '000862',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $canceller->user_id,
    ]);

    $submittedAt = now()->subDays(2)->startOfSecond();
    $reviewedAt = now()->subDay()->startOfSecond();
    $cancelledAt = now()->subHour()->startOfSecond();

    $source = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-CAN',
        'loan_type_label_snapshot' => 'Cancelled Loan',
        'requested_amount' => 12345,
        'requested_term' => 12,
        'loan_purpose' => 'Cancelled purpose',
        'availment_status' => 'Re-Loan',
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => $submittedAt,
        'reviewed_by' => $reviewer->user_id,
        'reviewed_at' => $reviewedAt,
        'approved_amount' => 25000,
        'approved_term' => 18,
        'decision_notes' => 'Approved before cancellation.',
        'cancelled_by' => $canceller->user_id,
        'cancelled_at' => $cancelledAt,
        'cancellation_reason' => 'Wrong co-maker details.',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Cancelled',
            'last_name' => 'Applicant',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Cancelled',
            'last_name' => 'CoMakerOne',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'Cancelled',
            'last_name' => 'CoMakerTwo',
        ]);

    $this
        ->actingAs($member)
        ->post(route('client.loan-requests.corrected-copy', $source->id))
        ->assertForbidden();

    Notification::assertNothingSent();
    expect(
        LoanRequest::query()->where('corrected_from_id', $source->id)->count(),
    )->toBe(0);
});

test('admin can create corrected request from cancelled request', function () {
    $admin = User::factory()->create([
        'acctno' => '000863',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000864',
    ]);

    $reviewer = User::factory()->create([
        'acctno' => '000865',
    ]);
    $canceller = User::factory()->create([
        'acctno' => '000866',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $canceller->user_id,
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-CAN',
        'loan_type_label_snapshot' => 'Cancelled Loan',
        'requested_amount' => 12345,
        'requested_term' => 12,
        'loan_purpose' => 'Cancelled purpose',
        'availment_status' => 'Re-Loan',
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDays(2)->startOfSecond(),
        'reviewed_by' => $reviewer->user_id,
        'reviewed_at' => now()->subDay()->startOfSecond(),
        'approved_amount' => 25000,
        'approved_term' => 18,
        'decision_notes' => 'Approved before cancellation.',
        'cancelled_by' => $canceller->user_id,
        'cancelled_at' => now()->subHour()->startOfSecond(),
        'cancellation_reason' => 'Wrong co-maker details.',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Cancelled',
            'last_name' => 'Applicant',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Cancelled',
            'last_name' => 'CoMakerOne',
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'Cancelled',
            'last_name' => 'CoMakerTwo',
        ]);

    $response = $this
        ->actingAs($admin)
        ->postJson("/spa/admin/requests/{$source->id}/admin-corrected-copy", [
            'correction_reason' => 'Fix incorrect applicant and co-maker details.',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'ok',
            'data' => [
                'loanRequest' => ['id', 'reference', 'url'],
            ],
        ]);

    $correctedId = (int) $response->json('data.loanRequest.id');
    $correctedReference = $response->json('data.loanRequest.reference');
    $correctedUrl = $response->json('data.loanRequest.url');

    $corrected = LoanRequest::query()->findOrFail($correctedId);

    expect($correctedReference)->toBe($corrected->reference);
    expect($correctedUrl)->toBe(route('admin.requests.show', $corrected));
    expect($corrected->status)->toBe(LoanRequestStatus::UnderReview);
    expect($corrected->corrected_from_id)->toBe($source->id);
    expect($corrected->typecode)->toBe('LN-CAN');
    expect($corrected->requested_amount)->toBe('12345.00');
    expect($corrected->requested_term)->toBe(12);
    expect($corrected->loan_purpose)->toBe('Cancelled purpose');
    expect($corrected->availment_status)->toBe('Re-Loan');
    expect($corrected->submitted_at)->not->toBeNull();
    expect($corrected->reviewed_by)->toBeNull();
    expect($corrected->reviewed_at)->toBeNull();
    expect($corrected->approved_amount)->toBeNull();
    expect($corrected->approved_term)->toBeNull();
    expect($corrected->decision_notes)->toBeNull();
    expect($corrected->cancelled_by)->toBeNull();
    expect($corrected->cancelled_at)->toBeNull();
    expect($corrected->cancellation_reason)->toBeNull();

    $people = LoanRequestPerson::query()
        ->where('loan_request_id', $corrected->id)
        ->get()
        ->keyBy('role');

    expect($people)->toHaveCount(3);
    expect($people[LoanRequestPersonRole::Applicant->value]->first_name)->toBe('Cancelled');
    expect($people[LoanRequestPersonRole::CoMakerTwo->value]->last_name)->toBe('CoMakerTwo');

    $source->refresh();
    expect($source->status)->toBe(LoanRequestStatus::Cancelled);
    expect($source->cancelled_by)->toBe($canceller->user_id);
    expect($source->cancellation_reason)->toBe('Wrong co-maker details.');

    $change = LoanRequestChange::query()
        ->where('loan_request_id', $corrected->id)
        ->latest('id')
        ->first();

    expect($change)->not->toBeNull();
    expect($change?->action)->toBe('admin_create_corrected_request');
    expect($change?->changed_by)->toBe($admin->user_id);
    expect($change?->reason)->toBe('Fix incorrect applicant and co-maker details.');
    expect($change?->before_json['loanRequest']['id'] ?? null)->toBe($source->id);
    expect($change?->after_json['loanRequest']['id'] ?? null)->toBe($corrected->id);
    expect($change?->changed_fields_json ?? [])->toContain(
        'corrected_from_id',
        'copied_loan_details',
        'copied_people_snapshots',
        'admin_correction_reason',
    );
});

test('admin corrected copy preserves the original submission date and clones health/beneficiary data entries', function () {
    $admin = User::factory()->create([
        'acctno' => '000872',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000873',
    ]);

    $originalSubmittedAt = now()->subMonths(3)->startOfSecond();

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => $originalSubmittedAt,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Wrong details.',
    ]);

    LoanRequestPerson::factory()->forLoanRequest($source)->role(LoanRequestPersonRole::Applicant)->create();
    LoanRequestPerson::factory()->forLoanRequest($source)->role(LoanRequestPersonRole::CoMakerOne)->create();
    LoanRequestPerson::factory()->forLoanRequest($source)->role(LoanRequestPersonRole::CoMakerTwo)->create();

    LoanRequestDataEntry::factory()->create([
        'loan_request_id' => $source->id,
        'section_key' => 'health',
        'field_key' => 'health_smoking_status',
        'owner_type' => 'member',
        'is_sensitive' => true,
        'confirmed_by_member' => true,
        'confirmed_by_member_at' => now()->subMonths(3),
        'value_json' => ['value' => 'none'],
    ]);

    $this
        ->actingAs($admin)
        ->postJson("/spa/admin/requests/{$source->id}/admin-corrected-copy", [
            'correction_reason' => 'Fix incorrect applicant details.',
        ])
        ->assertOk();

    $corrected = LoanRequest::query()->where('corrected_from_id', $source->id)->sole();

    expect($corrected->submitted_at->equalTo($originalSubmittedAt))->toBeTrue();

    $clonedEntry = LoanRequestDataEntry::query()
        ->where('loan_request_id', $corrected->id)
        ->where('field_key', 'health_smoking_status')
        ->sole();

    expect($clonedEntry->value_json['value'])->toBe('none')
        ->and($clonedEntry->confirmed_by_member)->toBeTrue();
});

test('admin cannot create corrected request from non-cancelled request', function (LoanRequestStatus $status) {
    $admin = User::factory()->create([
        'acctno' => '000867',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $source = LoanRequest::factory()->create([
        'status' => $status,
        'submitted_at' => $status === LoanRequestStatus::Draft ? null : now(),
    ]);

    $this
        ->actingAs($admin)
        ->postJson("/spa/admin/requests/{$source->id}/admin-corrected-copy", [
            'correction_reason' => 'Fix details.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect(
        LoanRequest::query()->where('corrected_from_id', $source->id)->count(),
    )->toBe(0);
})->with([
    'under review' => LoanRequestStatus::UnderReview,
    'approved' => LoanRequestStatus::Approved,
    'declined' => LoanRequestStatus::Declined,
    'submitted' => LoanRequestStatus::Submitted,
    'draft' => LoanRequestStatus::Draft,
]);

test('admin cannot create duplicate corrected request from same cancelled request', function () {
    $admin = User::factory()->create([
        'acctno' => '000868',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $source = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDay(),
        'cancelled_at' => now(),
        'cancellation_reason' => 'Wrong details.',
    ]);

    LoanRequest::factory()->create([
        'user_id' => $source->user_id,
        'acctno' => $source->acctno,
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'corrected_from_id' => $source->id,
    ]);

    $this
        ->actingAs($admin)
        ->postJson("/spa/admin/requests/{$source->id}/admin-corrected-copy", [
            'correction_reason' => 'Fix details.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('loan_request')
        ->assertJsonPath(
            'errors.loan_request.0',
            'A corrected request already exists for this cancelled request.',
        );
});

test('non-admin users cannot create admin corrected request copies', function () {
    $member = User::factory()->create([
        'acctno' => '000871',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDay(),
        'cancelled_at' => now(),
        'cancellation_reason' => 'Wrong details.',
    ]);

    $this
        ->actingAs($member)
        ->postJson("/spa/admin/requests/{$source->id}/admin-corrected-copy", [
            'correction_reason' => 'Fix details.',
        ])
        ->assertForbidden();
});

test('admin corrected-copy creation sends member notification', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'acctno' => '000869',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000870',
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
        'submitted_at' => now()->subDay(),
        'cancelled_at' => now(),
        'cancellation_reason' => 'Wrong details.',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::Applicant)
        ->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create();
    LoanRequestPerson::factory()
        ->forLoanRequest($source)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create();

    $this
        ->actingAs($admin)
        ->postJson("/spa/admin/requests/{$source->id}/admin-corrected-copy", [
            'correction_reason' => 'Fix details.',
        ])
        ->assertOk();

    Notification::assertSentTo(
        $member,
        LoanRequestAdminCorrectedCreatedNotification::class,
    );
});

test('admin correction sends member notification', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'acctno' => '000520',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);
    Role::attachNamedRole($admin, Role::LOAN_MANAGER);

    $member = User::factory()->create([
        'acctno' => '000521',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $payload = [
        'change_reason' => 'Corrected submitted request details.',
        'typecode' => 'LN-COR',
        'requested_amount' => 23000,
        'requested_term' => 18,
        'loan_purpose' => 'Corrected purpose',
        'availment_status' => 'Re-Loan',
        'applicant' => [
            'first_name' => 'Corrected',
            'last_name' => 'Applicant',
            'middle_name' => 'A',
            'nickname' => 'CA',
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Corrected Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
            'length_of_stay' => '6 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09123456789',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'number_of_children' => 2,
            'spouse_name' => 'Corrected Spouse',
            'spouse_age' => 35,
            'spouse_cell_no' => '09123456780',
            'employment_type' => 'Private',
            'employer_business_name' => 'Corrected Company',
            'employer_business_address1' => 'Corrected Center',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Supervisor',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '5 years',
            'employer_date_employed' => '2017-05-20',
            'gross_monthly_income' => 32000,
            'payday' => 'Quincenal',
        ],
        'co_maker_1' => [
            'first_name' => 'Corrected',
            'last_name' => 'CoMakerOne',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address1' => 'Corrected Co One Street',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'length_of_stay' => '4 years',
            'housing_status' => 'RENT',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Corrected Office One',
            'employer_business_address1' => 'Corrected Plaza',
            'employer_business_address2' => 'Cebu City',
            'employer_business_address3' => 'Cebu',
            'telephone_no' => '021234568',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => 'Quincenal',
        ],
        'co_maker_2' => [
            'first_name' => 'Corrected',
            'last_name' => 'CoMakerTwo',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address1' => 'Corrected Co Two Street',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'length_of_stay' => '3 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Corrected Store Two',
            'employer_business_address1' => 'Corrected Store',
            'employer_business_address2' => 'Davao City',
            'employer_business_address3' => 'Davao del Sur',
            'telephone_no' => '021234569',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => 'Quincenal',
        ],
        'dependents' => [
            'applicant_cycle_status' => 'New',
            'dependent_spouse_cycle_status' => 'New',
        ],
    ];

    $this
        ->actingAs($admin)
        ->patchJson(
            "/spa/admin/requests/{$loanRequest->id}/corrections",
            $payload,
        )
        ->assertOk();

    Notification::assertSentTo(
        $member,
        LoanRequestCorrectedNotification::class,
    );

    expect($loanRequest->refresh()->assigned_officer_id)->toBeNull();
});

test('loan processor correcting an unassigned request becomes its assigned officer', function () {
    $processor = User::factory()->create(['acctno' => '000522']);
    AdminProfile::factory()->create(['user_id' => $processor->user_id]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $member = User::factory()->create(['acctno' => '000523']);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-COR',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Original purpose',
        'availment_status' => 'New',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::Applicant)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerOne)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerTwo)->create();

    $this
        ->actingAs($processor)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/corrections", [
            'change_reason' => 'Fixed the requested amount.',
            'typecode' => 'LN-COR',
            'requested_amount' => 20000,
            'requested_term' => 12,
            'loan_purpose' => 'Original purpose',
            'availment_status' => 'New',
            'applicant' => [
                'first_name' => 'Corrected',
                'last_name' => 'Applicant',
                'middle_name' => 'A',
                'nickname' => 'CA',
                'birthdate' => '1990-04-10',
                'birthplace_city' => 'Manila',
                'birthplace_province' => 'Metro Manila',
                'address1' => 'Corrected Street',
                'address2' => 'Manila',
                'address3' => 'Metro Manila',
                'length_of_stay' => '6 years',
                'housing_status' => 'OWNED',
                'cell_no' => '09123456789',
                'civil_status' => 'Married',
                'educational_attainment' => 'College',
                'number_of_children' => 2,
                'spouse_name' => 'Corrected Spouse',
                'spouse_age' => 35,
                'spouse_cell_no' => '09123456780',
                'employment_type' => 'Private',
                'employer_business_name' => 'Corrected Company',
                'employer_business_address1' => 'Corrected Center',
                'employer_business_address2' => 'Manila',
                'employer_business_address3' => 'Metro Manila',
                'telephone_no' => '021234567',
                'current_position' => 'Supervisor',
                'nature_of_business' => 'Finance',
                'years_in_work_business' => '5 years',
                'employer_date_employed' => '2017-05-20',
                'gross_monthly_income' => 32000,
                'payday' => 'Quincenal',
            ],
            'co_maker_1' => [
                'first_name' => 'Corrected',
                'last_name' => 'CoMakerOne',
                'middle_name' => 'One',
                'nickname' => null,
                'birthdate' => '1989-03-12',
                'birthplace_city' => 'Cebu',
                'birthplace_province' => 'Cebu',
                'address1' => 'Corrected Co One Street',
                'address2' => 'Cebu City',
                'address3' => 'Cebu',
                'length_of_stay' => '4 years',
                'housing_status' => 'RENT',
                'cell_no' => '09998887777',
                'civil_status' => 'Married',
                'educational_attainment' => 'College',
                'employment_type' => 'Government',
                'employer_business_name' => 'Corrected Office One',
                'employer_business_address1' => 'Corrected Plaza',
                'employer_business_address2' => 'Cebu City',
                'employer_business_address3' => 'Cebu',
                'telephone_no' => '021234568',
                'current_position' => 'Clerk',
                'nature_of_business' => 'Government',
                'years_in_work_business' => '6 years',
                'gross_monthly_income' => 18000,
                'payday' => 'Quincenal',
            ],
            'co_maker_2' => [
                'first_name' => 'Corrected',
                'last_name' => 'CoMakerTwo',
                'middle_name' => 'Two',
                'nickname' => null,
                'birthdate' => '1987-02-12',
                'birthplace_city' => 'Davao',
                'birthplace_province' => 'Davao del Sur',
                'address1' => 'Corrected Co Two Street',
                'address2' => 'Davao City',
                'address3' => 'Davao del Sur',
                'length_of_stay' => '3 years',
                'housing_status' => 'OWNED',
                'cell_no' => '09111112222',
                'civil_status' => 'Single',
                'educational_attainment' => 'High School',
                'employment_type' => 'Self Employed',
                'employer_business_name' => 'Corrected Store Two',
                'employer_business_address1' => 'Corrected Store',
                'employer_business_address2' => 'Davao City',
                'employer_business_address3' => 'Davao del Sur',
                'telephone_no' => '021234569',
                'current_position' => 'Owner',
                'nature_of_business' => 'Retail',
                'years_in_work_business' => '8 years',
                'gross_monthly_income' => 22000,
                'payday' => 'Quincenal',
            ],
            'dependents' => [
                'applicant_cycle_status' => 'New',
                'dependent_spouse_cycle_status' => 'New',
            ],
        ])
        ->assertOk();

    expect($loanRequest->refresh()->assigned_officer_id)->toBe($processor->user_id);

    $this
        ->actingAs($processor)
        ->patchJson(
            route('spa.workflow.loan-requests.recommend-approval', $loanRequest),
            ['review_remarks' => 'Ready for manager approval.'],
        )
        ->assertOk();

    expect($loanRequest->refresh()->status)->toBe(LoanRequestStatus::RecommendedForApproval);
});

function correctedWorkflowFullPersonPayload(): array
{
    return [
        'applicant' => [
            'first_name' => 'Corrected',
            'last_name' => 'Applicant',
            'middle_name' => 'A',
            'nickname' => 'CA',
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Corrected Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
            'length_of_stay' => '6 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09123456789',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'number_of_children' => 2,
            'spouse_name' => 'Corrected Spouse',
            'spouse_age' => 35,
            'spouse_cell_no' => '09123456780',
            'employment_type' => 'Private',
            'employer_business_name' => 'Corrected Company',
            'employer_business_address1' => 'Corrected Center',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Supervisor',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '5 years',
            'employer_date_employed' => '2017-05-20',
            'gross_monthly_income' => 32000,
            'payday' => 'Quincenal',
        ],
        'co_maker_1' => [
            'first_name' => 'Corrected',
            'last_name' => 'CoMakerOne',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address1' => 'Corrected Co One Street',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'length_of_stay' => '4 years',
            'housing_status' => 'RENT',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Corrected Office One',
            'employer_business_address1' => 'Corrected Plaza',
            'employer_business_address2' => 'Cebu City',
            'employer_business_address3' => 'Cebu',
            'telephone_no' => '021234568',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => 'Quincenal',
        ],
        'co_maker_2' => [
            'first_name' => 'Corrected',
            'last_name' => 'CoMakerTwo',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address1' => 'Corrected Co Two Street',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'length_of_stay' => '3 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Corrected Store Two',
            'employer_business_address1' => 'Corrected Store',
            'employer_business_address2' => 'Davao City',
            'employer_business_address3' => 'Davao del Sur',
            'telephone_no' => '021234569',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => 'Quincenal',
        ],
        'dependents' => [
            'applicant_cycle_status' => 'New',
            'dependent_spouse_cycle_status' => 'New',
        ],
    ];
}

test('loan manager can correct a recommended for approval request without returning it for processing', function () {
    $manager = User::factory()->create(['acctno' => '000526']);
    AdminProfile::factory()->create(['user_id' => $manager->user_id]);
    Role::attachNamedRole($manager, Role::LOAN_MANAGER);

    $member = User::factory()->create(['acctno' => '000527']);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-COR',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Original purpose',
        'availment_status' => 'New',
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::Applicant)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerOne)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerTwo)->create();

    $this
        ->actingAs($manager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/corrections", [
            'change_reason' => 'Fixed a typo spotted during approval review.',
            'typecode' => 'LN-COR',
            'requested_amount' => 20000,
            'requested_term' => 12,
            'loan_purpose' => 'Original purpose',
            'availment_status' => 'New',
            ...correctedWorkflowFullPersonPayload(),
        ])
        ->assertOk();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::RecommendedForApproval);
    expect((float) $loanRequest->requested_amount)->toBe(20000.0);

    $change = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->latest('id')
        ->first();

    expect($change)->not->toBeNull();
    expect($change?->changed_by)->toBe($manager->user_id);
    expect($change?->reason)->toBe('Fixed a typo spotted during approval review.');
});

test('loan manager cannot correct their own recommended for approval request', function () {
    $manager = User::factory()->create(['acctno' => '000528']);
    AdminProfile::factory()->create(['user_id' => $manager->user_id]);
    Role::attachNamedRole($manager, Role::LOAN_MANAGER);

    $loanRequest = LoanRequest::factory()->forUser($manager)->create([
        'typecode' => 'LN-COR',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Original purpose',
        'availment_status' => 'New',
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::Applicant)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerOne)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerTwo)->create();

    $this
        ->actingAs($manager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/corrections", [
            'change_reason' => 'Attempted self-correction.',
            'typecode' => 'LN-COR',
            'requested_amount' => 20000,
            'requested_term' => 12,
            'loan_purpose' => 'Original purpose',
            'availment_status' => 'New',
            ...correctedWorkflowFullPersonPayload(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('correction');
});

test('loan manager cannot correct a recommended request assigned to a different manager', function () {
    $assignedManager = User::factory()->create(['acctno' => '000529']);
    AdminProfile::factory()->admin()->create(['user_id' => $assignedManager->user_id]);
    Role::attachNamedRole($assignedManager, Role::LOAN_MANAGER);

    $otherManager = User::factory()->create(['acctno' => '000530']);
    AdminProfile::factory()->admin()->create(['user_id' => $otherManager->user_id]);
    Role::attachNamedRole($otherManager, Role::LOAN_MANAGER);

    $member = User::factory()->create(['acctno' => '000531']);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'typecode' => 'LN-COR',
        'requested_amount' => 15000,
        'requested_term' => 12,
        'loan_purpose' => 'Original purpose',
        'availment_status' => 'New',
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::Applicant)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerOne)->create();
    LoanRequestPerson::factory()->forLoanRequest($loanRequest)->role(LoanRequestPersonRole::CoMakerTwo)->create();

    LoanRequestDataEntry::create([
        'loan_request_id' => $loanRequest->id,
        'section_key' => 'processing',
        'field_key' => 'witness_two_id',
        'owner_type' => 'staff',
        'value_json' => ['value' => $assignedManager->user_id],
    ]);

    $this
        ->actingAs($otherManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/corrections", [
            'change_reason' => 'Attempted correction on someone else\'s assigned request.',
            'typecode' => 'LN-COR',
            'requested_amount' => 20000,
            'requested_term' => 12,
            'loan_purpose' => 'Original purpose',
            'availment_status' => 'New',
            ...correctedWorkflowFullPersonPayload(),
        ])
        ->assertForbidden();

    $this
        ->actingAs($assignedManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/corrections", [
            'change_reason' => 'Correction by the designated manager.',
            'typecode' => 'LN-COR',
            'requested_amount' => 20000,
            'requested_term' => 12,
            'loan_purpose' => 'Original purpose',
            'availment_status' => 'New',
            ...correctedWorkflowFullPersonPayload(),
        ])
        ->assertOk();
});

test('staff without loan review permission cannot correct an under review request', function () {
    $staff = User::factory()->create(['acctno' => '000524']);
    AdminProfile::factory()->create(['user_id' => $staff->user_id]);

    $member = User::factory()->create(['acctno' => '000525']);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $this
        ->actingAs($staff)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/corrections", [
            'change_reason' => 'Attempted correction.',
            'typecode' => 'LN-COR',
            'requested_amount' => 20000,
            'requested_term' => 12,
            'loan_purpose' => 'Original purpose',
            'availment_status' => 'New',
        ])
        ->assertForbidden();
});

test('admin cancellation sends member notification', function () {
    Notification::fake();

    $reviewer = User::factory()->create([
        'acctno' => '000610',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $reviewer->user_id,
    ]);

    $admin = User::factory()->create([
        'acctno' => '000611',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000612',
    ]);
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_by' => $reviewer->user_id,
        'reviewed_at' => now()->subDay()->startOfSecond(),
        'approved_amount' => 25000,
        'approved_term' => 18,
        'decision_notes' => 'Approved before cancellation.',
    ]);

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/cancel", [
            'cancellation_reason' => 'Wrong co-maker details.',
        ])
        ->assertOk();

    Notification::assertSentTo(
        $member,
        LoanRequestCancelledNotification::class,
    );
});
