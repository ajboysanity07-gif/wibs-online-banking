<?php

use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

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

test('city search returns duplicate names disambiguated via the province field', function () {
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

    // Labels intentionally show the bare city name -- the frontend renders
    // a separate City/Municipality type badge, and province disambiguation
    // is carried by the `province` field instead (see PsgcService::searchCities()).
    expect($labels)->toBe([
        'Carmen',
        'Carmen',
        'Carmen',
    ]);
    expect($provinces)->toBe([
        'Cebu',
        'Cotabato',
        'Davao del Norte',
    ]);
    expect($values)->toBe(['Carmen']);
});

test('city search returns davao suggestions', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.cities', ['search' => 'Davao']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
        ]);

    $data = collect($response->json('data'));
    $labels = $data->pluck('label');
    $values = $data->pluck('value');

    expect($labels)->toContain('City of Davao');
    expect($values)->toContain('City of Davao');
});

test('city search endpoint returns a province\'s full city list without a search term', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.cities', ['search' => '', 'province' => 'Davao del Norte']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
        ]);

    $data = collect($response->json('data'));

    expect($data)->not->toBeEmpty();
    expect($data->pluck('province')->unique()->all())->toBe(['Davao del Norte']);
    expect($data->pluck('label'))->toContain('Carmen');
});

test('city search endpoint returns empty data for an empty search without a province', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.cities', ['search' => '']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
            'data' => [],
        ]);
});

test('province search returns davao provinces', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.provinces', ['search' => 'Davao']));

    $response->assertSuccessful();

    $labels = collect($response->json('data'))->pluck('label');

    expect($labels)->toContain('Davao del Norte');
    expect($labels)->toContain('Davao del Sur');
});

test('barangay search endpoint returns suggestions scoped to a municipality', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.barangays', [
            'municipality' => 'Davao City',
            'search' => 'Acacia',
        ]));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
        ])
        ->assertJsonPath('data.0.label', 'Acacia')
        ->assertJsonPath('data.0.value', 'Acacia')
        ->assertJsonPath('data.0.type', 'barangay');
});

test('barangay search endpoint disambiguates a same-named municipality using the province param', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.barangays', [
            'municipality' => 'Carmen',
            'province' => 'Davao del Norte',
            'search' => 'Alejal',
        ]));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
        ])
        ->assertJsonPath('data.0.label', 'Alejal');
});

test('barangay search endpoint returns empty data without a municipality', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.barangays', ['search' => 'Aca']));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
            'data' => [],
        ]);
});

test('barangay search endpoint returns empty data for an unknown municipality', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('api.locations.barangays', [
            'municipality' => 'Not A Real City',
            'search' => 'Aca',
        ]));

    $response
        ->assertSuccessful()
        ->assertJson([
            'ok' => true,
            'available' => true,
            'data' => [],
        ]);
});

test('barangay search endpoint requires authentication', function () {
    $response = $this->get(route('api.locations.barangays', [
        'municipality' => 'Davao City',
        'search' => 'Aca',
    ]));

    $response->assertRedirect(route('login'));
});
