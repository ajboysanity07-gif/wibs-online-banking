<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\Role;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('loan manager visible scope excludes recommended requests witnessed by another manager', function (): void {
    $manager = createScopeStaffUser([Role::LOAN_MANAGER]);
    $otherManager = createScopeStaffUser([Role::LOAN_MANAGER]);
    $member = createScopeStaffUser([Role::MEMBER], acctno: '920001');

    $ownRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);
    LoanRequestDataEntry::create([
        'loan_request_id' => $ownRequest->id,
        'section_key' => 'processing',
        'field_key' => 'witness_two_id',
        'owner_type' => 'staff',
        'value_json' => ['value' => $manager->user_id],
    ]);

    $otherRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);
    LoanRequestDataEntry::create([
        'loan_request_id' => $otherRequest->id,
        'section_key' => 'processing',
        'field_key' => 'witness_two_id',
        'owner_type' => 'staff',
        'value_json' => ['value' => $otherManager->user_id],
    ]);

    $service = app(LoanWorkflowWorkspaceService::class);
    $query = LoanRequest::query();
    $service->applyVisibleScope($query, $manager);

    $visibleIds = $query->pluck('id')->all();

    expect($visibleIds)->toContain($ownRequest->id);
    expect($visibleIds)->not->toContain($otherRequest->id);
});

test('loan manager visible scope includes recommended requests with no witness assigned', function (): void {
    $manager = createScopeStaffUser([Role::LOAN_MANAGER]);
    $member = createScopeStaffUser([Role::MEMBER], acctno: '920002');

    $unwitnessedRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);

    $service = app(LoanWorkflowWorkspaceService::class);
    $query = LoanRequest::query();
    $service->applyVisibleScope($query, $manager);

    expect($query->pluck('id')->all())->toContain($unwitnessedRequest->id);
});

function createScopeStaffUser(array $roles, ?string $acctno = null): AppUser
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
