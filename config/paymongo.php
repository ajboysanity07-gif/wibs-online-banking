<?php

return [
    'public_key' => env('PAYMONGO_PUBLIC_KEY'),
    'secret_key' => env('PAYMONGO_SECRET_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    'mode' => env('PAYMONGO_MODE', 'test'),
    'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
    'payment_methods' => ['qrph', 'gcash', 'paymaya', 'dob'],
];
