<?php

use App\Services\LoanRequests\PdfFieldMaps\GeneraliPdfFieldMap;

test('generali map prints height and weight with units from the application form', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $height = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 165.9 && ($f['y'] ?? null) === 112.0);
    $weight = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 178.9 && ($f['y'] ?? null) === 112.0);

    expect($height)->not->toBeNull();
    expect($weight)->not->toBeNull();

    $withValues = ['application_form' => ['height_cm' => '165', 'weight_kg' => '68']];
    expect(($height['value'])($withValues))->toBe('165 cm');
    expect(($weight['value'])($withValues))->toBe('68 kg');

    expect(($height['value'])(['application_form' => ['height_cm' => null]]))->toBeNull();
    expect(($weight['value'])(['application_form' => ['weight_kg' => '']]))->toBeNull();
});

test('generali map places street/purok and barangay under their own printed captions, not under the row label', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $street = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_street');
    $barangay = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_barangay');

    expect($street)->not->toBeNull()
        ->and($street['x'])->toBe(92.7)
        ->and($street['y'])->toBe(82.1);

    expect($barangay)->not->toBeNull()
        ->and($barangay['x'])->toBe(163.8)
        ->and($barangay['y'])->toBe(82.1);

    expect(collect($fields)->contains(fn (array $f) => ($f['value'] ?? null) === 'applicant.address_line'))
        ->toBeFalse();
});

test('generali map aligns the occupation column, loan amount, and loan term inside their own rows', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $occupation = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.position_or_designation' && ($f['x'] ?? null) === 116.2);
    $amount = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'loan.approved_amount');
    $term = collect($fields)->first(fn (array $f) => ($f['value'] ?? null) === 'loan.approved_term_label');

    expect($occupation)->not->toBeNull()
        ->and($occupation['x'])->toBe(116.2)
        ->and($occupation['y'])->toBe(125.0);

    expect($amount)->not->toBeNull()
        ->and($amount['x'])->toBe(27.3)
        ->and($amount['y'])->toBe(170.0);

    expect($term)->not->toBeNull()
        ->and($term['x'])->toBe(124.1)
        ->and($term['y'])->toBe(170.0);
});

test('generali map prints source of fund and the composed government id', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $source = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 27.3 && ($f['y'] ?? null) === 134.2);
    $id = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 150.0 && ($f['y'] ?? null) === 130.3);
    $idOther = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 150.0 && ($f['y'] ?? null) === 134.2);

    expect($source)->not->toBeNull();
    expect($id)->not->toBeNull();
    expect($idOther)->not->toBeNull();

    $resolve = function (array $field, array $documentData): mixed {
        $value = $field['value'] ?? null;

        if (is_callable($value)) {
            return $value($documentData);
        }

        return is_string($value) ? data_get($documentData, $value) : $value;
    };

    $standard = ['application_form' => [
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_type_other' => 'Driver License',
        'id_number' => '12-3456789-0',
    ]];
    expect($resolve($source, $standard))->toBe('Salary');
    expect($resolve($id, $standard))->toBe('SSS 12-3456789-0');
    expect($resolve($idOther, $standard))->toBeNull();

    $others = ['application_form' => [
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'Others',
        'id_type_other' => 'Driver License',
        'id_number' => '1234',
    ]];
    expect($resolve($id, $others))->toBe('Driver License 1234');
    expect($resolve($idOther, $others))->toBe('Driver License');

    expect($resolve($id, ['application_form' => ['id_type' => 'SSS', 'id_number' => null]]))->toBeNull();
});

test('generali map prints the employer date employed below the date employed label', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $date = collect($fields)->first(
        fn (array $f) => ($f['x'] ?? null) === 125.1 && ($f['y'] ?? null) === 157.0,
    );

    expect($date)->not->toBeNull();

    $resolve = function (array $field, array $documentData): mixed {
        $value = $field['value'] ?? null;

        if (is_callable($value)) {
            return $value($documentData);
        }

        return is_string($value) ? data_get($documentData, $value) : $value;
    };

    expect($resolve($date, ['applicant' => ['employer_date_employed' => '06/01/2019']]))->toBe('06/01/2019');
    expect($resolve($date, ['applicant' => ['employer_date_employed' => null]]))->toBeNull();
});

test('generali map derives the item 2 header Yes/No across every sub-question', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $q2Y = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 127.68 && ($f['y'] ?? null) === 216.28);
    $q2N = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 134.64 && ($f['y'] ?? null) === 216.28);

    expect($q2Y)->not->toBeNull();
    expect($q2N)->not->toBeNull();

    $base = [
        'gl_health_q02a_neuro' => false,
        'gl_health_q02b_respiratory' => false,
        'gl_health_q02c_cardiac' => false,
        'gl_health_q02d_digestive' => false,
        'gl_health_q02e_diabetes' => false,
        'gl_health_q02e_kidney' => false,
        'gl_health_q02e_liver' => false,
        'gl_health_q02e_urinary' => false,
        'gl_health_q02f_musculoskeletal' => false,
        'gl_health_q02g_oncology_blood' => false,
        'gl_health_q02h_dermatologic' => false,
        'gl_health_q02i_std_viral' => false,
        'gl_health_q02j_other_illness' => false,
    ];
    $doc = fn (array $overrides): array => ['health_glapi' => array_merge($base, $overrides)];

    expect(($q2Y['value'])($doc([])))->toBeFalse();
    expect(($q2N['value'])($doc([])))->toBeTrue();

    expect(($q2Y['value'])($doc(['gl_health_q02e_kidney' => true])))->toBeTrue();
    expect(($q2N['value'])($doc(['gl_health_q02e_kidney' => true])))->toBeFalse();

    expect(($q2Y['value'])($doc(['gl_health_q02g_oncology_blood' => null])))->toBeFalse();
    expect(($q2N['value'])($doc(['gl_health_q02g_oncology_blood' => null])))->toBeFalse();
});

test('generali map prints the proposed insured printed name over the right-hand signature line', function (): void {
    $fields = collect((new GeneraliPdfFieldMap)->fields());

    $printedName = $fields->first(fn (array $f) => ($f['value'] ?? null) === 'applicant.full_name');

    expect($printedName)->not->toBeNull()
        ->and($printedName['page'])->toBe(2)
        ->and($printedName['size'])->toBe(9)
        ->and($printedName['x'])->toBe(107.1)
        ->and($printedName['y'])->toBe(259.8);

    expect(($printedName['transform'])('juan dela cruz'))->toBe('JUAN DELA CRUZ');
});

test('generali health rows were recalibrated onto the template checkbox glyphs', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $expectedY = [
        207.25, // Q1
        216.28, // Q2 header
        234.21, // 2d
        264.35, // 2i
        33.23,  // Q3 (page 2)
        62.09,  // Q6
        85.37,  // Q10
        104.64, // Q13
        120.16, // Q15 pregnancy/complications
    ];

    foreach ($expectedY as $y) {
        expect(collect($fields)->pluck('y')->contains($y))
            ->toBeTrue("expected a field at recalibrated y=$y");
    }
});
