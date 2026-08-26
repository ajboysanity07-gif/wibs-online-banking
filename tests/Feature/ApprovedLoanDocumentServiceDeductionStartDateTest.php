<?php

use App\Models\LoanRequest;
use App\Services\LoanRequests\ApprovedLoanDocumentDataBuilder;

function resolveDeductionStartDateForTest(?string $wibsReleaseDate, ?string $paymentMode): ?string
{
    $service = app(ApprovedLoanDocumentDataBuilder::class);
    $loanRequest = new LoanRequest(['wibs_release_date' => $wibsReleaseDate]);

    $date = (new ReflectionMethod($service, 'resolveDeductionStartDate'))
        ->invoke($service, $loanRequest, $paymentMode);

    return $date?->toDateString();
}

it('returns null when the release date is not yet known', function () {
    expect(resolveDeductionStartDateForTest(null, 'QUINCENAL'))->toBeNull();
});

it('adds 7 days for weekly payment mode', function () {
    expect(resolveDeductionStartDateForTest('2026-07-10', 'WEEKLY'))->toBe('2026-07-17');
});

it('resolves to the 15th when released on or before the 15th, for quincenal mode', function () {
    expect(resolveDeductionStartDateForTest('2026-07-05', 'QUINCENAL'))->toBe('2026-07-15');
    expect(resolveDeductionStartDateForTest('2026-07-15', 'QUINCENAL'))->toBe('2026-07-15');
});

it('resolves to the end of month when released after the 15th, for quincenal mode', function () {
    expect(resolveDeductionStartDateForTest('2026-07-20', 'QUINCENAL'))->toBe('2026-07-31');
    expect(resolveDeductionStartDateForTest('2026-02-16', 'QUINCENAL'))->toBe('2026-02-28');
});

it('adds 1 month for monthly or unknown payment modes', function () {
    expect(resolveDeductionStartDateForTest('2026-07-10', 'MONTHLY'))->toBe('2026-08-10');
    expect(resolveDeductionStartDateForTest('2026-07-10', null))->toBe('2026-08-10');
});
