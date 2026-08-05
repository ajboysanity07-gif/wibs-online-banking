<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;

class ZipCodeService
{
    private const CACHE_KEY = 'locations.zipcodes.v1';

    /**
     * Force-build and cache the zip map, e.g. from a deploy warm-up step so
     * the first real request doesn't pay the cold-cache cost.
     */
    public function warm(): void
    {
        $this->zipcodes();
    }

    /**
     * Whether $zip is one of the canonical postal codes in the dataset, for
     * any locality. Deliberately reads the real committed dataset directly
     * rather than the environment-aware zipcodes() (which honors
     * testing_data_path) -- see PsgcService::productionDataset() for the
     * same reasoning: value-uniformity validation should reflect real-world
     * coverage even under test.
     */
    public function isKnownZip(?string $zip): bool
    {
        $zip = trim((string) $zip);

        if ($zip === '') {
            return false;
        }

        return in_array($zip, $this->productionZipcodes(), true);
    }

    /**
     * @return array<string, string>
     */
    private function productionZipcodes(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $path = base_path('resources/data/ph-zipcodes.json');

        if (! is_file($path)) {
            return $cached = [];
        }

        $contents = file_get_contents($path);
        $payload = $contents !== false ? json_decode($contents, true) : null;

        return $cached = is_array($payload) ? $payload : [];
    }

    /**
     * Resolve the canonical postal code for a PSGC locality code.
     */
    public function lookup(?string $localityCode): ?string
    {
        $localityCode = trim((string) $localityCode);

        if ($localityCode === '') {
            return null;
        }

        $zipcodes = $this->zipcodes();

        if ($zipcodes === []) {
            return null;
        }

        $zip = $zipcodes[$localityCode] ?? null;

        return is_string($zip) && trim($zip) !== '' ? trim($zip) : null;
    }

    /**
     * @return array<string, string>
     */
    private function zipcodes(): array
    {
        $store = Cache::store(config('locations.cache_store', 'file'));
        $cached = $store->get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $zipcodes = $this->loadZipcodes();

        if ($zipcodes !== []) {
            $store->put(self::CACHE_KEY, $zipcodes, 86400);
        }

        return $zipcodes;
    }

    /**
     * @return array<string, string>
     */
    private function loadZipcodes(): array
    {
        $path = $this->resolveDatasetPath();

        if ($path === null) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            Log::warning('PH zipcodes dataset could not be read.', [
                'path' => $path,
            ]);

            return [];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('PH zipcodes dataset could not be decoded.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! is_array($payload)) {
            Log::warning('PH zipcodes dataset payload is invalid.', [
                'path' => $path,
            ]);

            return [];
        }

        $zipcodes = [];

        foreach ($payload as $code => $zip) {
            if (! is_string($zip) || trim($zip) === '') {
                continue;
            }

            $code = trim((string) $code);

            if ($code === '') {
                continue;
            }

            $zipcodes[$code] = trim($zip);
        }

        return $zipcodes;
    }

    private function resolveDatasetPath(): ?string
    {
        $config = config('locations.providers.ph-zipcodes', []);
        $path = app()->environment('testing')
            ? ($config['testing_data_path'] ?? null)
            : ($config['data_path'] ?? null);

        if (! is_string($path)) {
            Log::warning('PH zipcodes dataset path is not configured.');

            return null;
        }

        $path = trim($path);

        if ($path === '' || ! is_file($path)) {
            Log::warning('PH zipcodes dataset path is invalid.', [
                'path' => $path,
            ]);

            return null;
        }

        return $path;
    }
}
