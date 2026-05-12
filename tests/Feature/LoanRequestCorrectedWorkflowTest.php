<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use App\Notifications\LoanRequestCancelledNotification;
use App\Notifications\LoanRequestCorrectedNotification;
use Illuminate\Support\Facades\Notification;

test('member can create a corrected draft from their own cancelled loan request', function () {
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
        ->assertRedirect(route('client.loan-requests.create'));

    Notification::assertNothingSent();

    $draft = LoanRequest::query()
        ->where('corrected_from_id', $source->id)
        ->sole();

    expect($draft->status)->toBe(LoanRequestStatus::Draft);
    expect($draft->corrected_from_id)->toBe($source->id);
    expect($draft->typecode)->toBe('LN-CAN');
    expect($draft->requested_amount)->toBe('12345.00');
    expect($draft->requested_term)->toBe(12);
    expect($draft->loan_purpose)->toBe('Cancelled purpose');
    expect($draft->availment_status)->toBe('Re-Loan');
    expect($draft->submitted_at)->toBeNull();
    expect($draft->reviewed_by)->toBeNull();
    expect($draft->reviewed_at)->toBeNull();
    expect($draft->approved_amount)->toBeNull();
    expect($draft->approved_term)->toBeNull();
    expect($draft->decision_notes)->toBeNull();
    expect($draft->cancelled_by)->toBeNull();
    expect($draft->cancelled_at)->toBeNull();
    expect($draft->cancellation_reason)->toBeNull();

    $people = LoanRequestPerson::query()
        ->where('loan_request_id', $draft->id)
        ->get()
        ->keyBy('role');

    expect($people)->toHaveCount(3);
    expect($people[LoanRequestPersonRole::Applicant->value]->first_name)->toBe('Cancelled');
    expect($people[LoanRequestPersonRole::CoMakerTwo->value]->last_name)->toBe('CoMakerTwo');

    $source->refresh();

    expect($source->status)->toBe(LoanRequestStatus::Cancelled);
    expect($source->cancelled_by)->toBe($canceller->user_id);
    expect($source->cancellation_reason)->toBe('Wrong co-maker details.');
});

test('member cannot create a corrected draft from another member cancelled request', function () {
    $member = User::factory()->create([
        'acctno' => '000863',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    $otherMember = User::factory()->create([
        'acctno' => '000864',
    ]);
    $source = LoanRequest::factory()->forUser($otherMember)->create([
        'status' => LoanRequestStatus::Cancelled,
    ]);

    $this
        ->actingAs($member)
        ->post(route('client.loan-requests.corrected-copy', $source->id))
        ->assertNotFound();
});

test('member cannot create a corrected draft from non-cancelled requests', function (LoanRequestStatus $status) {
    $member = User::factory()->create([
        'acctno' => '000865',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'submitted_at' => $status === LoanRequestStatus::Draft ? null : now(),
    ]);

    $this
        ->actingAs($member)
        ->post(route('client.loan-requests.corrected-copy', $source->id))
        ->assertSessionHasErrors(['status']);

    expect(LoanRequest::query()->where('corrected_from_id', $source->id)->count())->toBe(0);
})->with([
    'under review' => LoanRequestStatus::UnderReview,
    'approved' => LoanRequestStatus::Approved,
    'declined' => LoanRequestStatus::Declined,
    'submitted' => LoanRequestStatus::Submitted,
    'draft' => LoanRequestStatus::Draft,
]);

test('member cannot create a corrected draft when an active draft exists', function () {
    $member = User::factory()->create([
        'acctno' => '000866',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'submitted_at' => null,
    ]);

    $source = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
    ]);

    $this
        ->actingAs($member)
        ->post(route('client.loan-requests.corrected-copy', $source->id))
        ->assertSessionHasErrors(['draft']);

    expect(LoanRequest::query()->where('corrected_from_id', $source->id)->count())->toBe(0);
});

test('admin correction sends member notification', function () {
    Notification::fake();

    $admin = User::factory()->create([
        'acctno' => '000520',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000521',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
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
            'gross_monthly_income' => 32000,
            'payday' => '15th & 30th',
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
            'payday' => '30th',
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
            'payday' => '15th',
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
