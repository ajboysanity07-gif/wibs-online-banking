<?php

return [
    'provider' => env('LOCATIONS_PROVIDER', 'ph-address'),
    'cache_ttl' => env('LOCATIONS_CACHE_TTL', 86400),
    'providers' => [
        'ph-address' => [
            'data_path' => base_path('vendor/dmn/ph-address/database/seeders/PSGC.csv'),
            'testing_data_path' => base_path('vendor/dmn/ph-address/database/seeders/PSGCTest.csv'),
        ],
    ],
];
