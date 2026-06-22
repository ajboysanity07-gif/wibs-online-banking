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

function makeReportManager(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    AdminProfile::factory()->admin()->create(['user_id' => $user->user_id]);
    Role::attachNamedRole($user, Role::LOAN_MANAGER);

    return $user->fresh(['roles.permissions']);
}

function makeReportSuperadmin(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    AdminProfile::factory()->superadmin()->create(['user_id' => $user->user_id]);
    Role::attachNamedRole($user, Role::SUPERADMIN);

    return $user->fresh(['roles.permissions']);
}

function makeReportProcessor(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::LOAN_PROCESSOR);

    return $user->fresh(['roles.permissions']);
}

function makeReportMember(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($user, Role::MEMBER);

    return $user->fresh(['roles.permissions']);
}

// ── admin/dashboard ────────────────────────────────────────────────────────────

test('loan manager can view the reporting dashboard', function (): void {
    $manager = makeReportManager();

    $response = $this->actingAs($manager)->get(route('admin.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('summary')
            ->has('summary.metrics')
            ->has('summary.requests')
            ->has('reportingMetrics')
            ->has('reportingMetrics.pending_count')
            ->has('reportingMetrics.approved_count')
            ->has('reportingMetrics.approval_rate')
            ->has('reportingMetrics.portfolio_total')
            ->has('applicationVolume')
            ->has('staffPerformance')
        );
});

test('superadmin can view the reporting dashboard', function (): void {
    $superadmin = makeReportSuperadmin();

    $response = $this->actingAs($superadmin)->get(route('admin.dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/dashboard')
        ->has('reportingMetrics')
    );
});

// ── admin/reports ──────────────────────────────────────────────────────────────

test('loan manager can view the reports index page', function (): void {
    $manager = makeReportManager();

    $response = $this->actingAs($manager)->get(route('admin.reports.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports')
            ->has('reportingMetrics')
            ->has('canExport')
            ->where('canExport', true)
        );
});

test('superadmin can view reports and export', function (): void {
    $superadmin = makeReportSuperadmin();

    $response = $this->actingAs($superadmin)->get(route('admin.reports.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canExport', true)
    );
});

test('member cannot access admin reports — 403', function (): void {
    $member = makeReportMember();

    $response = $this->actingAs($member)->get(route('admin.reports.index'));

    $response->assertForbidden();
});

// ── date range filtering ────────────────────────────────────────────────────────

test('reports dashboard respects from/to query params', function (): void {
    $manager = makeReportManager();

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

    $from = Carbon::now()->subDays(10)->toDateString();
    $to = Carbon::now()->toDateString();

    $response = $this->actingAs($manager)->get(route('admin.dashboard', compact('from', 'to')));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('reportingMetrics.approved_count', 1)
    );
});

// ── export routes ───────────────────────────────────────────────────────────────

test('loan manager can download monthly-applications export', function (): void {
    $manager = makeReportManager();

    $response = $this->actingAs($manager)->get(route('admin.reports.export', ['type' => 'monthly-applications']));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('loan manager can download rejection-reasons export', function (): void {
    $manager = makeReportManager();

    $response = $this->actingAs($manager)->get(route('admin.reports.export', ['type' => 'rejection-reasons']));

    $response->assertOk();
});

test('loan manager can download turnaround-time export', function (): void {
    $manager = makeReportManager();

    $response = $this->actingAs($manager)->get(route('admin.reports.export', ['type' => 'turnaround-time']));

    $response->assertOk();
});

test('loan manager can download processor-workload export', function (): void {
    $manager = makeReportManager();

    $response = $this->actingAs($manager)->get(route('admin.reports.export', ['type' => 'processor-workload']));

    $response->assertOk();
});

test('processor cannot export reports — 403', function (): void {
    $processor = makeReportProcessor();

    $response = $this->actingAs($processor)->get(route('admin.reports.export', ['type' => 'monthly-applications']));

    $response->assertForbidden();
});

test('unknown export type returns 404', function (): void {
    $manager = makeReportManager();

    $response = $this->actingAs($manager)->get('/admin/reports/export/unknown-type');

    $response->assertNotFound();
});
