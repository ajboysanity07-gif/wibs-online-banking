<?php

use App\Services\Locations\PsgcService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
});

test('psgc service normalizes birthplace suggestions', function () {
    Http::fake([
        'https://psgc.cloud/api/regions' => Http::response([
            ['code' => '0100000000', 'name' => 'Region I (Ilocos Region)'],
        ]),
        'https://psgc.cloud/api/provinces' => Http::response([
            ['code' => '0102800000', 'name' => 'Ilocos Norte'],
        ]),
        'https://psgc.cloud/api/cities' => Http::response([
            [
                'code' => '0102805000',
                'name' => 'City of Batac',
                'type' => 'City',
                'district' => '2nd',
                'zip_code' => '2906',
            ],
        ]),
        'https://psgc.cloud/api/municipalities' => Http::response([
            [
                'code' => '0102801000',
                'name' => 'Adams',
                'type' => 'Mun',
                'district' => '1st',
                'zip_code' => '',
            ],
        ]),
    ]);

    $service = new PsgcService;
    $result = $service->searchBirthplaces('Ada');

    expect($result['available'])->toBeTrue();
    expect($result['results'])->toHaveCount(1);
    expect($result['results'][0]['label'])->toBe('Adams, Ilocos Norte');
    expect($result['results'][0]['value'])->toBe('Adams, Ilocos Norte');
    expect($result['results'][0]['type'])->toBe('municipality');
});

test('psgc service caches birthplace responses', function () {
    Http::fake([
        'https://psgc.cloud/api/regions' => Http::response([
            ['code' => '0100000000', 'name' => 'Region I (Ilocos Region)'],
        ]),
        'https://psgc.cloud/api/provinces' => Http::response([
            ['code' => '0102800000', 'name' => 'Ilocos Norte'],
        ]),
        'https://psgc.cloud/api/cities' => Http::response([
            [
                'code' => '0102805000',
                'name' => 'City of Batac',
                'type' => 'City',
                'district' => '2nd',
                'zip_code' => '2906',
            ],
        ]),
        'https://psgc.cloud/api/municipalities' => Http::response([
            [
                'code' => '0102801000',
                'name' => 'Adams',
                'type' => 'Mun',
                'district' => '1st',
                'zip_code' => '',
            ],
        ]),
    ]);

    $service = new PsgcService;
    $service->searchBirthplaces('Batac');
    $service->searchBirthplaces('Batac');

    Http::assertSentCount(4);
});
