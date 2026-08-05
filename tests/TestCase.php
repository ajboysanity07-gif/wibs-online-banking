<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    private ?string $isolatedStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $realTemplatesDirectory = storage_path('app/templates/approved-loan-documents');
        $realCustomFontsDirectory = storage_path('app/fonts/tcpdf');

        $this->isolatedStoragePath = storage_path('framework/testing/disks/'.Str::random(16));

        $this->app->useStoragePath($this->isolatedStoragePath);

        if (File::isDirectory($realTemplatesDirectory)) {
            File::copyDirectory(
                $realTemplatesDirectory,
                storage_path('app/templates/approved-loan-documents'),
            );
        }

        if (File::isDirectory($realCustomFontsDirectory)) {
            File::copyDirectory($realCustomFontsDirectory, storage_path('app/fonts/tcpdf'));
        }

        // The 'file' cache store resolves to the real (non-isolated)
        // storage path at config-load time, before useStoragePath() above
        // runs -- so a stale PSGC/zip dataset cached on a developer's or
        // CI's disk from a real artisan run would otherwise leak into every
        // test. Forget those keys so each test starts with a clean slate.
        \Illuminate\Support\Facades\Cache::store(config('locations.cache_store', 'file'))
            ->forget('locations.dataset.v3');
        \Illuminate\Support\Facades\Cache::store(config('locations.cache_store', 'file'))
            ->forget('locations.zipcodes.v1');
    }

    protected function tearDown(): void
    {
        if ($this->isolatedStoragePath !== null) {
            File::deleteDirectory($this->isolatedStoragePath);
        }

        parent::tearDown();
    }
}
