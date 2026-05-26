<?php

$supportedPaymentMethods = ['gcash', 'paymaya', 'qrph', 'dob'];

$configuredPaymentMethods = array_values(array_unique(array_filter(array_map(
    static fn (string $method): string => strtolower(trim($method)),
    explode(',', (string) env('PAYMONGO_PAYMENT_METHODS', 'gcash')),
))));

$paymentMethods = array_values(array_intersect($configuredPaymentMethods, $supportedPaymentMethods));

return [
    'public_key' => env('PAYMONGO_PUBLIC_KEY'),
    'secret_key' => env('PAYMONGO_SECRET_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    'mode' => env('PAYMONGO_MODE', 'test'),
    'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
    // Enable qrph, paymaya, and dob only after confirming they are available in the PayMongo merchant account.
    'payment_methods' => $paymentMethods === [] ? ['gcash'] : $paymentMethods,
];
