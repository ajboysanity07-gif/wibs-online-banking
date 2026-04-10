<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Log;
use JsonException;
use Symfony\Component\Process\Process;

class PhAddressLocationProvider implements LocationProvider
{
    private const DEFAULT_NODE_BINARY = 'node';

    private const DEFAULT_NODE_TIMEOUT = 20;

    /**
     * @return array{
     *     regions: array<string, string>,
     *     provinces: array<string, string>,
     *     localities: list<array{code: string, name: string, type: string, province_code?: string|null, region_code?: string|null}>
     * }
     */
    public function dataset(): array
    {
        if (app()->environment('testing')) {
            $path = $this->resolveTestingDatasetPath();

            if ($path === null) {
                return [];
            }

            return $this->datasetFromFile($path);
        }

        return $this->datasetFromNode();
    }

    private function datasetFromNode(): array
    {
        $nodeBinary = (string) config(
            'locations.providers.ph-address.node_binary',
            self::DEFAULT_NODE_BINARY,
        );
        $timeout = (int) config(
            'locations.providers.ph-address.node_timeout',
            self::DEFAULT_NODE_TIMEOUT,
        );
        $script = $this->nodeScript();

        $process = new Process([$nodeBinary, '-e', $script], base_path());

        if ($timeout > 0) {
            $process->setTimeout($timeout);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('PH address dataset could not be generated.', [
                'exit_code' => $process->getExitCode(),
                'error' => trim($process->getErrorOutput()),
            ]);

            return [];
        }

        return $this->decodeDataset($process->getOutput(), 'node');
    }

    private function datasetFromFile(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            Log::warning('PH address dataset could not be read.', [
                'path' => $path,
            ]);

            return [];
        }

        return $this->decodeDataset($contents, $path);
    }

    private function resolveTestingDatasetPath(): ?string
    {
        $config = config('locations.providers.ph-address', []);
        $path = $config['testing_data_path'] ?? null;

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
     * @return array{
     *     regions: array<string, string>,
     *     provinces: array<string, string>,
     *     localities: list<array{code: string, name: string, type: string, province_code?: string|null, region_code?: string|null}>
     * }|array{}
     */
    private function decodeDataset(string $contents, string $source): array
    {
        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('PH address dataset could not be decoded.', [
                'source' => $source,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! is_array($payload)) {
            Log::warning('PH address dataset payload is invalid.', [
                'source' => $source,
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

    private function nodeScript(): string
    {
        return <<<'NODE'
const {
  getAllRegions,
  getAllProvinces,
  getMunicipalitiesByProvince,
} = require('@aivangogh/ph-address');

const regions = getAllRegions();
const provinces = getAllProvinces();

const regionMap = {};
const regionCodes = new Set();

for (const region of regions) {
  if (!region || !region.psgcCode) {
    continue;
  }

  const name = region.name || '';
  const designation = (region.designation || '').trim();
  const label = designation ? `${name} (${designation})` : name;

  regionMap[String(region.psgcCode)] = label;
  regionCodes.add(String(region.psgcCode));
}

const provinceMap = {};
const provinceRegions = new Map();

for (const province of provinces) {
  if (!province || !province.psgcCode) {
    continue;
  }

  const code = String(province.psgcCode);

  provinceMap[code] = province.name || '';

  if (province.regionCode) {
    provinceRegions.set(code, String(province.regionCode));
  }
}

const localities = [];
const seen = new Set();

const addLocality = (entry, regionFallback) => {
  if (!entry || !entry.psgcCode) {
    return;
  }

  const code = String(entry.psgcCode);

  if (seen.has(code)) {
    return;
  }

  const name = entry.name || '';
  const type = /\bCity\b/i.test(name) ? 'City' : 'Mun';
  const provinceCode = entry.provinceCode ? String(entry.provinceCode) : null;
  let regionCode = regionFallback ? String(regionFallback) : null;

  if (!regionCode && provinceCode && provinceRegions.has(provinceCode)) {
    regionCode = provinceRegions.get(provinceCode);
  }

  if (!regionCode && provinceCode && regionCodes.has(provinceCode)) {
    regionCode = provinceCode;
  }

  const locality = {
    code,
    name,
    type,
  };

  if (provinceCode) {
    locality.province_code = provinceCode;
  }

  if (regionCode) {
    locality.region_code = regionCode;
  }

  localities.push(locality);
  seen.add(code);
};

for (const province of provinces) {
  if (!province || !province.psgcCode) {
    continue;
  }

  const provinceCode = String(province.psgcCode);
  const provinceLocalities = getMunicipalitiesByProvince(provinceCode) || [];

  for (const entry of provinceLocalities) {
    addLocality(entry, province.regionCode);
  }
}

for (const region of regions) {
  if (!region || !region.psgcCode) {
    continue;
  }

  const regionCode = String(region.psgcCode);
  const regionLocalities = getMunicipalitiesByProvince(regionCode) || [];

  for (const entry of regionLocalities) {
    addLocality(entry, regionCode);
  }
}

console.log(JSON.stringify({
  regions: regionMap,
  provinces: provinceMap,
  localities,
}));
NODE;
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
