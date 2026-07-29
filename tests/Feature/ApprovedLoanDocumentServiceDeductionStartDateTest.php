<?php

use App\Models\LoanRequest;
use App\Services\LoanRequests\ApprovedLoanDocumentService;

function resolveDeductionStartDateForTest(?string $wibsReleaseDate, ?string $paymentMode): ?string
{
    $service = app(ApprovedLoanDocumentService::class);
    $loanRequest = new LoanRequest(['wibs_release_date' => $wibsReleaseDate]);

    $date = (new ReflectionMethod($service, 'resolveDeductionStartDate'))
        ->invoke($service, $loanRequest, $paymentMode);

    return $date?->toDateString();
}

it('returns null when the release date is not yet known', function () {
    expect(resolveDeductionStartDateForTest(null, 'SEMI-MONTHLY'))->toBeNull();
});

it('adds 7 days for weekly payment mode', function () {
    expect(resolveDeductionStartDateForTest('2026-07-10', 'WEEKLY'))->toBe('2026-07-17');
});

it('adds 14 days for bi-weekly payment mode', function () {
    expect(resolveDeductionStartDateForTest('2026-07-10', 'BI-WEEKLY'))->toBe('2026-07-24');
});

it('resolves to the 15th when released on or before the 15th, for semi-monthly mode', function () {
    expect(resolveDeductionStartDateForTest('2026-07-05', 'SEMI-MONTHLY'))->toBe('2026-07-15');
    expect(resolveDeductionStartDateForTest('2026-07-15', 'SEMI-MONTHLY'))->toBe('2026-07-15');
});

it('resolves to the end of month when released after the 15th, for semi-monthly mode', function () {
    expect(resolveDeductionStartDateForTest('2026-07-20', 'SEMI-MONTHLY'))->toBe('2026-07-31');
    expect(resolveDeductionStartDateForTest('2026-02-16', 'SEMI-MONTHLY'))->toBe('2026-02-28');
});

it('adds 1 month for monthly or unknown payment modes', function () {
    expect(resolveDeductionStartDateForTest('2026-07-10', 'MONTHLY'))->toBe('2026-08-10');
    expect(resolveDeductionStartDateForTest('2026-07-10', null))->toBe('2026-08-10');
});
