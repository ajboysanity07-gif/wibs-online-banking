<?php

use App\Services\Locations\PsgcService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
    Config::set('locations.cache_store', 'array');
    Config::set(
        'locations.providers.ph-address.testing_data_path',
        base_path('tests/Fixtures/ph-address.json'),
    );
    Config::set(
        'locations.providers.ph-zipcodes.testing_data_path',
        base_path('tests/Fixtures/ph-zipcodes.json'),
    );
    Config::set(
        'locations.providers.ph-barangays.testing_data_path',
        base_path('tests/Fixtures/ph-barangays.json'),
    );
});

it('resolves ALL CAPS locality to canonical form', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveLocalityName('CITY OF MANILA'))->toBe('City of Manila');
    expect($psgc->resolveLocalityName('DAVAO CITY'))->toBe('City of Davao');
    expect($psgc->resolveLocalityName('BATAC CITY'))->toBe('City of Batac');
});

it('resolves lowercase locality to canonical form', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveLocalityName('city of manila'))->toBe('City of Manila');
    expect($psgc->resolveLocalityName('davao city'))->toBe('City of Davao');
});

it('resolves mixed-case locality to canonical form', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveLocalityName('CiTy Of MaNiLa'))->toBe('City of Manila');
    expect($psgc->resolveLocalityName('DaVaO cItY'))->toBe('City of Davao');
});

it('resolves "X City" variant to "City of X" canonical form', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveLocalityName('Manila City'))->toBe('City of Manila');
    expect($psgc->resolveLocalityName('MANILA CITY'))->toBe('City of Manila');
});

it('resolves bare municipality name to canonical form', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveLocalityName('carmen'))->toBe('Carmen');
    expect($psgc->resolveLocalityName('CARMEN'))->toBe('Carmen');
});

it('resolves ALL CAPS province to canonical form', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveProvinceName('DAVAO DEL NORTE'))->toBe('Davao del Norte');
    expect($psgc->resolveProvinceName('DAVAO DEL SUR'))->toBe('Davao del Sur');
    expect($psgc->resolveProvinceName('METRO MANILA'))->toBe('Metro Manila');
    expect($psgc->resolveProvinceName('CEBU'))->toBe('Cebu');
});

it('resolves lowercase province to canonical form', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveProvinceName('davao del norte'))->toBe('Davao del Norte');
    expect($psgc->resolveProvinceName('metro manila'))->toBe('Metro Manila');
});

it('returns empty string for empty input', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveLocalityName(''))->toBe('');
    expect($psgc->resolveProvinceName(''))->toBe('');
    expect($psgc->resolveLocalityName('   '))->toBe('');
});

it('returns best-effort title-cased value for unrecognized input', function () {
    $psgc = app(PsgcService::class);

    expect($psgc->resolveLocalityName('NOT A REAL CITY'))->toBe('Not A Real City');
    expect($psgc->resolveProvinceName('NOT A REAL PROVINCE'))->toBe('Not A Real Province');
});

it('resolved locality passes ValidPsgcLocality', function () {
    $psgc = app(PsgcService::class);
    $resolved = $psgc->resolveLocalityName('CITY OF MANILA');

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['city' => $resolved],
        ['city' => [new App\Rules\ValidPsgcLocality]],
    );

    expect($validator->passes())->toBeTrue();
});

it('resolved province passes ValidPsgcProvince', function () {
    $psgc = app(PsgcService::class);
    $resolved = $psgc->resolveProvinceName('DAVAO DEL NORTE');

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['province' => $resolved],
        ['province' => [new App\Rules\ValidPsgcProvince]],
    );

    expect($validator->passes())->toBeTrue();
});

it('resolves barangay with ALL CAPS municipality context', function () {
    $psgc = app(PsgcService::class);

    $resolved = $psgc->resolveBarangayName('ALEJAL', 'CARMEN', 'DAVAO DEL NORTE');

    expect($resolved)->toBe('Alejal');

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['barangay' => $resolved],
        ['barangay' => [new App\Rules\ValidPsgcBarangay('Carmen', 'Davao del Norte')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('isKnownLocality accepts ANY case variation after resolve', function () {
    $psgc = app(PsgcService::class);

    $cases = [
        'CITY OF MANILA',
        'city of manila',
        'City of Manila',
        'CiTy Of MaNiLa',
        'CITY  OF  MANILA',
    ];

    foreach ($cases as $input) {
        $resolved = $psgc->resolveLocalityName($input);
        expect($psgc->isKnownLocality($resolved))->toBeTrue();
    }
});

it('isKnownProvince accepts ANY case variation after resolve', function () {
    $psgc = app(PsgcService::class);

    $cases = [
        'DAVAO DEL NORTE',
        'davao del norte',
        'Davao Del Norte',
    ];

    foreach ($cases as $input) {
        $resolved = $psgc->resolveProvinceName($input);
        expect($psgc->isKnownProvince($resolved))->toBeTrue();
    }
});

it('isKnownBarangay accepts ANY case variation after resolve', function () {
    $psgc = app(PsgcService::class);

    $cases = ['ALEJAL', 'alejal', 'Alejal'];

    foreach ($cases as $input) {
        $resolved = $psgc->resolveBarangayName($input, 'Carmen', 'Davao del Norte');
        expect($psgc->isKnownBarangay('Carmen', $resolved, 'Davao del Norte'))->toBeTrue();
    }
});
