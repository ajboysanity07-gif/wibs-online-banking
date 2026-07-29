<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function createRequestsQueueSortActor(): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => '900101',
    ]);

    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
    ]);

    $user->roles()->sync(
        Role::query()->where('name', Role::LOAN_MANAGER)->pluck('id')->all(),
    );

    return $user;
}

test('admin requests queue sorts by amount ascending', function () {
    $actor = createRequestsQueueSortActor();

    $small = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'requested_amount' => 5000,
    ]);
    $large = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'requested_amount' => 90000,
    ]);

    $this
        ->actingAs($actor)
        ->get('/spa/admin/requests?sortBy=amount&sortDirection=asc&perPage=10&page=1')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $small->id)
        ->assertJsonPath('data.items.1.id', $large->id)
        ->assertJsonPath('data.meta.sortBy', 'amount')
        ->assertJsonPath('data.meta.sortDirection', 'asc');
});

test('admin requests queue sorts by amount descending', function () {
    $actor = createRequestsQueueSortActor();

    $small = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'requested_amount' => 5000,
    ]);
    $large = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'requested_amount' => 90000,
    ]);

    $this
        ->actingAs($actor)
        ->get('/spa/admin/requests?sortBy=amount&sortDirection=desc&perPage=10&page=1')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $large->id)
        ->assertJsonPath('data.items.1.id', $small->id);
});

test('admin requests queue rejects an invalid sortBy value', function () {
    $actor = createRequestsQueueSortActor();

    $this
        ->actingAs($actor)
        ->get('/spa/admin/requests?sortBy=not_a_real_column')
        ->assertInvalid(['sortBy']);
});
