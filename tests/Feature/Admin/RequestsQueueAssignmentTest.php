<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function createRequestsQueueActor(string $roleName, string $acctno): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
    ]);

    $user->roles()->sync(
        Role::query()->where('name', $roleName)->pluck('id')->all(),
    );

    return $user;
}

test('loan manager sees assignable officers and can_assign true on the admin requests queue', function () {
    $loanManager = createRequestsQueueActor(Role::LOAN_MANAGER, '900001');
    $loanProcessor = createRequestsQueueActor(Role::LOAN_PROCESSOR, '900002');

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $this
        ->actingAs($loanManager)
        ->get('/spa/admin/requests?perPage=10&page=1')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $loanRequest->id)
        ->assertJsonPath('data.items.0.can_assign', true)
        ->assertJsonPath('data.meta.assignmentOfficers.0.user_id', $loanProcessor->user_id);
});

test('loan processor does not see assignment officers or can_assign on the admin requests queue', function () {
    createRequestsQueueActor(Role::LOAN_PROCESSOR, '900004');
    $actingProcessor = createRequestsQueueActor(Role::LOAN_PROCESSOR, '900003');

    LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => null,
    ]);

    $this
        ->actingAs($actingProcessor)
        ->get('/spa/admin/requests?perPage=10&page=1')
        ->assertOk()
        ->assertJsonPath('data.items.0.can_assign', false)
        ->assertJsonPath('data.meta.assignmentOfficers', []);
});
