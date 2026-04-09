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
        base_path('tests/Fixtures/psgc-locations.csv'),
    );

    $this->user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $this->user->user_id,
    ]);
});

test('province search endpoint returns normalized suggestions', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.provinces', ['search' => 'Ceb']));

    $response
        ->assertSuccessful()
        ->assertJsonPath('data.0.label', 'Cebu')
        ->assertJsonPath('data.0.type', 'province')
        ->assertJsonPath('data.0.value', 'Cebu');
});

test('city search returns duplicate names with province labels', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.cities', ['search' => 'Carmen']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
        ]);

    $data = collect($response->json('data'));
    $labels = $data->pluck('label')->sort()->values()->all();
    $provinces = $data->pluck('province')->sort()->values()->all();
    $values = $data->pluck('value')->unique()->values()->all();

    expect($labels)->toBe([
        'Carmen, Cebu',
        'Carmen, Cotabato',
        'Carmen, Davao del Norte',
    ]);
    expect($provinces)->toBe([
        'Cebu',
        'Cotabato',
        'Davao del Norte',
    ]);
    expect($values)->toBe(['Carmen']);
});
