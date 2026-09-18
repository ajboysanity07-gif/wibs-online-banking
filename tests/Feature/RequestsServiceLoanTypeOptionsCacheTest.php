<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('loan type options for the staff workspace are cached per role and shared across users with the same role', function (): void {
    Cache::flush();

    $processorA = createLoanTypeCacheStaffUser([Role::LOAN_PROCESSOR]);
    $processorB = createLoanTypeCacheStaffUser([Role::LOAN_PROCESSOR]);
    $member = createLoanTypeCacheStaffUser([Role::MEMBER], acctno: '930001');

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'loan_type_label_snapshot' => 'Salary Loan',
    ]);

    $firstResponse = $this
        ->actingAs($processorA)
        ->getJson(route('spa.staff.loan-requests.index'))
        ->assertOk();

    expect($firstResponse->json('data.meta.loanTypes'))->toContain('Salary Loan');

    $cacheKey = 'requests-service.loan-type-options.'.md5(Role::LOAN_PROCESSOR);
    expect(Cache::has($cacheKey))->toBeTrue();

    // A newly created loan type for a second request should not appear yet
    // for a different processor sharing the same cached role bucket - this
    // is the deliberate staleness trade-off for cutting a DB round trip.
    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'loan_type_label_snapshot' => 'Emergency Loan',
    ]);

    $secondResponse = $this
        ->actingAs($processorB)
        ->getJson(route('spa.staff.loan-requests.index'))
        ->assertOk();

    expect($secondResponse->json('data.meta.loanTypes'))->not->toContain('Emergency Loan');
});

function createLoanTypeCacheStaffUser(array $roles, ?string $acctno = null): AppUser
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
