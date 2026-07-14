<?php

uses(Tests\TestCase::class);

function numericAdornedInputsSource(): string
{
    return file_get_contents(
        resource_path('js/components/loan-request/numeric-adorned-inputs.tsx'),
    );
}

test('numeric-adorned-inputs exports CurrencyInput, PercentInput, and MonthsInput', function () {
    $source = numericAdornedInputsSource();

    expect($source)->toContain('export function CurrencyInput')
        ->and($source)->toContain('export function PercentInput')
        ->and($source)->toContain('export function MonthsInput');
});

test('PercentInput converts between the stored fraction and the displayed percentage', function () {
    $source = numericAdornedInputsSource();

    expect($source)->toContain('* 100')
        ->and($source)->toContain('/ 100');
});

test('neither component hardcodes the old PHP text label', function () {
    $source = numericAdornedInputsSource();

    expect($source)->not->toContain("'PHP'")
        ->and($source)->not->toContain('"PHP"')
        ->and($source)->not->toContain('>PHP<');
});

test('MonthsInput is built on NumericFormat with integer-only, non-negative constraints', function () {
    $source = numericAdornedInputsSource();

    $monthsInputStart = strpos($source, 'export function MonthsInput');
    expect($monthsInputStart)->not->toBeFalse();

    $monthsInputBody = substr($source, $monthsInputStart);

    expect($monthsInputBody)->toContain('NumericFormat')
        ->and($monthsInputBody)->toContain('decimalScale={0}')
        ->and($monthsInputBody)->toContain('allowNegative={false}')
        ->and($monthsInputBody)->not->toContain('event.target.value');
});
