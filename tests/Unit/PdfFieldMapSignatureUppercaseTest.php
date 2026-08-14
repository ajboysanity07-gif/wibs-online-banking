<?php

use App\Services\LoanRequests\PdfFieldMaps\AffidavitUndertakingPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\AtmSalaryDeductionWaiverPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\DepedSalaryDeductionWaiverPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\LoanInformationPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\PensionDeductionWaiverPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\UndertakingBarangayPdfFieldMap;

function signatureLineFieldsFor(string $mapClass, string $value, float $y): array
{
    return collect((new $mapClass)->fields())
        ->filter(fn (array $f) => ($f['value'] ?? null) === $value && (float) ($f['y'] ?? null) === $y)
        ->all();
}

test('signature-line printed names render ALL CAPS regardless of stored casing', function (): void {
    $cases = [
        [UndertakingBarangayPdfFieldMap::class, 'applicant.full_name', 205.0],
        [AffidavitUndertakingPdfFieldMap::class, 'applicant.full_name', 250.75],
        [PensionDeductionWaiverPdfFieldMap::class, 'applicant.full_name', 171.0],
        [AtmSalaryDeductionWaiverPdfFieldMap::class, 'applicant.full_name', 171.0],
        [DepedSalaryDeductionWaiverPdfFieldMap::class, 'applicant.full_name', 206.0],
        [LoanInformationPdfFieldMap::class, 'reviewer.witness_one_name', 297.76],
        [LoanInformationPdfFieldMap::class, 'reviewer.name', 303.74],
    ];

    foreach ($cases as [$mapClass, $value, $y]) {
        $matches = signatureLineFieldsFor($mapClass, $value, $y);

        expect($matches)->toHaveCount(1, "expected exactly one {$value} field at y={$y} in {$mapClass}");

        $field = array_values($matches)[0];

        expect($field['transform'] ?? null)->toBeCallable();
        expect(($field['transform'])('Juan Dela Cruz'))->toBe('JUAN DELA CRUZ');
        expect(($field['transform'])('JUAN DELA CRUZ'))->toBe('JUAN DELA CRUZ');
    }
});

test('body-text occurrences of full_name outside the signature block are left un-transformed', function (): void {
    $bodyField = collect((new AffidavitUndertakingPdfFieldMap)->fields())
        ->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.full_name' && ($f['y'] ?? null) === 62.25);

    expect($bodyField)->not->toBeNull();
    expect($bodyField['transform'] ?? null)->toBeNull();
});
