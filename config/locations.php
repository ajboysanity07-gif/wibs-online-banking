<?php

return [
    'provider' => env('LOCATIONS_PROVIDER', 'ph-address'),
    'cache_ttl' => env('LOCATIONS_CACHE_TTL', 86400),

    // Static reference data (PSGC/zip lookups) is read far more often than it
    // changes, so it's cached on a local store instead of the app's default
    // CACHE_STORE (often 'database' in production) to avoid a DB round trip
    // for every cache read/write of this large dataset.
    'cache_store' => env('LOCATIONS_CACHE_STORE', 'file'),

    'providers' => [
        'ph-address' => [
            'data_path' => base_path('resources/data/ph-address.json'),
            'testing_data_path' => base_path('tests/Fixtures/ph-address.json'),
            'normalized_path' => base_path('resources/data/ph-address-normalized.json'),
        ],
        'ph-zipcodes' => [
            'data_path' => base_path('resources/data/ph-zipcodes.json'),
            'testing_data_path' => base_path('tests/Fixtures/ph-zipcodes.json'),
        ],
    ],
];
