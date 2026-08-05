<?php

use App\Services\Locations\PsgcService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
    Config::set('locations.cache_store', 'array');
    Config::set('locations.provider', 'ph-address');
    Config::set(
        'locations.providers.ph-address.testing_data_path',
        base_path('tests/Fixtures/ph-address.json'),
    );
    Config::set(
        'locations.providers.ph-barangays.testing_data_path',
        base_path('tests/Fixtures/ph-barangays.json'),
    );
});

test('psgc service normalizes birthplace suggestions', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBirthplaces('Ada');

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0]['label'])->toBe('Adams, Ilocos Norte');
    expect($result['results'][0]['value'])->toBe('Adams, Ilocos Norte');
    expect($result['results'][0]['type'])->toBe('municipality');
});

test('psgc service includes province details for city suggestions', function () {
    $service = app(PsgcService::class);
    $result = $service->searchCities('Ada');

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0]['label'])->toBe('Adams, Ilocos Norte');
    expect($result['results'][0]['value'])->toBe('Adams');
    expect($result['results'][0]['province'])->toBe('Ilocos Norte');
    expect($result['results'][0]['type'])->toBe('municipality');
});

test('psgc service matches reordered tokens ignoring word order', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBirthplaces('Batac City');

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0]['label'])->toBe('City of Batac, Ilocos Norte');
});

test('psgc service caches dataset responses', function () {
    $service = app(PsgcService::class);
    $service->searchBirthplaces('Batac');
    $service->searchBirthplaces('Batac');

    expect(Cache::has('locations.dataset.v3'))->toBeTrue();
});

test('psgc service recognizes known localities in either bare or labeled form', function () {
    $service = app(PsgcService::class);

    expect($service->isKnownLocality('City of Batac'))->toBeTrue();
    expect($service->isKnownLocality('City of Batac, Ilocos Norte'))->toBeTrue();
    expect($service->isKnownLocality('Batac City'))->toBeTrue();
    expect($service->isKnownLocality('Not A Real City'))->toBeFalse();
    expect($service->isKnownLocality(''))->toBeFalse();
});

test('psgc service recognizes known provinces', function () {
    $service = app(PsgcService::class);

    expect($service->isKnownProvince('Ilocos Norte'))->toBeTrue();
    expect($service->isKnownProvince('Not A Real Province'))->toBeFalse();
    expect($service->isKnownProvince(''))->toBeFalse();
});

test('psgc service searches barangays scoped to a municipality', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBarangays('Davao City', 'Acacia');

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0]['label'])->toBe('Acacia');
    expect($result['results'][0]['value'])->toBe('Acacia');
    expect($result['results'][0]['type'])->toBe('barangay');
    expect($result['results'][0]['province'])->toBeNull();
});

test('psgc service returns every barangay for a municipality when the requested limit exceeds its total', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBarangays('Batac City', '', limit: 10000);

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toHaveCount(43);
});

test('psgc service scopes barangay results to the given municipality only', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBarangays('Batac City', 'Aglipay');

    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0]['label'])->toBe('Aglipay');

    $result = $service->searchBarangays('Davao City', 'Aglipay');

    expect($result['results'])->toHaveCount(0);
});

test('psgc service returns empty barangay results for an unknown municipality', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBarangays('Not A Real City', 'Aca');

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toBe([]);
});

test('psgc service returns empty barangay results when no municipality is given', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBarangays('', 'Aca');

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toBe([]);
});

test('psgc service recognizes known barangays scoped to their municipality', function () {
    $service = app(PsgcService::class);

    expect($service->isKnownBarangay('Batac City', 'Aglipay'))->toBeTrue();
    expect($service->isKnownBarangay('Davao City', 'Agdao'))->toBeTrue();
    expect($service->isKnownBarangay('Davao City', 'Aglipay'))->toBeFalse();
    expect($service->isKnownBarangay('Not A Real City', 'Aglipay'))->toBeFalse();
    expect($service->isKnownBarangay('Batac City', 'Not A Real Barangay'))->toBeFalse();
    expect($service->isKnownBarangay('Batac City', ''))->toBeFalse();
    expect($service->isKnownBarangay('', 'Aglipay'))->toBeFalse();
});

test('psgc service resolves manila barangays through its per-district psgc codes', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBarangays('City of Manila', '', limit: 20);

    expect($result['available'])->toBeTrue();
    $labels = collect($result['results'])->pluck('label');

    expect($labels)->toContain('Barangay 1')
        ->and($labels)->toContain('Barangay 287');
});

test('psgc service recognizes known manila barangays via the real per-district dataset', function () {
    $service = app(PsgcService::class);

    expect($service->isKnownBarangay('City of Manila', 'Barangay 1'))->toBeTrue();
    expect($service->isKnownBarangay('Manila', 'Not A Real Barangay'))->toBeFalse();
});

test('psgc service disambiguates same-named municipalities by province when searching barangays', function () {
    $service = app(PsgcService::class);

    $cebu = $service->searchBarangays('Carmen', 'Baring', province: 'Cebu');
    expect(collect($cebu['results'])->pluck('label'))->toContain('Baring');

    $davao = $service->searchBarangays('Carmen', 'Alejal', province: 'Davao del Norte');
    expect(collect($davao['results'])->pluck('label'))->toContain('Alejal');

    // Davao's "Alejal" must not leak into Cebu's Carmen when scoped by province.
    $cebuMismatch = $service->searchBarangays('Carmen', 'Alejal', province: 'Cebu');
    expect($cebuMismatch['results'])->toBe([]);
});

test('psgc service falls back to the first match when no province is given for an ambiguous municipality', function () {
    $service = app(PsgcService::class);
    $result = $service->searchBarangays('Carmen', 'Baring');

    expect(collect($result['results'])->pluck('label'))->toContain('Baring');
});

test('psgc service disambiguates same-named municipalities by province when validating known barangays', function () {
    $service = app(PsgcService::class);

    expect($service->isKnownBarangay('Carmen', 'Baring', 'Cebu'))->toBeTrue();
    expect($service->isKnownBarangay('Carmen', 'Alejal', 'Davao del Norte'))->toBeTrue();
    expect($service->isKnownBarangay('Carmen', 'Alejal', 'Cebu'))->toBeFalse();
    expect($service->isKnownBarangay('Carmen', 'Tambad', 'Cotabato'))->toBeTrue();
});
