<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PsgcService
{
    private const CACHE_TTL_SECONDS = 86400;

    private const CACHE_DATASET = 'locations.dataset.v1';

    private const CODE_LENGTH = 9;

    private const SMALL_WORDS = [
        'Of',
        'And',
        'The',
        'De',
        'Del',
        'La',
        'Las',
        'Los',
    ];

    public function __construct(private LocationProvider $provider) {}

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
        $provinces = $this->provinces();

        if ($provinces === []) {
            return [
                'available' => false,
                'results' => [],
            ];
        }

        $needle = Str::lower($query);
        $matches = [];

        foreach ($provinces as $entry) {
            $name = $entry['name_lower'];

            if (! str_contains($name, $needle)) {
                continue;
            }

            $score = 2;

            if ($name === $needle) {
                $score = 0;
            } elseif (str_starts_with($name, $needle)) {
                $score = 1;
            }

            $matches[] = [$score, $entry];
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left[0] !== $right[0]) {
                return $left[0] <=> $right[0];
            }

            return strcmp($left[1]['name'], $right[1]['name']);
        });

        $results = [];

        foreach ($matches as [$score, $province]) {
            $results[] = [
                'code' => $province['code'],
                'name' => $province['name'],
                'type' => $province['type'],
                'province' => $province['province'],
                'region' => $province['region'],
                'label' => $province['label'],
                'value' => $province['value'],
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
        $dataset = $this->dataset();

        return $dataset['birthplaces'] ?? [];
    }

    /**
     * @return list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>
     */
    private function provinces(): array
    {
        $dataset = $this->dataset();

        return $dataset['provinces'] ?? [];
    }

    /**
     * @return array{
     *     birthplaces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>,
     *     provinces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>
     * }
     */
    private function dataset(): array
    {
        $cached = Cache::get(self::CACHE_DATASET);

        if (is_array($cached)) {
            return $cached;
        }

        $dataset = $this->buildDataset();

        if ($dataset !== []) {
            Cache::put(self::CACHE_DATASET, $dataset, $this->cacheTtl());
        }

        return $dataset;
    }

    /**
     * @return array{
     *     birthplaces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>,
     *     provinces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>
     * }|array{}
     */
    private function buildDataset(): array
    {
        $rawDataset = $this->provider->dataset();

        if ($rawDataset === []) {
            return [];
        }

        $regions = $this->normalizeMap($rawDataset['regions'] ?? []);
        $provinces = $this->normalizeMap($rawDataset['provinces'] ?? []);
        $birthplaces = [];

        foreach ($rawDataset['localities'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalized = $this->normalizeBirthplace($entry, $provinces, $regions);

            if ($normalized !== null) {
                $birthplaces[] = $normalized;
            }
        }

        $provinceSuggestions = [];

        foreach ($provinces as $code => $name) {
            if (! is_string($code)) {
                continue;
            }

            $provinceSuggestions[] = [
                'code' => $code,
                'name' => $name,
                'type' => 'province',
                'province' => null,
                'region' => null,
                'label' => $name,
                'value' => $name,
                'name_lower' => Str::lower($name),
            ];
        }

        return [
            'birthplaces' => $birthplaces,
            'provinces' => $provinceSuggestions,
        ];
    }

    /**
     * @param  array{code: string, name: string, type: string}  $entry
     * @param  array<string, string>  $provinces
     * @param  array<string, string>  $regions
     * @return array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}|null
     */
    private function normalizeBirthplace(array $entry, array $provinces, array $regions): ?array
    {
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
            return null;
        }

        $normalizedType = $this->normalizeLocalityType($type);

        if ($normalizedType === null) {
            return null;
        }

        $displayName = $this->normalizeName($name);
        $provinceCode = $this->provinceCodeFrom($code);
        $regionCode = $this->regionCodeFrom($code);
        $province = $provinceCode !== null ? ($provinces[$provinceCode] ?? null) : null;
        $region = $regionCode !== null ? ($regions[$regionCode] ?? null) : null;
        $suffix = $province ?? $region;
        $label = $suffix !== null ? sprintf('%s, %s', $displayName, $suffix) : $displayName;

        return [
            'code' => $code,
            'name' => $displayName,
            'type' => $normalizedType,
            'province' => $province,
            'region' => $region,
            'label' => $label,
            'value' => $label,
            'name_lower' => Str::lower($displayName),
        ];
    }

    private function normalizeLocalityType(string $type): ?string
    {
        $normalized = Str::lower(trim($type));

        if ($normalized === 'city') {
            return 'city';
        }

        if ($normalized === 'mun' || $normalized === 'municipality') {
            return 'municipality';
        }

        return null;
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function normalizeMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $code => $name) {
            if (! is_string($name)) {
                continue;
            }

            $code = trim((string) $code);
            $name = $this->normalizeName($name);

            if ($code === '' || $name === '') {
                continue;
            }

            $normalized[$code] = $name;
        }

        return $normalized;
    }

    private function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\\s+/', ' ', $name) ?? '');

        if ($name === '') {
            return '';
        }

        $normalized = Str::title(Str::lower($name));

        foreach (self::SMALL_WORDS as $word) {
            $normalized = preg_replace(
                sprintf('/\\b%s\\b/u', $word),
                Str::lower($word),
                $normalized,
            );
        }

        $normalized = preg_replace_callback(
            '/\\b[ivx]{1,4}\\b/i',
            static fn (array $match): string => Str::upper($match[0]),
            $normalized,
        );

        return $normalized;
    }

    private function provinceCodeFrom(string $code): ?string
    {
        $code = trim($code);

        if (strlen($code) < 4) {
            return null;
        }

        return str_pad(substr($code, 0, 4), self::CODE_LENGTH, '0');
    }

    private function regionCodeFrom(string $code): ?string
    {
        $code = trim($code);

        if (strlen($code) < 2) {
            return null;
        }

        return str_pad(substr($code, 0, 2), self::CODE_LENGTH, '0');
    }

    private function cacheTtl(): int
    {
        $ttl = (int) config('locations.cache_ttl', self::CACHE_TTL_SECONDS);

        if ($ttl <= 0) {
            return self::CACHE_TTL_SECONDS;
        }

        return $ttl;
    }
}
