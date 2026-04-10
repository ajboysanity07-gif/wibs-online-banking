<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Log;
use JsonException;

class PhAddressLocationProvider implements LocationProvider
{
    /**
     * @return array{
     *     regions: array<string, string>,
     *     provinces: array<string, string>,
     *     localities: list<array{code: string, name: string, type: string, province_code?: string|null, region_code?: string|null}>
     * }
     */
    public function dataset(): array
    {
        $path = $this->resolveDatasetPath();

        if ($path === null) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            Log::warning('PH address dataset could not be read.', [
                'path' => $path,
            ]);

            return [];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('PH address dataset could not be decoded.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! is_array($payload)) {
            Log::warning('PH address dataset payload is invalid.', [
                'path' => $path,
            ]);

            return [];
        }

        $regions = $this->filterMap($payload['regions'] ?? []);
        $provinces = $this->filterMap($payload['provinces'] ?? []);
        $localities = $this->filterLocalities($payload['localities'] ?? []);

        return [
            'regions' => $regions,
            'provinces' => $provinces,
            'localities' => $localities,
        ];
    }

    private function resolveDatasetPath(): ?string
    {
        $config = config('locations.providers.ph-address', []);
        $path = app()->environment('testing')
            ? ($config['testing_data_path'] ?? null)
            : ($config['data_path'] ?? null);

        if (! is_string($path)) {
            Log::warning('PH address dataset path is not configured.');

            return null;
        }

        $path = trim($path);

        if ($path === '' || ! is_file($path)) {
            Log::warning('PH address dataset path is invalid.', [
                'path' => $path,
            ]);

            return null;
        }

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function filterMap(mixed $map): array
    {
        if (! is_array($map)) {
            return [];
        }

        $filtered = [];

        foreach ($map as $code => $name) {
            if (! is_string($name)) {
                continue;
            }

            $code = trim((string) $code);
            $name = trim($name);

            if ($code === '' || $name === '') {
                continue;
            }

            $filtered[$code] = $name;
        }

        return $filtered;
    }

    /**
     * @return list<array{code: string, name: string, type: string, province_code?: string|null, region_code?: string|null}>
     */
    private function filterLocalities(mixed $localities): array
    {
        if (! is_array($localities)) {
            return [];
        }

        $filtered = [];

        foreach ($localities as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $code = $entry['code'] ?? null;
            $name = $entry['name'] ?? null;
            $type = $entry['type'] ?? null;

            if (
                ! is_string($code)
                || trim($code) === ''
                || ! is_string($name)
                || trim($name) === ''
                || ! is_string($type)
                || trim($type) === ''
            ) {
                continue;
            }

            $filtered[] = [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'province_code' => is_string($entry['province_code'] ?? null)
                    ? $entry['province_code']
                    : null,
                'region_code' => is_string($entry['region_code'] ?? null)
                    ? $entry['region_code']
                    : null,
            ];
        }

        return $filtered;
    }
}
