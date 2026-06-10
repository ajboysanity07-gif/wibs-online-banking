<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('loan officers can access the staff workflow list page', function (): void {
    $loanOfficer = createLoanWorkflowStaffUser([Role::LOAN_OFFICER]);

    $this
        ->actingAs($loanOfficer)
        ->get(route('staff.loan-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/loan-requests')
            ->where('auth.canAccessLoanWorkflow', true)
            ->where('auth.loanWorkflowRoles', fn ($roles): bool => collect($roles)->contains(Role::LOAN_OFFICER))
        );
});

test('loan managers can access the staff workflow detail page', function (): void {
    $loanManager = createLoanWorkflowStaffUser([Role::LOAN_MANAGER]);
    $member = createLoanWorkflowStaffUser([
        Role::MEMBER,
    ], acctno: '200001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);

    $this
        ->actingAs($loanManager)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/loan-request-show')
            ->where('loanRequest.id', $loanRequest->id)
            ->has('workflowPermissions')
            ->where('workflowContext.isOwnRequest', false)
        );
});

test('members cannot access staff workflow pages', function (): void {
    $member = createLoanWorkflowStaffUser([
        Role::MEMBER,
    ], acctno: '200002');
    $owner = createLoanWorkflowStaffUser([
        Role::MEMBER,
    ], acctno: '200003');

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    $this
        ->actingAs($member)
        ->get(route('staff.loan-requests.index'))
        ->assertForbidden();

    $this
        ->actingAs($member)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertForbidden();
});

test('unauthenticated users cannot access staff workflow pages', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);

    $this->get(route('staff.loan-requests.index'))
        ->assertRedirect(route('login'));

    $this->get(route('staff.loan-requests.show', $loanRequest))
        ->assertRedirect(route('login'));
});

test('admins still have access to the existing admin request pages', function (): void {
    $admin = createLoanWorkflowStaffUser(
        [Role::ADMIN],
        withAdminProfile: true,
    );

    $this
        ->actingAs($admin)
        ->get(route('admin.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/requests'));
});

function createLoanWorkflowStaffUser(
    array $roles,
    bool $withAdminProfile = false,
    ?string $acctno = null,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    if ($withAdminProfile) {
        AdminProfile::factory()->admin()->create([
            'user_id' => $user->user_id,
        ]);
    }

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    return $user->load('roles.permissions');
}
