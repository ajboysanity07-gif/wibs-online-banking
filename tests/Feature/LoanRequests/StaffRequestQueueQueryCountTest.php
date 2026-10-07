<?php

use App\LoanRequestStatus;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function staffQueueQueryCount(User $actor): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($actor)->getJson('/spa/staff/loan-requests')->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('staff queue query count does not grow with the number of rows', function (): void {
    $processor = User::factory()->create(['acctno' => '500171']);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $createRequests = function (int $count) use ($processor): void {
        foreach (range(1, $count) as $i) {
            LoanRequest::factory()
                ->forUser(User::factory()->create())
                ->create([
                    'status' => LoanRequestStatus::PendingReview,
                    'assigned_officer_id' => $processor->user_id,
                ]);
        }
    };

    $createRequests(2);
    staffQueueQueryCount($processor); // warm schema/option caches
    $few = staffQueueQueryCount($processor);

    $createRequests(6);
    $many = staffQueueQueryCount($processor);

    // Eager loads are batched, so extra rows must not add per-row queries.
    expect($many)->toBeLessThanOrEqual($few + 2);
});
