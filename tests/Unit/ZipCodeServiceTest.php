<?php

use App\Services\Locations\ZipCodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
    Config::set(
        'locations.providers.ph-zipcodes.testing_data_path',
        base_path('tests/Fixtures/ph-zipcodes.json'),
    );
});

test('zip code service resolves known locality codes', function () {
    $service = app(ZipCodeService::class);

    expect($service->lookup('1606801000'))->toBe('8309');
    expect($service->lookup('0730600000'))->toBe('6000');
    expect($service->lookup('1381300000'))->toBe('1100');
    expect($service->lookup('0102805000'))->toBe('2906');
});

test('zip code service returns null for unknown locality codes', function () {
    $service = app(ZipCodeService::class);

    expect($service->lookup('0000000000'))->toBeNull();
});

test('zip code service returns null for blank locality codes', function () {
    $service = app(ZipCodeService::class);

    expect($service->lookup(''))->toBeNull();
    expect($service->lookup(null))->toBeNull();
});

test('zip code service caches the dataset', function () {
    $service = app(ZipCodeService::class);
    $service->lookup('1606801000');
    $service->lookup('1606801000');

    expect(Cache::has('locations.zipcodes.v1'))->toBeTrue();
});
