<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Notifications\LoanRequestDecisionNotification;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Notifications\DatabaseNotification;

test('approved loan request sends a database notification', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'phoneno' => null,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'approved_amount' => 15000,
        'approved_term' => 12,
        'decision_notes' => 'Approved for release.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", $payload);

    $response->assertOk();

    $notification = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $member->user_id)
        ->latest()
        ->first();

    expect($notification)->not->toBeNull();

    $data = $notification->data;

    expect($data['type'])->toBe('loan_request_decision');
    expect($data['loan_request_id'])->toBe($loanRequest->id);
    expect($data['reference'])->toBe($loanRequest->reference);
    expect($data['status'])->toBe(LoanRequestStatus::Approved->value);
    expect($data['title'])->toBe('Loan request approved');
    expect($data['message'])->toBe(
        sprintf('Your loan request %s was approved.', $loanRequest->reference),
    );
    expect($data['decision_notes'])->toBe('Approved for release.');
    expect($data['reviewed_at'])->not->toBeNull();
});

test('declined loan request sends a database notification', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $member = User::factory()->create([
        'phoneno' => null,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'decision_notes' => 'Declined due to incomplete documents.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/decline", $payload);

    $response->assertOk();

    $notification = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $member->user_id)
        ->latest()
        ->first();

    expect($notification)->not->toBeNull();

    $data = $notification->data;

    expect($data['type'])->toBe('loan_request_decision');
    expect($data['loan_request_id'])->toBe($loanRequest->id);
    expect($data['reference'])->toBe($loanRequest->reference);
    expect($data['status'])->toBe(LoanRequestStatus::Declined->value);
    expect($data['title'])->toBe('Loan request declined');
    expect($data['message'])->toBe(
        sprintf('Your loan request %s was declined.', $loanRequest->reference),
    );
    expect($data['decision_notes'])->toBe(
        'Declined due to incomplete documents.',
    );
    expect($data['reviewed_at'])->not->toBeNull();
});

test('loan request submission notifies admins', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->admin()->create([
        'user_id' => $admin->user_id,
    ]);

    $superadmin = User::factory()->create();
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $member = User::factory()->create([
        'acctno' => '000712',
    ]);

    $payload = [
        'typecode' => 'LN-100',
        'requested_amount' => 25000,
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'applicant' => [
            'first_name' => 'Loan',
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
            'cell_no' => '09170000001',
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
            'gross_monthly_income' => 20000,
            'payday' => '15th',
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
            'payday' => '30th',
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
            'payday' => '15th',
        ],
    ];

    $service = app(LoanRequestService::class);
    $loanRequest = $service->submit($member, $payload);

    $adminNotification = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $admin->user_id)
        ->latest()
        ->first();

    $superadminNotification = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $superadmin->user_id)
        ->latest()
        ->first();

    expect($adminNotification)->not->toBeNull();
    expect($superadminNotification)->not->toBeNull();

    $data = $adminNotification->data;

    expect($data['type'])->toBe('loan_request_submitted');
    expect($data['loan_request_id'])->toBe($loanRequest->id);
    expect($data['reference'])->toBe($loanRequest->reference);
    expect($data['member_id'])->toBe($member->user_id);
    expect($data['member_name'])->toBe($member->name);
    expect($data['member_acctno'])->toBe($member->acctno);
    expect($data['loan_type_code'])->toBe('LN-100');
    expect($data['loan_type_label'])->toBe('LN-100');
    expect($data['requested_amount'])->toBe('25000.00');
    expect($data['requested_term'])->toBe(12);
    expect($data['submitted_at'])->not->toBeNull();
});

test('unread count endpoint returns the correct value', function () {
    $user = User::factory()->create();

    $approvedLoan = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);
    $declinedLoan = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Declined,
        'reviewed_at' => now(),
    ]);

    $user->notify(new LoanRequestDecisionNotification($approvedLoan));
    $user->notify(new LoanRequestDecisionNotification($declinedLoan));

    $user->unreadNotifications()->first()?->markAsRead();

    $response = $this
        ->actingAs($user)
        ->getJson('/spa/notifications/unread-count');

    $response
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 1);
});

test('user cannot mark another user notification as read', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $owner->notify(new LoanRequestDecisionNotification($loanRequest));

    $notificationId = $owner->unreadNotifications()->first()?->id;

    expect($notificationId)->not->toBeNull();

    $response = $this
        ->actingAs($otherUser)
        ->patchJson("/spa/notifications/{$notificationId}/read");

    $response->assertForbidden();
});

test('mark all notifications as read updates unread count', function () {
    $user = User::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create([
        'status' => LoanRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $user->notify(new LoanRequestDecisionNotification($loanRequest));
    $user->notify(new LoanRequestDecisionNotification($loanRequest));

    $response = $this
        ->actingAs($user)
        ->patchJson('/spa/notifications/read-all');

    $response
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
    expect(
        $user->notifications()->whereNull('read_at')->count(),
    )->toBe(0);
});

test('admin-only loan request owners are not notified', function () {
    $admin = User::factory()->create([
        'acctno' => '000500',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    $adminOnly = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $adminOnly->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($adminOnly)->create([
        'acctno' => '000501',
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $payload = [
        'approved_amount' => 12000,
        'approved_term' => 12,
        'decision_notes' => 'Approved for release.',
    ];

    $response = $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", $payload);

    $response->assertOk();

    $notificationCount = DatabaseNotification::query()
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $adminOnly->user_id)
        ->count();

    expect($notificationCount)->toBe(0);
});
