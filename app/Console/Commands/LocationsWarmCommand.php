<?php

namespace App\Console\Commands;

use App\Services\Locations\PsgcService;
use App\Services\Locations\ZipCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LocationsWarmCommand extends Command
{
    protected $signature = 'locations:warm
        {--regenerate : Rebuild resources/data/ph-address-normalized.json from the raw PSGC dataset before warming the cache}';

    protected $description = 'Warm the PSGC/zip location caches so the first request after deploy is not the one that pays the cold-cache cost.';

    public function handle(PsgcService $psgcService, ZipCodeService $zipCodeService): int
    {
        if ($this->option('regenerate')) {
            $path = config('locations.providers.ph-address.normalized_path');

            if (! is_string($path) || $path === '') {
                $this->error('locations.providers.ph-address.normalized_path is not configured.');

                return self::FAILURE;
            }

            File::put($path, json_encode($psgcService->buildDataset(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

            \Illuminate\Support\Facades\Cache::store(config('locations.cache_store', 'file'))->forget('locations.dataset.v3');

            $this->info("Regenerated {$path}");
        }

        $psgcService->warm();
        $zipCodeService->warm();

        $this->info('Location caches warmed.');

        return self::SUCCESS;
    }
}
