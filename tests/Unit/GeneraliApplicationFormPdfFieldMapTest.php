<?php

use App\Services\LoanRequests\PdfFieldMaps\GeneraliApplicationFormPdfFieldMap;

/**
 * @return list<array<string, mixed>>
 */
function generaliApplicationFormMapFields(): array
{
    return (new GeneraliApplicationFormPdfFieldMap)->fields();
}

test('generali application form map recalibrated all page-1 check anchors onto their size-9 boxes', function (): void {
    $fields = collect(generaliApplicationFormMapFields());

    $expect = [
        [1, 142.7, 78.6],   // applicant cycle status: New
        [1, 142.7, 83.1],   // applicant cycle status: Old
        [1, 158.4, 121.9],  // sex: MALE
        [1, 158.4, 126.2],  // sex: FEMALE
        [1, 124.7, 134.6],  // civil status: Single
        [1, 152.5, 134.6],  // civil status: Married
        [1, 124.7, 138.9],  // civil status: Separated
        [1, 152.2, 138.9],  // civil status: Widowed
        [1, 85.6, 143.2],   // PEP: Yes
        [1, 99.5, 143.2],   // PEP: No
        [1, 98.8, 258.5],   // spouse: New
        [1, 122.6, 258.5],  // spouse: Old
    ];

    foreach ($expect as [$page, $x, $y]) {
        $field = $fields->first(fn (array $f): bool => ($f['type'] ?? null) === 'check'
            && ($f['page'] ?? null) === $page
            && abs(($f['x'] ?? 0) - $x) < 0.05
            && abs(($f['y'] ?? 0) - $y) < 0.05);

        expect($field)->not->toBeNull("expected a check at page $page ($x, $y)")
            ->and($field['size'])->toBe(9);
    }
});

test('generali application form map centers dependents checks on every page-2 template row', function (): void {
    $fields = collect(generaliApplicationFormMapFields());

    // These row y values match the template's checkbox-row baselines.
    $rows = [51.0, 55.6, 60.2, 72.2, 76.8, 81.5, 95.5, 100.1, 114.2, 118.8, 123.5];

    foreach ($rows as $rowY) {
        $checkY = round($rowY - 1.9, 1);

        foreach ([98.8, 122.6] as $checkX) {
            $field = $fields->first(fn (array $f): bool => ($f['type'] ?? null) === 'check'
                && ($f['page'] ?? null) === 2
                && abs(($f['x'] ?? 0) - $checkX) < 0.05
                && abs(($f['y'] ?? 0) - $checkY) < 0.05);

            expect($field)->not->toBeNull("expected a page-2 check at ($checkX, $checkY)")
                ->and($field['size'])->toBe(9);
        }
    }
});

/*
 * 2026-08-20: dependents table name/birthdate/age rows were sitting ~1mm too
 * low (text overran the printed underline instead of resting above it), and
 * the page-3 "SIGNED AT ___ ON ___" fields sat ~2mm below the label line.
 * Separately, the address city/province/country columns had drifted onto the
 * wrong caption row (overlapping "Residence Address (Street No.) (Brgy.)"
 * instead of sitting under "(City/Municipality) (Province) (Country) (Zip
 * Code)"), and applicant.address_zip had no field at all. Pin the corrected
 * coordinates -- measured against the real template with PyMuPDF per the
 * wibs-documents skill's render-verify loop -- so these can't drift back
 * silently.
 */
test('generali application form map pins the recalibrated dependents row text positions', function (): void {
    $fields = collect(generaliApplicationFormMapFields());

    $rows = [
        [1, 28.5, 259.6], // spouse name
        [2, 28.5, 50.1],  // children.0 name
        [2, 28.5, 54.8],  // children.1 name
        [2, 28.5, 59.4],  // children.2 name
        [2, 28.5, 71.4],  // siblings.0 name
        [2, 28.5, 76.0],  // siblings.1 name
        [2, 28.5, 80.7],  // siblings.2 name
        [2, 28.5, 94.7],  // parents.0 name
        [2, 28.5, 99.4],  // parents.1 name
        [2, 28.5, 113.4], // extended.0 name
        [2, 28.5, 118.0], // extended.1 name
        [2, 28.5, 122.7], // extended.2 name
    ];

    foreach ($rows as [$page, $x, $y]) {
        $field = $fields->first(fn (array $f): bool => ($f['type'] ?? null) !== 'check'
            && ($f['page'] ?? null) === $page
            && abs(($f['x'] ?? 0) - $x) < 0.05
            && abs(($f['y'] ?? 0) - $y) < 0.05);

        expect($field)->not->toBeNull("expected a name field at page $page ($x, $y)");
    }
});

test('generali application form map fills the address zip column and positions city/province/country under their printed captions', function (): void {
    $fields = generaliApplicationFormMapFields();

    $zip = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_zip');
    expect($zip)->not->toBeNull()
        ->and($zip['x'])->toBe(165.6)
        ->and($zip['y'])->toBe(101.5);

    $city = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_city');
    $province = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_province');

    expect($city['x'])->toBe(43.1)->and($city['y'])->toBe(101.5);
    expect($province['x'])->toBe(94.0)->and($province['y'])->toBe(101.5);
});

test('generali application form map places street/purok and barangay under their own printed captions, not under the row label', function (): void {
    $fields = generaliApplicationFormMapFields();

    $street = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_street');
    $barangay = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_barangay');

    expect($street)->not->toBeNull()
        ->and($street['x'])->toBe(92.7)
        ->and($street['y'])->toBe(95.5);

    expect($barangay)->not->toBeNull()
        ->and($barangay['x'])->toBe(163.8)
        ->and($barangay['y'])->toBe(95.5);

    expect(collect($fields)->contains(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_line'))
        ->toBeFalse();
});

test('generali application form map aligns SIGNED AT / ON onto the label line on page 3', function (): void {
    $fields = collect(generaliApplicationFormMapFields());

    $signingPlace = $fields->first(fn (array $f) => ($f['value'] ?? null) === 'notarial.signing_place');
    $approvedDate = $fields->first(fn (array $f) => ($f['value'] ?? null) === 'loan.approved_date');

    expect($signingPlace)->not->toBeNull()->and($signingPlace['page'])->toBe(3)->and($signingPlace['y'])->toBe(237.2);
    expect($approvedDate)->not->toBeNull()->and($approvedDate['page'])->toBe(3)->and($approvedDate['y'])->toBe(237.2);
});
