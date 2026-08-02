<?php

use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('cache.default', 'array');
    Cache::store()->flush();
    Config::set(
        'locations.providers.ph-zipcodes.testing_data_path',
        base_path('tests/Fixtures/ph-zipcodes.json'),
    );

    $this->user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $this->user->user_id,
    ]);
});

test('zip lookup endpoint resolves a locality code', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.zip', ['locality_code' => '1606801000']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
            'zip' => '8309',
        ]);
});

test('zip lookup endpoint returns null for unknown locality codes', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.zip', ['locality_code' => '0000000000']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
            'zip' => null,
        ]);
});

test('zip lookup endpoint requires authentication', function () {
    $this->get(route('api.locations.zip', ['locality_code' => '1606801000']))
        ->assertRedirect(route('login'));
});
