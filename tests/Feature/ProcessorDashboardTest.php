<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function makeProcessorStaff(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::LOAN_PROCESSOR);

    return $user->fresh(['roles.permissions']);
}

function makeNonProcessorAdmin(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    AdminProfile::factory()->admin()->create(['user_id' => $user->user_id]);

    return $user->fresh(['roles.permissions']);
}

// ── access ─────────────────────────────────────────────────────────────────────

test('loan processor can view processor dashboard', function (): void {
    $processor = makeProcessorStaff();

    $response = $this->actingAs($processor)->get(route('staff.processor-dashboard.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/processor-dashboard')
            ->has('queueData')
            ->has('queueData.queue_count')
            ->has('queueData.aging_count')
            ->has('queueData.aging_threshold_days')
            ->has('queueData.items')
            ->has('thisMonth')
            ->has('thisMonth.approved')
            ->has('thisMonth.rejected')
        );
});

test('non-staff user cannot view processor dashboard — 403', function (): void {
    $admin = makeNonProcessorAdmin();

    $response = $this->actingAs($admin)->get('/staff/processor-dashboard');

    $response->assertForbidden();
});

// ── queue data ──────────────────────────────────────────────────────────────────

test('processor dashboard shows only own queue items', function (): void {
    $processor = makeProcessorStaff();
    $other = makeProcessorStaff();

    LoanRequest::factory()->count(2)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $other->user_id,
    ]);

    $response = $this->actingAs($processor)->get(route('staff.processor-dashboard.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('queueData.queue_count', 2)
    );
});

test('processor dashboard highlights aging applications', function (): void {
    config(['loan_workflow.report_aging_threshold_days' => 2]);

    $processor = makeProcessorStaff();

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
        'created_at' => Carbon::now()->subWeekdays(3),
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
        'created_at' => Carbon::now(),
    ]);

    $response = $this->actingAs($processor)->get(route('staff.processor-dashboard.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('queueData.aging_count', 1)
    );
});

// ── this month stats ──────────────────────────────────────────────────────────

test('processor dashboard shows this month approved count', function (): void {
    $processor = makeProcessorStaff();

    LoanRequest::factory()->count(2)->create([
        'status' => LoanRequestStatus::Approved,
        'approved_by' => $processor->user_id,
        'approved_at' => Carbon::now()->startOfMonth()->addDays(2),
        'approved_amount' => 5000,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_by' => $processor->user_id,
        'approved_at' => Carbon::now()->subMonths(2),
        'approved_amount' => 5000,
    ]);

    $response = $this->actingAs($processor)->get(route('staff.processor-dashboard.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('thisMonth.approved', 2)
    );
});

test('processor dashboard shows this month rejected count', function (): void {
    $processor = makeProcessorStaff();

    LoanRequest::factory()->count(3)->create([
        'status' => LoanRequestStatus::Rejected,
        'rejected_by' => $processor->user_id,
        'rejected_at' => Carbon::now()->startOfMonth()->addDays(1),
    ]);

    $response = $this->actingAs($processor)->get(route('staff.processor-dashboard.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('thisMonth.rejected', 3)
    );
});
