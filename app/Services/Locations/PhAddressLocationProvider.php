<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PhAddressLocationProvider implements LocationProvider
{
    /**
     * @return array{
     *     regions: array<string, string>,
     *     provinces: array<string, string>,
     *     localities: list<array{code: string, name: string, type: string}>
     * }
     */
    public function dataset(): array
    {
        $path = $this->resolveDatasetPath();

        if ($path === null) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            Log::warning('PH address dataset could not be opened.', [
                'path' => $path,
            ]);

            return [];
        }

        $regions = [];
        $provinces = [];
        $localities = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }

            $code = trim((string) $row[0]);
            $name = trim((string) $row[1]);
            $type = trim((string) $row[2]);

            if ($code === '' || $name === '' || $type === '') {
                continue;
            }

            $type = Str::upper($type);

            if ($type === 'REG') {
                $regions[$code] = $name;

                continue;
            }

            if ($type === 'PROV' || $type === 'DIST') {
                $provinces[$code] = $name;

                continue;
            }

            if ($type === 'CITY' || $type === 'MUN') {
                $localities[] = [
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                ];
            }
        }

        fclose($handle);

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
}
