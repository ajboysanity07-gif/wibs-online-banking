<?php

use App\Support\LocationComposer;

uses(Tests\TestCase::class);

test('compose joins street, city, and province without barangay', function (): void {
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila'))
        ->toBe('123 Main St, Manila, Metro Manila');
});

test('compose inserts barangay between street and city when given', function (): void {
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila', 'Barangay Uno'))
        ->toBe('123 Main St, Barangay Uno, Manila, Metro Manila');
});

test('compose omits barangay when blank', function (): void {
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila', ''))
        ->toBe('123 Main St, Manila, Metro Manila');
    expect(LocationComposer::compose('123 Main St', 'Manila', 'Metro Manila', null))
        ->toBe('123 Main St, Manila, Metro Manila');
});

test('composeBirthplace is unaffected by the barangay parameter', function (): void {
    expect(LocationComposer::composeBirthplace('Cebu City', 'Cebu'))
        ->toBe('Cebu City, Cebu');
});

test('composeUnique drops city and province already present in the street line', function (): void {
    expect(LocationComposer::composeUnique(
        'Purok 1 poblacion Lianga Surigao del sur',
        'Lianga',
        'Surigao del sur',
    ))->toBe('Purok 1 poblacion Lianga Surigao del sur');
});

test('composeUnique drops a typo\'d province that near-duplicates the street line', function (): void {
    expect(LocationComposer::composeUnique(
        'Purok 1 poblacion Lianga Surigao del sur',
        'Lianga',
        'Surigao del sue',
    ))->toBe('Purok 1 poblacion Lianga Surigao del sur');
});

test('composeUnique keeps distinct city and province tokens', function (): void {
    expect(LocationComposer::composeUnique(
        '123 Loan Street',
        'Loan City',
        'Loan Province',
    ))->toBe('123 Loan Street, Loan City, Loan Province');
});

test('composeUnique joins parts like compose when nothing is duplicated', function (): void {
    expect(LocationComposer::composeUnique('123 Main St', 'Manila', 'Metro Manila'))
        ->toBe('123 Main St, Manila, Metro Manila');
});

test('composeUnique omits blank parts', function (): void {
    expect(LocationComposer::composeUnique('123 Main St', null, 'Metro Manila', ''))
        ->toBe('123 Main St, Metro Manila');
});

test('composeUnique drops a barangay repeated inside the street line', function (): void {
    expect(LocationComposer::composeUnique(
        'Brgy San Isidro Purok 5',
        'Lianga',
        'Surigao del Sur',
        'San Isidro',
    ))->toBe('Brgy San Isidro Purok 5, Lianga, Surigao del Sur');
});

test('composeUnique returns an empty string when no parts are provided', function (): void {
    expect(LocationComposer::composeUnique(null, null, null))->toBe('');
});

test('recomposeLegacyAddress normalizes spacing around existing commas', function (): void {
    expect(LocationComposer::recomposeLegacyAddress('Purok 4 ,Tagbina,   Surigao del Sur'))
        ->toBe('Purok 4, Tagbina, Surigao del Sur');
});

test('recomposeLegacyAddress inserts commas via PSGC place matching when there are no separators at all', function (): void {
    expect(LocationComposer::recomposeLegacyAddress('Purok 4 Tagbina Surigao Del Sur'))
        ->toBe('Purok 4, Tagbina, Surigao del Sur');
});

test('recomposeLegacyAddress falls back to the raw string when nothing recognizable can be split out', function (): void {
    expect(LocationComposer::recomposeLegacyAddress('some random text with no place names'))
        ->toBe('some random text with no place names');
});

test('recomposeLegacyAddress returns an empty string for blank input', function (): void {
    expect(LocationComposer::recomposeLegacyAddress(null))->toBe('');
    expect(LocationComposer::recomposeLegacyAddress(''))->toBe('');
});

test('recomposeLegacyBirthplace normalizes spacing around existing commas', function (): void {
    expect(LocationComposer::recomposeLegacyBirthplace('Cebu City ,Cebu'))
        ->toBe('Cebu City, Cebu');
});

test('recomposeLegacyBirthplace inserts commas via PSGC place matching when there are no separators at all', function (): void {
    expect(LocationComposer::recomposeLegacyBirthplace('Cebu City Cebu'))
        ->toBe('City of Cebu, Cebu');
});

test('recomposeLegacyBirthplace falls back to the raw string when nothing recognizable can be split out', function (): void {
    expect(LocationComposer::recomposeLegacyBirthplace('nowhere in particular'))
        ->toBe('nowhere in particular');
});
