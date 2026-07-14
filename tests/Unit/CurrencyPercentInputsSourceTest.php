<?php

uses(Tests\TestCase::class);

function currencyPercentInputsSource(): string
{
    return file_get_contents(
        resource_path('js/components/loan-request/currency-percent-inputs.tsx'),
    );
}

test('currency-percent-inputs exports CurrencyInput and PercentInput', function () {
    $source = currencyPercentInputsSource();

    expect($source)->toContain('export function CurrencyInput')
        ->and($source)->toContain('export function PercentInput');
});

test('PercentInput converts between the stored fraction and the displayed percentage', function () {
    $source = currencyPercentInputsSource();

    expect($source)->toContain('* 100')
        ->and($source)->toContain('/ 100');
});

test('neither component hardcodes the old PHP text label', function () {
    $source = currencyPercentInputsSource();

    expect($source)->not->toContain("'PHP'")
        ->and($source)->not->toContain('"PHP"')
        ->and($source)->not->toContain('>PHP<');
});
