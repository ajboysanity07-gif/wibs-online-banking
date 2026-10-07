<?php

uses(Tests\TestCase::class);

use App\Support\LoanPaymentSplit;

function splitRow(array $attributes): object
{
    return new class($attributes)
    {
        public function __construct(private array $attributes) {}

        public function __get(string $key): mixed
        {
            return $this->attributes[$key] ?? null;
        }
    };
}

test('real posted values win over the derived split', function () {
    $row = splitRow(['principal' => 2300, 'accruedint' => 850, 'payments' => 3150]);
    $entries = collect([(object) ['Date_pay' => '2026-09-10', 'Interest' => 700, 'lnnumber' => 'LN-1']]);

    expect(LoanPaymentSplit::resolve($row, $entries))->toBe([
        'principal' => 2300.0,
        'interest' => 850.0,
    ]);
});

test('zero posted values fall back to the covering schedule installment', function () {
    $row = splitRow(['principal' => 0, 'accruedint' => 0, 'payments' => 3150, 'date_in' => '2026-09-15 10:00:00']);
    $entries = collect([
        (object) ['Date_pay' => '2026-08-10', 'Interest' => 800, 'lnnumber' => 'LN-1'],
        (object) ['Date_pay' => '2026-09-10', 'Interest' => 762.50, 'lnnumber' => 'LN-1'],
        (object) ['Date_pay' => '2026-10-10', 'Interest' => 700, 'lnnumber' => 'LN-1'],
    ]);

    expect(LoanPaymentSplit::resolve($row, $entries))->toBe([
        'principal' => 2387.5,
        'interest' => 762.5,
    ]);
});

test('unknown halves stay null instead of zero', function () {
    $row = splitRow(['principal' => 0, 'accruedint' => null, 'payments' => 3150, 'date_in' => '2026-09-15']);

    expect(LoanPaymentSplit::resolve($row, collect()))->toBe([
        'principal' => null,
        'interest' => null,
    ]);
});

test('schedule interest picks the latest installment on or before the payment', function () {
    $entries = collect([
        (object) ['Date_pay' => '2026-09-10', 'Interest' => 762.50],
        (object) ['Date_pay' => '2026-09-20', 'Interest' => 700],
    ]);

    expect(LoanPaymentSplit::interestForDate($entries, '2026-09-15'))->toBe(762.5);
    expect(LoanPaymentSplit::interestForDate($entries, '2026-08-01'))->toBeNull();
});

test('rows group by trimmed loan number despite legacy padding', function () {
    $rows = [
        (object) ['lnnumber' => 'LN-900  ', 'Balance' => 9000],
        (object) ['lnnumber' => 'LN-900', 'Balance' => 6000],
        (object) ['lnnumber' => 'LN-901', 'Balance' => 1000],
        (object) ['lnnumber' => '   ', 'Balance' => 5],
    ];

    $grouped = LoanPaymentSplit::indexRowsByLoan($rows);

    expect(array_keys($grouped))->toBe(['LN-900', 'LN-901']);
    expect(count($grouped['LN-900']))->toBe(2);
});
