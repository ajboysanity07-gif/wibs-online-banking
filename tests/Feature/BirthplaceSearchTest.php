<?php

use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
});

test('birthplace search endpoint returns suggestions', function () {
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

    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('api.locations.birthplaces', ['search' => 'Batac']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
        ])
        ->assertJsonPath('data.0.label', 'City of Batac, Ilocos Norte');
});

test('birthplace search endpoint reports unavailable when psgc fails', function () {
    Http::fake([
        'https://psgc.cloud/api/*' => Http::response(null, 503),
    ]);

    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('api.locations.birthplaces', ['search' => 'Batac']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => false,
            'message' => 'Birthplace suggestions are temporarily unavailable.',
            'data' => [],
        ]);
});
