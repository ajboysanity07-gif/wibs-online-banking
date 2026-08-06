<?php

use App\Models\Wmaster;
use App\Services\Locations\PsgcService;

uses(Tests\TestCase::class);

test('wmaster resolves normalized and raw address/birthplace parts', function () {
    $wmaster = Wmaster::factory()->make([
        'birthplace' => 'TAGUM CITY, DDN',
        'address1' => '123 Mabini St',
        'address2' => 'MAGUGPO POBLACION',
        'address3' => 'TAGUM CITY',
        'address4' => 'DDN',
    ]);

    $parts = $wmaster->resolvedAddressParts(app(PsgcService::class));

    expect($parts['address1'])->toBe('123 Mabini St');
    expect($parts['barangay'])->toBe('Magugpo Poblacion');
    expect($parts['barangay_raw'])->toBe('MAGUGPO POBLACION');
    expect($parts['address2'])->toBe('City of Tagum');
    expect($parts['address2_raw'])->toBe('TAGUM CITY');
    expect($parts['address3'])->toBe('Davao del Norte');
    expect($parts['address3_raw'])->toBe('DDN');
    expect($parts['zip_code'])->toBeNull();
    expect($parts['birthplace_city'])->toBe('City of Tagum');
    expect($parts['birthplace_province'])->toBe('Davao del Norte');
});

test('wmaster resolves empty address/birthplace parts to null', function () {
    $wmaster = Wmaster::factory()->make([
        'birthplace' => null,
        'address1' => null,
        'address2' => null,
        'address3' => null,
        'address4' => null,
    ]);

    $parts = $wmaster->resolvedAddressParts(app(PsgcService::class));

    expect($parts['address1'])->toBeNull();
    expect($parts['barangay'])->toBeNull();
    expect($parts['barangay_raw'])->toBeNull();
    expect($parts['address2'])->toBeNull();
    expect($parts['address3'])->toBeNull();
    expect($parts['zip_code'])->toBeNull();
    expect($parts['birthplace_city'])->toBeNull();
    expect($parts['birthplace_province'])->toBeNull();
});
