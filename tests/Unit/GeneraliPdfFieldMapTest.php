<?php

use App\Services\LoanRequests\PdfFieldMaps\GeneraliPdfFieldMap;

test('generali map prints height and weight with units from the application form', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $height = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 165.9 && ($f['y'] ?? null) === 115.0);
    $weight = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 178.9 && ($f['y'] ?? null) === 115.0);

    expect($height)->not->toBeNull();
    expect($weight)->not->toBeNull();

    $withValues = ['application_form' => ['height_cm' => '165', 'weight_kg' => '68']];
    expect(($height['value'])($withValues))->toBe('165 cm');
    expect(($weight['value'])($withValues))->toBe('68 kg');

    expect(($height['value'])(['application_form' => ['height_cm' => null]]))->toBeNull();
    expect(($weight['value'])(['application_form' => ['weight_kg' => '']]))->toBeNull();
});

test('generali map prints source of fund and the composed government id', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $source = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 56.0 && ($f['y'] ?? null) === 129.2);
    $id = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 153.0 && ($f['y'] ?? null) === 129.2);
    $idOther = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 153.0 && ($f['y'] ?? null) === 133.1);

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

test('generali map derives the item 2 header Yes/No across every sub-question', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $q2Y = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 128.2 && ($f['y'] ?? null) === 216.64);
    $q2N = collect($fields)->first(fn (array $f) => ($f['x'] ?? null) === 135.2 && ($f['y'] ?? null) === 216.64);

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

test('generali health rows were recalibrated onto the template checkbox glyphs', function (): void {
    $fields = (new GeneraliPdfFieldMap)->fields();

    $expectedY = [
        207.45, // Q1
        216.64, // Q2 header
        234.56, // 2d
        264.55, // 2i
        33.58,  // Q3 (page 2)
        62.44,  // Q6
        85.72,  // Q10
        104.99, // Q13
        120.51, // Q15 pregnancy/complications
    ];

    foreach ($expectedY as $y) {
        expect(collect($fields)->pluck('y')->contains($y))
            ->toBeTrue("expected a field at recalibrated y=$y");
    }
});
