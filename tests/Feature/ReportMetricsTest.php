<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use App\Services\Reports\ReportMetricsService;
use Carbon\Carbon;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function makeProcessor(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::LOAN_PROCESSOR);

    return $user->fresh(['roles.permissions']);
}

function makeLoanManager(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::LOAN_MANAGER);

    return $user->fresh(['roles.permissions']);
}

function makeSuperadmin(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::SUPERADMIN);

    return $user->fresh(['roles.permissions']);
}

// ── dashboardMetrics ──────────────────────────────────────────────────────────

test('dashboardMetrics returns correct pending, approved, and rejected counts', function (): void {
    $manager = makeLoanManager();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(3)->create(['status' => LoanRequestStatus::PendingReview]);
    LoanRequest::factory()->count(2)->create([
        'status' => LoanRequestStatus::Approved,
        'approved_at' => now(),
        'approved_amount' => 10000,
    ]);
    LoanRequest::factory()->count(1)->create([
        'status' => LoanRequestStatus::Rejected,
        'rejected_at' => now(),
    ]);

    $metrics = $service->dashboardMetrics($manager, null, null);

    expect($metrics['pending_count'])->toBe(3)
        ->and($metrics['approved_count'])->toBe(2)
        ->and($metrics['rejected_count'])->toBe(1);
});

test('dashboardMetrics calculates approval_rate correctly', function (): void {
    $manager = makeLoanManager();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(3)->create([
        'status' => LoanRequestStatus::Approved,
        'approved_at' => now(),
        'approved_amount' => 5000,
    ]);
    LoanRequest::factory()->count(1)->create([
        'status' => LoanRequestStatus::Rejected,
        'rejected_at' => now(),
    ]);

    $metrics = $service->dashboardMetrics($manager, null, null);

    expect($metrics['approval_rate'])->toBe(75.0);
});

test('dashboardMetrics sums portfolio_total from approved amounts', function (): void {
    $manager = makeLoanManager();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_at' => now(),
        'approved_amount' => 20000,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_at' => now(),
        'approved_amount' => 30000,
    ]);

    $metrics = $service->dashboardMetrics($manager, null, null);

    expect($metrics['portfolio_total'])->toBe(50000.0);
});

test('dashboardMetrics computes average_processing_days', function (): void {
    $manager = makeLoanManager();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'created_at' => Carbon::now()->subDays(4),
        'approved_at' => Carbon::now(),
        'approved_amount' => 1000,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Rejected,
        'created_at' => Carbon::now()->subDays(2),
        'rejected_at' => Carbon::now(),
    ]);

    $metrics = $service->dashboardMetrics($manager, null, null);

    expect($metrics['average_processing_days'])->toBe(3.0);
});

test('dashboardMetrics status_breakdown counts each status via SQL aggregate', function (): void {
    $manager = makeLoanManager();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(3)->create(['status' => LoanRequestStatus::PendingReview]);
    LoanRequest::factory()->count(2)->create(['status' => LoanRequestStatus::UnderReview]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_at' => now(),
        'approved_amount' => 1000,
    ]);

    $metrics = $service->dashboardMetrics($manager, null, null);

    expect($metrics['status_breakdown'])->toMatchArray([
        LoanRequestStatus::PendingReview->value => 3,
        LoanRequestStatus::UnderReview->value => 2,
        LoanRequestStatus::Approved->value => 1,
    ]);
});

// ── staffPerformance ────────────────────────────────────────────────────────────

test('staffPerformance aggregates assigned, approved, and rejected counts per officer', function (): void {
    $processorA = makeProcessor();
    $processorB = makeProcessor();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(2)->create([
        'status' => LoanRequestStatus::Approved,
        'assigned_officer_id' => $processorA->user_id,
        'approved_at' => now(),
        'approved_amount' => 1000,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Rejected,
        'assigned_officer_id' => $processorA->user_id,
        'rejected_at' => now(),
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processorB->user_id,
    ]);

    $performance = collect($service->staffPerformance(null, null))
        ->keyBy('processor_id');

    expect($performance[$processorA->user_id]['assigned'])->toBe(3)
        ->and($performance[$processorA->user_id]['approved'])->toBe(2)
        ->and($performance[$processorA->user_id]['rejected'])->toBe(1)
        ->and($performance[$processorB->user_id]['assigned'])->toBe(1)
        ->and($performance[$processorB->user_id]['approved'])->toBe(0)
        ->and($performance[$processorB->user_id]['rejected'])->toBe(0);
});

test('staffPerformance computes average processing days from decided requests only', function (): void {
    $processor = makeProcessor();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'assigned_officer_id' => $processor->user_id,
        'created_at' => Carbon::now()->subDays(4),
        'approved_at' => Carbon::now(),
        'approved_amount' => 1000,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
        'created_at' => Carbon::now()->subDays(10),
    ]);

    $performance = collect($service->staffPerformance(null, null))
        ->keyBy('processor_id');

    expect($performance[$processor->user_id]['avg_days'])->toBe(4.0);
});

// ── Role scoping ──────────────────────────────────────────────────────────────

test('loan processor only sees own assigned requests', function (): void {
    $processor = makeProcessor();
    $otherProcessor = makeProcessor();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(2)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);
    LoanRequest::factory()->count(3)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $otherProcessor->user_id,
    ]);

    $metrics = $service->dashboardMetrics($processor, null, null);

    expect($metrics['pending_count'])->toBe(2);
});

test('loan manager sees all requests', function (): void {
    $processor = makeProcessor();
    $manager = makeLoanManager();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(2)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);
    LoanRequest::factory()->count(3)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    $metrics = $service->dashboardMetrics($manager, null, null);

    expect($metrics['pending_count'])->toBe(5);
});

test('superadmin sees all requests', function (): void {
    $processor = makeProcessor();
    $superadmin = makeSuperadmin();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(4)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $processor->user_id,
    ]);

    $metrics = $service->dashboardMetrics($superadmin, null, null);

    expect($metrics['pending_count'])->toBe(4);
});

// ── Date range filtering ───────────────────────────────────────────────────────

test('dashboardMetrics date range filters to correct window', function (): void {
    $manager = makeLoanManager();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'created_at' => Carbon::now()->subDays(60),
        'approved_at' => Carbon::now()->subDays(58),
        'approved_amount' => 5000,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'created_at' => Carbon::now()->subDays(5),
        'approved_at' => Carbon::now()->subDays(3),
        'approved_amount' => 5000,
    ]);

    $from = Carbon::now()->subDays(10)->startOfDay();
    $to = Carbon::now()->endOfDay();

    $metrics = $service->dashboardMetrics($manager, $from, $to);

    expect($metrics['approved_count'])->toBe(1);
});

// ── processorQueue ────────────────────────────────────────────────────────────

test('processorQueue returns only assigned active items for that processor', function (): void {
    $processor = makeProcessor();
    $other = makeProcessor();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->count(2)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $other->user_id,
    ]);

    $queue = $service->processorQueue($processor);

    expect($queue['queue_count'])->toBe(2);
});

test('processorQueue flags aging applications correctly', function (): void {
    config(['loan_workflow.report_aging_threshold_days' => 2]);

    $processor = makeProcessor();
    $service = app(ReportMetricsService::class);

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
        'created_at' => Carbon::now()->subWeekdays(3),
    ]);
    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
        'created_at' => Carbon::now()->subDay(),
    ]);

    $queue = $service->processorQueue($processor);

    expect($queue['aging_count'])->toBe(1);
    $agingItem = collect($queue['items'])->firstWhere('is_aging', true);
    expect($agingItem)->not->toBeNull();
});

test('processorQueue aging threshold respects config value', function (): void {
    config(['loan_workflow.report_aging_threshold_days' => 5]);

    $processor = makeProcessor();
    $service = app(ReportMetricsService::class);

    $queue = $service->processorQueue($processor);

    expect($queue['aging_threshold_days'])->toBe(5);
});
