<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function createDashboardRequestMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('dashboard exposes the in-flight request with its member step', function (): void {
    $member = createDashboardRequestMember('006001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('client/dashboard')
            ->where('activeRequest.id', $loanRequest->id)
            ->where('activeRequest.status', 'under_review')
            ->where('activeRequest.step', 2)
            ->where('activeRequest.total_steps', 4),
        );
});

test('dashboard activeRequest is null for draft-only and finished requests', function (LoanRequestStatus $status): void {
    $member = createDashboardRequestMember('006002');

    LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertInertia(fn ($page) => $page->where('activeRequest', null));
})->with([
    LoanRequestStatus::Draft,
    LoanRequestStatus::Rejected,
    LoanRequestStatus::Released,
    LoanRequestStatus::Cancelled,
]);

test('dashboard picks the most recently submitted in-flight request', function (): void {
    $member = createDashboardRequestMember('006003');

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now()->subDays(5),
    ]);
    $latest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'acctno' => $member->acctno,
        'submitted_at' => now()->subDay(),
    ]);

    $this->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('activeRequest.id', $latest->id)
            ->where('activeRequest.step', 3)
            ->where('activeRequest.more_count', 1),
        );
});

test('dashboard more_count ignores finished requests', function (): void {
    $member = createDashboardRequestMember('006004');

    foreach ([LoanRequestStatus::UnderReview, LoanRequestStatus::Rejected, LoanRequestStatus::Released, LoanRequestStatus::Cancelled] as $status) {
        LoanRequest::factory()->forUser($member)->create([
            'status' => $status,
            'acctno' => $member->acctno,
            'submitted_at' => now(),
        ]);
    }

    $this->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertInertia(fn ($page) => $page->where('activeRequest.more_count', 0));
});

test('every request status has a defined member step', function (): void {
    foreach (LoanRequestStatus::cases() as $status) {
        expect($status->memberStep())->toBeIn([null, 1, 2, 3, 4]);
    }

    expect(LoanRequestStatus::Draft->memberStep())->toBeNull()
        ->and(LoanRequestStatus::Submitted->memberStep())->toBe(1)
        ->and(LoanRequestStatus::ReleaseScheduled->memberStep())->toBe(4);
});
