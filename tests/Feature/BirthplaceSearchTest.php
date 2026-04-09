<?php

use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
    Config::set('locations.provider', 'ph-address');
    Config::set(
        'locations.providers.ph-address.testing_data_path',
        base_path('tests/Fixtures/ph-address.json'),
    );
});

test('birthplace search endpoint returns suggestions', function () {
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

test('birthplace search endpoint reports unavailable when dataset is missing', function () {
    Config::set(
        'locations.providers.ph-address.testing_data_path',
        base_path('tests/Fixtures/missing-ph-address.json'),
    );
    Cache::store()->flush();

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
