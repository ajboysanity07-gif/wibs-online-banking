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
