<?php

test('apache compresses text responses and long-caches hashed build assets', function (): void {
    $conf = file_get_contents(base_path('docker/performance.conf'));

    expect($conf)->toContain('mod_deflate.c')->toContain('DEFLATE')
        ->toContain('/build/')->toContain('max-age=31536000, immutable');
});

test('dockerfile enables deflate and installs the performance conf', function (): void {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('a2enmod rewrite headers deflate')
        ->toContain('docker/performance.conf');
});
