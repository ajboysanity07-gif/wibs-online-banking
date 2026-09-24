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

function createDashboardDraftMember(string $acctno): AppUser
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

test('dashboard exposes activeDraft for a strict draft record', function (): void {
    $member = createDashboardDraftMember('005001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('client/dashboard')
            ->where('activeDraft.id', $loanRequest->id),
        );
});

test('dashboard activeDraft is null when there is no draft', function (): void {
    $member = createDashboardDraftMember('005002');

    $this->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('client/dashboard')
            ->where('activeDraft', null),
        );
});

test('dashboard activeDraft is null for a submitted/pending-review request', function (): void {
    $member = createDashboardDraftMember('005003');

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('client/dashboard')
            ->where('activeDraft', null),
        );
});
