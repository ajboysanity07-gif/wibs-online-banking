<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('processor dashboard scopes the queue to the processors own assignments', function (): void {
    $processor = createDashboardStaffUser([Role::LOAN_PROCESSOR]);
    $otherProcessor = createDashboardStaffUser([Role::LOAN_PROCESSOR]);
    $member = createDashboardStaffUser([Role::MEMBER], acctno: '900020');

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);
    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $otherProcessor->user_id,
    ]);

    $this
        ->actingAs($processor)
        ->get(route('staff.processor-dashboard.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/processor-dashboard')
            ->where('dashboardRole', 'loan_processor')
            ->where('queueData.queue_count', 1)
        );
});

test('manager dashboard scopes the queue to manager-actionable statuses, not any single assignee', function (): void {
    $manager = createDashboardStaffUser([Role::LOAN_MANAGER]);
    $processor = createDashboardStaffUser([Role::LOAN_PROCESSOR]);
    $member = createDashboardStaffUser([Role::MEMBER], acctno: '900021');

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $processor->user_id,
    ]);
    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $processor->user_id,
    ]);

    $this
        ->actingAs($manager)
        ->get(route('staff.processor-dashboard.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/processor-dashboard')
            ->where('dashboardRole', 'loan_manager')
            ->where('queueData.queue_count', 1)
        );
});

function createDashboardStaffUser(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    $twoFactorRoles = [Role::SUPERADMIN, Role::LOAN_MANAGER];
    if (! empty(array_intersect($roles, $twoFactorRoles))) {
        $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();
    }

    return $user->load('roles.permissions');
}
