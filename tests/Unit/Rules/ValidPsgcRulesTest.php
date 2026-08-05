<?php

use App\Rules\ValidPostalCode;
use App\Rules\ValidPsgcLocality;
use App\Rules\ValidPsgcProvince;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

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
});

test('valid psgc locality rule accepts a known city and rejects a made-up one', function () {
    $passing = Validator::make(
        ['city' => 'City of Batac'],
        ['city' => [new ValidPsgcLocality]],
    );
    $failing = Validator::make(
        ['city' => 'Not A Real City'],
        ['city' => [new ValidPsgcLocality]],
    );

    expect($passing->passes())->toBeTrue();
    expect($failing->fails())->toBeTrue();
});

test('valid psgc province rule accepts a known province and rejects a made-up one', function () {
    $passing = Validator::make(
        ['province' => 'Ilocos Norte'],
        ['province' => [new ValidPsgcProvince]],
    );
    $failing = Validator::make(
        ['province' => 'Not A Real Province'],
        ['province' => [new ValidPsgcProvince]],
    );

    expect($passing->passes())->toBeTrue();
    expect($failing->fails())->toBeTrue();
});

test('valid postal code rule accepts a known zip and rejects a made-up one', function () {
    $passing = Validator::make(
        ['zip' => '8309'],
        ['zip' => [new ValidPostalCode]],
    );
    $failing = Validator::make(
        ['zip' => '00000'],
        ['zip' => [new ValidPostalCode]],
    );

    expect($passing->passes())->toBeTrue();
    expect($failing->fails())->toBeTrue();
});
