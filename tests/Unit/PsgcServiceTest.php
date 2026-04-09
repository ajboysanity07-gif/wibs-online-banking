<?php

use App\Services\Locations\PsgcService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
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

test('psgc service caches dataset responses', function () {
    $service = app(PsgcService::class);
    $service->searchBirthplaces('Batac');
    $service->searchBirthplaces('Batac');

    expect(Cache::has('locations.dataset.v2'))->toBeTrue();
});
