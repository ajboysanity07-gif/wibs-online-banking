<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PsgcService
{
    private const BASE_URL = 'https://psgc.cloud/api';

    private const CACHE_TTL_SECONDS = 86400;

    private const CACHE_BIRTHPLACES = 'psgc.birthplaces.v1';

    private const CACHE_REGIONS = 'psgc.regions.v1';

    private const CACHE_PROVINCES = 'psgc.provinces.v1';

    private const CACHE_PROVINCES_LIST = 'psgc.provinces.list.v1';

    private const CACHE_CITIES = 'psgc.cities.v1';

    private const CACHE_MUNICIPALITIES = 'psgc.municipalities.v1';

    /**
     * @return array{available: bool, results: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string}>}
     */
    public function searchBirthplaces(string $query, int $limit = 15): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'available' => true,
                'results' => [],
            ];
        }

        $limit = max(1, min($limit, 20));
        $birthplaces = $this->birthplaces();

        if ($birthplaces === []) {
            return [
                'available' => false,
                'results' => [],
            ];
        }

        $needle = Str::lower($query);
        $matches = [];

        foreach ($birthplaces as $birthplace) {
            $name = $birthplace['name_lower'];

            if (! str_contains($name, $needle)) {
                continue;
            }

            $score = 2;

            if ($name === $needle) {
                $score = 0;
            } elseif (str_starts_with($name, $needle)) {
                $score = 1;
            }

            $matches[] = [$score, $birthplace];
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left[0] !== $right[0]) {
                return $left[0] <=> $right[0];
            }

            return strcmp($left[1]['name'], $right[1]['name']);
        });

        $results = [];

        foreach ($matches as [$score, $birthplace]) {
            $results[] = [
                'code' => $birthplace['code'],
                'name' => $birthplace['name'],
                'type' => $birthplace['type'],
                'province' => $birthplace['province'],
                'region' => $birthplace['region'],
                'label' => $birthplace['label'],
                'value' => $birthplace['value'],
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return [
            'available' => true,
            'results' => $results,
        ];
    }

    /**
     * @return array{available: bool, results: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string}>}
     */
    public function searchProvinces(string $query, int $limit = 15): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'available' => true,
                'results' => [],
            ];
        }

        $limit = max(1, min($limit, 20));
        $provinces = $this->provincesList();

        if ($provinces === []) {
            return [
                'available' => false,
                'results' => [],
            ];
        }

        $needle = Str::lower($query);
        $matches = [];

        foreach ($provinces as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = $entry['name'] ?? null;
            $code = $entry['code'] ?? null;

            if (! is_string($name) || ! is_string($code)) {
                continue;
            }

            $name = trim($name);
            $code = trim($code);

            if ($name === '' || $code === '') {
                continue;
            }

            $nameLower = Str::lower($name);

            if (! str_contains($nameLower, $needle)) {
                continue;
            }

            $score = 2;

            if ($nameLower === $needle) {
                $score = 0;
            } elseif (str_starts_with($nameLower, $needle)) {
                $score = 1;
            }

            $matches[] = [$score, [
                'code' => $code,
                'name' => $name,
                'type' => 'province',
                'province' => null,
                'region' => null,
                'label' => $name,
                'value' => $name,
            ]];
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left[0] !== $right[0]) {
                return $left[0] <=> $right[0];
            }

            return strcmp($left[1]['name'], $right[1]['name']);
        });

        $results = [];

        foreach ($matches as [$score, $province]) {
            $results[] = $province;

            if (count($results) >= $limit) {
                break;
            }
        }

        return [
            'available' => true,
            'results' => $results,
        ];
    }

    /**
     * @return array{available: bool, results: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string}>}
     */
    public function searchCities(
        string $query,
        int $limit = 15,
        ?string $province = null,
    ): array {
        $query = trim($query);

        if ($query === '') {
            return [
                'available' => true,
                'results' => [],
            ];
        }

        $limit = max(1, min($limit, 20));
        $birthplaces = $this->birthplaces();

        if ($birthplaces === []) {
            return [
                'available' => false,
                'results' => [],
            ];
        }

        $needle = Str::lower($query);
        $provinceFilter = trim((string) $province);
        $provinceFilter = $provinceFilter !== '' ? Str::lower($provinceFilter) : '';
        $matches = [];

        foreach ($birthplaces as $birthplace) {
            $name = $birthplace['name_lower'];
            $provinceName = $birthplace['province'] ?? null;

            if ($provinceFilter !== '') {
                if (! is_string($provinceName)) {
                    continue;
                }

                if (Str::lower($provinceName) !== $provinceFilter) {
                    continue;
                }
            }

            if (! str_contains($name, $needle)) {
                continue;
            }

            $score = 2;

            if ($name === $needle) {
                $score = 0;
            } elseif (str_starts_with($name, $needle)) {
                $score = 1;
            }

            $matches[] = [$score, [
                'code' => $birthplace['code'],
                'name' => $birthplace['name'],
                'type' => $birthplace['type'],
                'province' => $birthplace['province'],
                'region' => $birthplace['region'],
                'label' => $birthplace['label'],
                'value' => $birthplace['name'],
            ]];
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left[0] !== $right[0]) {
                return $left[0] <=> $right[0];
            }

            return strcmp($left[1]['name'], $right[1]['name']);
        });

        $results = [];

        foreach ($matches as [$score, $birthplace]) {
            $results[] = $birthplace;

            if (count($results) >= $limit) {
                break;
            }
        }

        return [
            'available' => true,
            'results' => $results,
        ];
    }

    /**
     * @return list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>
     */
    private function birthplaces(): array
    {
        $cached = Cache::get(self::CACHE_BIRTHPLACES);

        if (is_array($cached)) {
            return $cached;
        }

        $regions = $this->lookupMap('regions', self::CACHE_REGIONS);
        $provinces = $this->lookupMap('provinces', self::CACHE_PROVINCES);
        $cities = $this->list('cities', self::CACHE_CITIES);
        $municipalities = $this->list('municipalities', self::CACHE_MUNICIPALITIES);

        $birthplaces = [];

        foreach ($cities as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalized = $this->normalizeBirthplace($entry, 'city', $provinces, $regions);

            if ($normalized !== null) {
                $birthplaces[] = $normalized;
            }
        }

        foreach ($municipalities as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalized = $this->normalizeBirthplace($entry, 'municipality', $provinces, $regions);

            if ($normalized !== null) {
                $birthplaces[] = $normalized;
            }
        }

        if ($birthplaces !== []) {
            Cache::put(self::CACHE_BIRTHPLACES, $birthplaces, self::CACHE_TTL_SECONDS);
        }

        return $birthplaces;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function provincesList(): array
    {
        return $this->list('provinces', self::CACHE_PROVINCES_LIST);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $provinces
     * @param  array<string, string>  $regions
     * @return array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}|null
     */
    private function normalizeBirthplace(array $entry, string $type, array $provinces, array $regions): ?array
    {
        $code = $entry['code'] ?? null;
        $name = $entry['name'] ?? null;

        if (! is_string($code) || trim($code) === '' || ! is_string($name) || trim($name) === '') {
            return null;
        }

        $provinceCode = $this->provinceCodeFrom($code);
        $regionCode = $this->regionCodeFrom($code);
        $province = $provinceCode !== null ? ($provinces[$provinceCode] ?? null) : null;
        $region = $regionCode !== null ? ($regions[$regionCode] ?? null) : null;
        $suffix = $province ?? $region;
        $label = $suffix !== null ? sprintf('%s, %s', $name, $suffix) : $name;

        return [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'province' => $province,
            'region' => $region,
            'label' => $label,
            'value' => $label,
            'name_lower' => Str::lower($name),
        ];
    }

    private function provinceCodeFrom(string $code): ?string
    {
        $code = trim($code);

        if (strlen($code) < 5) {
            return null;
        }

        return substr($code, 0, 5).'00000';
    }

    private function regionCodeFrom(string $code): ?string
    {
        $code = trim($code);

        if (strlen($code) < 2) {
            return null;
        }

        return substr($code, 0, 2).'00000000';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(string $endpoint, string $cacheKey): array
    {
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $data = $this->fetchEndpoint($endpoint);

        if ($data === []) {
            return [];
        }

        Cache::put($cacheKey, $data, self::CACHE_TTL_SECONDS);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function lookupMap(string $endpoint, string $cacheKey): array
    {
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $data = $this->fetchEndpoint($endpoint);

        if ($data === []) {
            return [];
        }

        $mapped = [];

        foreach ($data as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $code = $entry['code'] ?? null;
            $name = $entry['name'] ?? null;

            if (is_string($code) && trim($code) !== '' && is_string($name) && trim($name) !== '') {
                $mapped[$code] = $name;
            }
        }

        if ($mapped !== []) {
            Cache::put($cacheKey, $mapped, self::CACHE_TTL_SECONDS);
        }

        return $mapped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchEndpoint(string $endpoint): array
    {
        $url = sprintf('%s/%s', self::BASE_URL, $endpoint);

        try {
            $response = Http::timeout(10)
                ->retry(2, 200)
                ->acceptJson()
                ->get($url);
        } catch (\Throwable $exception) {
            Log::warning('PSGC API request failed', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! $response->ok()) {
            Log::warning('PSGC API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return [];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            Log::warning('PSGC API response is invalid', [
                'endpoint' => $endpoint,
            ]);

            return [];
        }

        return $payload;
    }
}
