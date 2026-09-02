<?php

use App\Models\LoanRequestPerson;

uses(Tests\TestCase::class);

test('composedAddress inserts commas via PSGC matching when the unsplit blob was saved into address1 alone', function (): void {
    $person = new LoanRequestPerson([
        'address1' => 'Purok 4 Tagbina Surigao Del Sur',
        'address2' => null,
        'address3' => null,
        'address_barangay' => null,
        'address' => null,
    ]);

    expect($person->composedAddress())->toBe('Purok 4, Tagbina, Surigao del Sur');
});

test('composedAddress leaves a properly comma-separated street line untouched', function (): void {
    $person = new LoanRequestPerson([
        'address1' => '123 Loan Street',
        'address2' => 'Loan City',
        'address3' => 'Loan Province',
        'address_barangay' => null,
        'address' => null,
    ]);

    expect($person->composedAddress())->toBe('123 Loan Street, Loan City, Loan Province');
});

test('composedAddress falls back to the legacy address column when structured fields are blank', function (): void {
    $person = new LoanRequestPerson([
        'address1' => null,
        'address2' => null,
        'address3' => null,
        'address_barangay' => null,
        'address' => 'Purok 4 Tagbina Surigao Del Sur',
    ]);

    expect($person->composedAddress())->toBe('Purok 4, Tagbina, Surigao del Sur');
});
