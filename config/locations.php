<?php

return [
    'provider' => env('LOCATIONS_PROVIDER', 'ph-address'),
    'cache_ttl' => env('LOCATIONS_CACHE_TTL', 86400),
    'providers' => [
        'ph-address' => [
            'node_binary' => env('PH_ADDRESS_NODE_BINARY', 'node'),
            'node_timeout' => env('PH_ADDRESS_NODE_TIMEOUT', 20),
            'testing_data_path' => base_path('tests/Fixtures/ph-address.json'),
        ],
    ],
];
