<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Notifications\LoanRequestDecisionNotification;
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
