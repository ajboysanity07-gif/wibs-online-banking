<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
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

test('admin requests queue reports the most recent audit trail entry as last_activity_at', function () {
    $actor = createRequestsQueueSortActor();

    $request = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->subDays(10),
    ]);

    LoanRequestChange::factory()->create([
        'loan_request_id' => $request->id,
        'created_at' => now()->subDays(5),
    ]);
    $latestChange = LoanRequestChange::factory()->create([
        'loan_request_id' => $request->id,
        'created_at' => now()->subDay(),
    ]);

    $this
        ->actingAs($actor)
        ->get('/spa/admin/requests?perPage=10&page=1')
        ->assertOk()
        ->assertJsonPath(
            'data.items.0.last_activity_at',
            $latestChange->created_at->toDateTimeString(),
        );
});

test('admin requests queue falls back to submitted_at when a request has no audit trail entries', function () {
    $actor = createRequestsQueueSortActor();

    $request = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->subDays(3),
    ]);

    $this
        ->actingAs($actor)
        ->get('/spa/admin/requests?perPage=10&page=1')
        ->assertOk()
        ->assertJsonPath(
            'data.items.0.last_activity_at',
            $request->submitted_at->toDateTimeString(),
        );
});

test('admin requests queue sorts by last activity date via the submitted column', function () {
    $actor = createRequestsQueueSortActor();

    $staleActivity = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->subDays(10),
    ]);
    LoanRequestChange::factory()->create([
        'loan_request_id' => $staleActivity->id,
        'created_at' => now()->subDays(8),
    ]);

    $freshActivity = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now()->subDays(9),
    ]);
    LoanRequestChange::factory()->create([
        'loan_request_id' => $freshActivity->id,
        'created_at' => now()->subDay(),
    ]);

    $this
        ->actingAs($actor)
        ->get('/spa/admin/requests?sortBy=submitted&sortDirection=desc&perPage=10&page=1')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $freshActivity->id)
        ->assertJsonPath('data.items.1.id', $staleActivity->id);
});

test('admin requests queue rejects an invalid sortBy value', function () {
    $actor = createRequestsQueueSortActor();

    $this
        ->actingAs($actor)
        ->get('/spa/admin/requests?sortBy=not_a_real_column')
        ->assertInvalid(['sortBy']);
});
