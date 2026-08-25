<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('admin bulk cancels eligible pending requests', function (): void {
    $admin = User::factory()->create(['acctno' => '500101']);
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $member = User::factory()->create(['acctno' => '500102']);

    $requests = LoanRequest::factory()->forUser($member)->count(3)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson('/spa/admin/requests/bulk-cancel', [
            'loan_request_ids' => $requests->pluck('id')->all(),
            'cancellation_reason' => 'Bulk cancelled duplicate applications.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.succeeded_count', 3)
        ->assertJsonPath('data.failed_count', 0);

    foreach ($requests as $loanRequest) {
        expect($loanRequest->refresh()->status)->toBe(LoanRequestStatus::Cancelled);
    }

    expect(LoanRequestChange::query()->count())->toBe(3);
});

test('bulk cancel reports per-row failures without blocking the rest of the batch', function (): void {
    $manager = User::factory()->create(['acctno' => '500111']);
    AdminProfile::factory()->create(['user_id' => $manager->user_id]);
    Role::attachNamedRole($manager, Role::LOAN_MANAGER);

    $member = User::factory()->create(['acctno' => '500112']);

    $cancellable = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $manager->user_id,
    ]);
    $unclaimedByProcessor = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => null,
    ]);
    $alreadyCancelled = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Cancelled,
    ]);

    $response = $this
        ->actingAs($manager)
        ->patchJson('/spa/admin/requests/bulk-cancel', [
            'loan_request_ids' => [
                $cancellable->id,
                $unclaimedByProcessor->id,
                $alreadyCancelled->id,
            ],
            'cancellation_reason' => 'Bulk cancellation batch.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.succeeded', [$cancellable->id])
        ->assertJsonPath('data.succeeded_count', 1)
        ->assertJsonPath('data.failed_count', 2);

    expect($cancellable->refresh()->status)->toBe(LoanRequestStatus::Cancelled);
    expect($unclaimedByProcessor->refresh()->status)->toBe(LoanRequestStatus::PendingReview);
    expect(LoanRequestChange::query()->count())->toBe(1);
});

test('bulk cancel blocks cancelling the actor own request', function (): void {
    $admin = User::factory()->create(['acctno' => '500121']);
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $ownRequest = LoanRequest::factory()->forUser($admin)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    $response = $this
        ->actingAs($admin)
        ->patchJson('/spa/admin/requests/bulk-cancel', [
            'loan_request_ids' => [$ownRequest->id],
            'cancellation_reason' => 'Should be blocked.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.succeeded_count', 0)
        ->assertJsonPath('data.failed_count', 1);

    expect($ownRequest->refresh()->status)->toBe(LoanRequestStatus::PendingReview);
});

test('bulk cancel validation requires a reason and non-empty ids', function (): void {
    $admin = User::factory()->create(['acctno' => '500131']);
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    $this
        ->actingAs($admin)
        ->patchJson('/spa/admin/requests/bulk-cancel', [
            'loan_request_ids' => [$loanRequest->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cancellation_reason');

    $this
        ->actingAs($admin)
        ->patchJson('/spa/admin/requests/bulk-cancel', [
            'loan_request_ids' => [],
            'cancellation_reason' => 'Missing ids.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('loan_request_ids');

    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('non-admin users cannot bulk cancel loan requests', function (): void {
    $member = User::factory()->create(['acctno' => '500141']);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    $this
        ->actingAs($member)
        ->patchJson('/spa/admin/requests/bulk-cancel', [
            'loan_request_ids' => [$loanRequest->id],
            'cancellation_reason' => 'Trying without admin access.',
        ])
        ->assertForbidden();

    expect($loanRequest->refresh()->status)->toBe(LoanRequestStatus::PendingReview);
});
