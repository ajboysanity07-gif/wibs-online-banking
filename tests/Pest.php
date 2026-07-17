<?php

use App\LoanRequestPersonRole;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function testPngSignatureDataUrl(string $variant = 'one'): string
{
    $base64 = match ($variant) {
        'two' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=',
        default => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=',
    };

    return 'data:image/png;base64,'.$base64;
}

function testPngSignatureBinary(string $variant = 'one'): string
{
    $encoded = str_replace(
        'data:image/png;base64,',
        '',
        testPngSignatureDataUrl($variant),
    );
    $decoded = base64_decode($encoded, true);

    if ($decoded === false) {
        throw new RuntimeException('Unable to decode test signature data.');
    }

    return $decoded;
}

function prepareLoanRequestForApproval(
    LoanRequest $loanRequest,
    AppUser $admin,
): void {
    $loanRequest->loadMissing('people');

    foreach ([
        LoanRequestPersonRole::Applicant,
        LoanRequestPersonRole::CoMakerOne,
        LoanRequestPersonRole::CoMakerTwo,
    ] as $role) {
        $person = $loanRequest->people
            ->first(fn (LoanRequestPerson $item): bool => $item->role === $role);

        if ($person === null) {
            LoanRequestPerson::factory()
                ->forLoanRequest($loanRequest)
                ->role($role)
                ->create();
        }
    }
}
