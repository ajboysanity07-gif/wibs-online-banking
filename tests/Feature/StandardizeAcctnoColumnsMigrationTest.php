<?php

use Illuminate\Support\Facades\Schema;

test('acctno normalization migration is guarded for non-sqlsrv', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
        $this->markTestSkipped('This test assumes a non-sqlsrv connection.');
    }

    $migration = require base_path(
        'database/migrations/2026_04_10_031254_standardize_acctno_columns.php',
    );

    $migration->up();
    $migration->down();

    expect(true)->toBeTrue();
});
