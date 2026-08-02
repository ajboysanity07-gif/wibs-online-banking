<?php

use App\Models\AppUser;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Services\LoanRequests\LoanManagerWitnessResolver;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * Guards the SQL Server regression where managers() ordered the query by the
 * virtual `name` accessor (AppUser::getNameAttribute), which does not exist as
 * a column on `appusers` and blew up with "Invalid column name 'name'". The
 * resolver must order by real columns instead.
 */
test('managers returns only active loan managers ordered by username', function (): void {
    createLoanManager('charlie');
    createLoanManager('alpha');
    createLoanManager('bravo');
    createLoanManager('delta', suspended: true);

    $processor = createLoanManager('echo');
    $processor->roles()->sync(
        Role::query()->where('name', Role::LOAN_PROCESSOR)->pluck('id')->all(),
    );

    $managers = app(LoanManagerWitnessResolver::class)->managers();

    expect($managers)
        ->toHaveCount(3)
        ->and(array_map(
            static fn (AppUser $manager): string => $manager->username,
            $managers,
        ))->toBe(['alpha', 'bravo', 'charlie']);
});

test('options returns the witness option shape for each eligible manager', function (): void {
    createLoanManager('witness');

    $options = app(LoanManagerWitnessResolver::class)->options();

    expect($options)->toHaveCount(1)
        ->and($options[0])->toMatchArray([
            'name' => 'witness',
            'active_loans' => 0,
        ])
        ->and($options[0]['id'])->toBeInt();
});

/**
 * @param  list<string>  $roles
 */
function createLoanManager(
    string $username,
    bool $suspended = false,
): AppUser {
    $user = AppUser::factory()->create([
        'username' => $username,
        'acctno' => null,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()
            ->where('name', Role::LOAN_MANAGER)
            ->pluck('id')
            ->all(),
    );

    if ($suspended) {
        StaffAccessControl::create([
            'user_id' => $user->user_id,
            'status' => StaffAccessControl::STATUS_SUSPENDED,
        ]);
    }

    return $user->fresh(['roles', 'staffAccessControl']);
}
