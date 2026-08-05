<?php

namespace App\Services\Locations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PsgcService
{
    private const CACHE_TTL_SECONDS = 86400;

    private const CACHE_DATASET = 'locations.dataset.v3';

    private const CACHE_BARANGAYS = 'locations.barangays.v1';

    private const CODE_LENGTH = 10;

    private const NCR_REGION_CODE = '1300000000';

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
     * Force-build and cache the dataset, e.g. from a deploy warm-up step so
     * the first real request doesn't pay the cold-cache cost.
     */
    public function warm(): void
    {
        $this->dataset();
        $this->barangays();
    }

    /**
     * Whether $value exactly matches a known city/municipality, either by
     * its bare name (as produced by the address2 city-search autocomplete,
     * e.g. "City of Manila") or its labeled value (as produced by the
     * birthplace autocomplete, e.g. "City of Manila, Metro Manila").
     */
    public function isKnownLocality(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $needle = Str::lower($value);
        $needleBase = $this->cityBaseName($needle);

        foreach ($this->productionDataset()['birthplaces'] as $birthplace) {
            $name = Str::lower($birthplace['name']);

            if ($name === $needle || Str::lower($birthplace['value']) === $needle) {
                return true;
            }

            if ($birthplace['type'] === 'city' && $this->cityBaseName($name) === $needleBase) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduces "City of Cebu", "Cebu City" and "cebu" to the same "cebu" base
     * so city-name variants that differ only in "City of"/"City" phrasing
     * are treated as the same locality.
     */
    private function cityBaseName(string $lowerName): string
    {
        $base = preg_replace('/^city of\s+/', '', $lowerName) ?? $lowerName;
        $base = preg_replace('/\s+city$/', '', $base) ?? $base;

        return trim($base);
    }

    /**
     * Whether $value exactly matches a known province name, as produced by
     * the address3 autocomplete.
     */
    public function isKnownProvince(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $needle = Str::lower($value);

        foreach ($this->productionDataset()['provinces'] as $province) {
            if (Str::lower($province['value']) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * The real PSGC dataset, used by isKnownLocality()/isKnownProvince() for
     * value-uniformity validation. Unlike dataset() (used by the search
     * endpoints), this deliberately ignores the testing_data_path override
     * so validation reflects real-world coverage even under test -- the
     * small search fixtures exist to keep autocomplete-result assertions
     * deterministic, not to constrain what counts as a "real" address.
     *
     * @return array{
     *     birthplaces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>,
     *     provinces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>
     * }
     */
    private function productionDataset(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $path = base_path('resources/data/ph-address-normalized.json');

        if (! is_file($path)) {
            return $cached = ['birthplaces' => [], 'provinces' => []];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return $cached = ['birthplaces' => [], 'provinces' => []];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $cached = ['birthplaces' => [], 'provinces' => []];
        }

        if (! is_array($payload) || ! isset($payload['birthplaces'], $payload['provinces'])) {
            return $cached = ['birthplaces' => [], 'provinces' => []];
        }

        return $cached = $payload;
    }

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
        $tokens = $this->searchTokens($needle);
        $matches = [];

        foreach ($birthplaces as $birthplace) {
            $name = $birthplace['name_lower'];

            if (! $this->matchesAllTokens($name, $tokens)) {
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
    public function searchBarangays(
        string $municipality,
        string $query,
        int $limit = 15,
        ?string $province = null,
    ): array {
        $municipality = trim($municipality);

        if ($municipality === '') {
            return [
                'available' => true,
                'results' => [],
            ];
        }

        $limit = max(1, min($limit, 500));
        $municipalityCode = $this->municipalityCodeForName($municipality, $province);

        if ($municipalityCode === null) {
            return [
                'available' => true,
                'results' => [],
            ];
        }

        $barangays = $this->barangays();

        if ($barangays === []) {
            return [
                'available' => false,
                'results' => [],
            ];
        }

        $candidates = $this->resolveBarangayCandidates($municipalityCode, $barangays);

        if ($candidates === []) {
            return [
                'available' => true,
                'results' => [],
            ];
        }

        $province = $this->provinceForMunicipality($municipalityCode);
        $region = $this->regionForMunicipality($municipalityCode);
        $query = trim($query);

        if ($query === '') {
            return [
                'available' => true,
                'results' => $this->buildBarangayResults(
                    $candidates,
                    $province,
                    $region,
                    $limit,
                ),
            ];
        }

        $needle = Str::lower($query);
        $tokens = $this->searchTokens($needle);
        $matches = [];

        foreach ($candidates as $barangay) {
            $name = Str::lower($barangay['name']);

            if (! $this->matchesAllTokens($name, $tokens)) {
                continue;
            }

            $score = 2;

            if ($name === $needle) {
                $score = 0;
            } elseif (str_starts_with($name, $needle)) {
                $score = 1;
            }

            $matches[] = [$score, $barangay];
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left[0] !== $right[0]) {
                return $left[0] <=> $right[0];
            }

            return strcmp($left[1]['name'], $right[1]['name']);
        });

        return [
            'available' => true,
            'results' => $this->buildBarangayResults(
                array_map(
                    static fn (array $match): array => $match[1],
                    $matches,
                ),
                $province,
                $region,
                $limit,
            ),
        ];
    }

    /**
     * @param  list<array{code: string, name: string}>  $barangays
     * @return list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string}>
     */
    private function buildBarangayResults(
        array $barangays,
        ?string $province,
        ?string $region,
        int $limit,
    ): array {
        $results = [];

        foreach ($barangays as $barangay) {
            $results[] = [
                'code' => $barangay['code'],
                'name' => $barangay['name'],
                'type' => 'barangay',
                'province' => $province,
                'region' => $region,
                'label' => $barangay['name'],
                'value' => $barangay['name'],
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Whether $value is a known barangay of the given municipality (matched by
     * name or PSGC code). Used for value-uniformity validation.
     */
    public function isKnownBarangay(string $municipality, string $value, ?string $province = null): bool
    {
        $municipality = trim($municipality);

        if ($municipality === '') {
            return false;
        }

        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $municipalityCode = $this->municipalityCodeForName($municipality, $province);

        if ($municipalityCode === null) {
            return false;
        }

        $needle = Str::lower($value);
        $candidates = $this->resolveBarangayCandidates($municipalityCode, $this->productionBarangays());

        foreach ($candidates as $barangay) {
            if (Str::lower($barangay['name']) === $needle) {
                return true;
            }
        }

        return false;
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
        $tokens = $this->searchTokens($needle);
        $matches = [];

        foreach ($provinces as $entry) {
            $name = $entry['name_lower'];

            if (! $this->matchesAllTokens($name, $tokens)) {
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
        $provinceFilter = trim((string) $province);

        if ($query === '' && $provinceFilter === '') {
            return [
                'available' => true,
                'results' => [],
            ];
        }

        $limit = max(1, min($limit, 500));
        $birthplaces = $this->birthplaces();

        if ($birthplaces === []) {
            return [
                'available' => false,
                'results' => [],
            ];
        }

        $needle = Str::lower($query);
        $tokens = $this->searchTokens($needle);
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

            if (! $this->matchesAllTokens($name, $tokens)) {
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
     * @return list<string>
     */
    private function searchTokens(string $needle): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($needle)) ?: [],
            static fn (string $token): bool => $token !== '',
        ));
    }

    /**
     * Matches when every token appears somewhere in the name, regardless of
     * order, so queries like "manila city" match "City of Manila".
     *
     * @param  list<string>  $tokens
     */
    private function matchesAllTokens(string $name, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! str_contains($name, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a municipality by its bare name ("City of Cebu", "Carmen"), its
     * labeled value ("City of Cebu, Cebu"), or its 10-digit PSGC code to the
     * municipality PSGC code used as the barangays dataset key.
     */
    private function municipalityCodeForName(string $value, ?string $province = null): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{10}$/', $value) === 1) {
            return $value;
        }

        $needle = Str::lower($value);
        $needleBase = $this->cityBaseName($needle);
        $provinceNeedle = $province !== null ? trim($province) : '';
        $provinceNeedle = $provinceNeedle !== '' ? Str::lower($provinceNeedle) : null;

        // Municipality names aren't unique nationwide (~115 pairs share a
        // name across different provinces, e.g. three "Carmen"s). Without a
        // province to disambiguate we fall back to the first match, same as
        // before -- but prefer an exact province match when one is given.
        $fallbackCode = null;

        foreach ($this->birthplaces() as $birthplace) {
            $name = Str::lower($birthplace['name']);

            $isMatch = $name === $needle
                || Str::lower($birthplace['value']) === $needle
                || ($birthplace['type'] === 'city' && $this->cityBaseName($name) === $needleBase);

            if (! $isMatch) {
                continue;
            }

            if ($provinceNeedle === null) {
                return $birthplace['code'];
            }

            $birthplaceProvince = is_string($birthplace['province'])
                ? Str::lower($birthplace['province'])
                : null;

            if ($birthplaceProvince === $provinceNeedle) {
                return $birthplace['code'];
            }

            $fallbackCode ??= $birthplace['code'];
        }

        return $fallbackCode;
    }

    /**
     * Resolve the barangay list for a municipality code. Handles PSGC's
     * Manila exception: "City of Manila" (the umbrella city-level code) has
     * no direct barangay entries -- its ~900 barangays are split across
     * PSGC codes for Manila's 14 numbered districts (Tondo I/II, Binondo,
     * Quiapo, etc.), which share the city's region+province code prefix.
     * Those district codes are *also* independently selectable as their own
     * "city" in the birthplace/city search (so residents who picked e.g.
     * "Tondo I/II" directly already resolve correctly without this
     * fallback) -- this only kicks in for the small remainder who picked
     * the "City of Manila" umbrella entry itself.
     *
     * Only falls back when the resolved code has zero direct barangay
     * entries, which today is uniquely true for "City of Manila" -- no
     * other municipality/city in the dataset has a bare zero-entry code, so
     * merging by prefix can't currently pull in an unrelated neighboring
     * town's barangays by mistake.
     *
     * @param  array<string, list<array{code: string, name: string}>>  $barangayMap
     * @return list<array{code: string, name: string}>
     */
    private function resolveBarangayCandidates(string $municipalityCode, array $barangayMap): array
    {
        if (($barangayMap[$municipalityCode] ?? []) !== []) {
            return $barangayMap[$municipalityCode];
        }

        $prefix = substr($municipalityCode, 0, 5);
        $merged = [];

        foreach ($barangayMap as $code => $list) {
            $code = (string) $code;

            if ($code === $municipalityCode || ! str_starts_with($code, $prefix)) {
                continue;
            }

            array_push($merged, ...$list);
        }

        return $merged;
    }

    private function provinceForMunicipality(string $municipalityCode): ?string
    {
        foreach ($this->birthplaces() as $birthplace) {
            if ($birthplace['code'] === $municipalityCode) {
                return $birthplace['province'];
            }
        }

        return null;
    }

    private function regionForMunicipality(string $municipalityCode): ?string
    {
        foreach ($this->birthplaces() as $birthplace) {
            if ($birthplace['code'] === $municipalityCode) {
                return $birthplace['region'];
            }
        }

        return null;
    }

    /**
     * @return array<string, list<array{code: string, name: string}>>
     */
    private function barangays(): array
    {
        $store = $this->cache();
        $cached = $store->get(self::CACHE_BARANGAYS);

        if (is_array($cached)) {
            return $cached;
        }

        $path = $this->resolveBarangayPath();

        if ($path === null) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $barangays = [];

        if (is_array($payload)) {
            foreach ($payload as $code => $list) {
                if ((! is_string($code) && ! is_int($code)) || ! is_array($list)) {
                    continue;
                }

                $code = (string) $code;
                $filtered = [];

                foreach ($list as $entry) {
                    if (
                        ! is_array($entry)
                        || ! is_string($entry['code'] ?? null)
                        || ! is_string($entry['name'] ?? null)
                        || trim($entry['name']) === ''
                    ) {
                        continue;
                    }

                    $filtered[] = [
                        'code' => $entry['code'],
                        'name' => trim($entry['name']),
                    ];
                }

                if ($filtered !== []) {
                    $barangays[$code] = $filtered;
                }
            }
        }

        if ($barangays !== []) {
            $store->put(self::CACHE_BARANGAYS, $barangays, $this->cacheTtl());
        }

        return $barangays;
    }

    /**
     * The real barangays dataset, used by isKnownBarangay() for
     * value-uniformity validation. Deliberately ignores the testing_data_path
     * override -- see productionDataset() for the same reasoning.
     *
     * @return array<string, list<array{code: string, name: string}>>
     */
    private function productionBarangays(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $path = base_path('resources/data/ph-barangays.json');

        if (! is_file($path)) {
            return $cached = [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return $cached = [];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $cached = [];
        }

        return $cached = is_array($payload) ? $payload : [];
    }

    private function resolveBarangayPath(): ?string
    {
        $config = config('locations.providers.ph-barangays', []);

        $path = app()->environment('testing')
            ? ($config['testing_data_path'] ?? null)
            : ($config['data_path'] ?? null);

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        return $path;
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
        $cached = $this->cache()->get(self::CACHE_DATASET);

        if (is_array($cached)) {
            return $cached;
        }

        $dataset = $this->loadPrecomputedDataset() ?? $this->buildDataset();

        if ($dataset !== []) {
            $this->cache()->put(self::CACHE_DATASET, $dataset, $this->cacheTtl());
        }

        return $dataset;
    }

    /**
     * Load the dataset precomputed at build/deploy time (see the
     * `locations:warm --regenerate` command), skipping the runtime regex
     * normalization in buildDataset() entirely. Falls back to null when the
     * precomputed file hasn't been generated (e.g. local dev).
     *
     * @return array{
     *     birthplaces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>,
     *     provinces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>
     * }|null
     */
    private function loadPrecomputedDataset(): ?array
    {
        // Tests point the raw provider at small fixtures via
        // testing_data_path; the precomputed file is always built from the
        // real dataset, so it must be skipped in testing or fixture-driven
        // tests would see production data instead.
        if (app()->environment('testing')) {
            return null;
        }

        $path = config('locations.providers.ph-address.normalized_path');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['birthplaces'], $payload['provinces'])) {
            return null;
        }

        return $payload;
    }

    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('locations.cache_store', 'file'));
    }

    /**
     * @return array{
     *     birthplaces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>,
     *     provinces: list<array{code: string, name: string, type: string, province: ?string, region: ?string, label: string, value: string, name_lower: string}>
     * }|array{}
     */
    public function buildDataset(): array
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

        // Metro Manila (NCR) has no constituent provinces in PSGC -- its
        // cities' `province_code` is null -- but forms need a selectable
        // "province" value for them, so the region itself is offered as one.
        foreach ($regions as $code => $name) {
            $code = (string) $code;

            if ($code === '') {
                continue;
            }

            $displayName = $code === self::NCR_REGION_CODE ? 'Metro Manila' : $name;

            $provinceSuggestions[] = [
                'code' => $code,
                'name' => $displayName,
                'type' => 'province',
                'province' => null,
                'region' => null,
                'label' => $displayName,
                'value' => $displayName,
                'name_lower' => Str::lower($displayName),
            ];
        }

        foreach ($provinces as $code => $name) {
            $code = (string) $code;

            if ($code === '') {
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
     * @param  array{code: string, name: string, type: string, province_code?: string|null, region_code?: string|null}  $entry
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

        if ($normalizedType === 'city') {
            $displayName = $this->normalizeCityName($displayName);
        }
        $provinceCode = $this->provinceCodeFrom($code);
        $regionCode = $this->regionCodeFrom($code);

        if (is_string($entry['province_code'] ?? null) && trim($entry['province_code']) !== '') {
            $provinceCode = $entry['province_code'];
        }

        if (is_string($entry['region_code'] ?? null) && trim($entry['region_code']) !== '') {
            $regionCode = $entry['region_code'];
        }
        $province = $provinceCode !== null ? ($provinces[$provinceCode] ?? null) : null;
        $region = $regionCode !== null ? ($regions[$regionCode] ?? null) : null;

        // NCR has no constituent provinces, so its cities' `province` would
        // otherwise be null; fall back to the common "Metro Manila" name so
        // address3 autofill and validation have a selectable value.
        if ($province === null && $regionCode === self::NCR_REGION_CODE) {
            $province = 'Metro Manila';
        }

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

        $acronyms = [];

        if (preg_match_all('/\\(([A-Z0-9-]{2,})\\)/', $name, $matches)) {
            $acronyms = $matches[1];
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

        foreach ($acronyms as $acronym) {
            $normalized = preg_replace(
                sprintf('/\\b%s\\b/iu', preg_quote($acronym, '/')),
                $acronym,
                $normalized,
            );
        }

        return $normalized;
    }

    private function normalizeCityName(string $name): string
    {
        if (Str::startsWith(Str::lower($name), 'city of ')) {
            return $name;
        }

        if (preg_match('/\\s+city$/i', $name) === 1) {
            $baseName = trim(preg_replace('/\\s+city$/i', '', $name) ?? '');

            if ($baseName !== '') {
                return sprintf('City of %s', $baseName);
            }
        }

        return $name;
    }

    private function provinceCodeFrom(string $code): ?string
    {
        $code = trim($code);

        if (strlen($code) < 5) {
            return null;
        }

        return str_pad(substr($code, 0, 5), self::CODE_LENGTH, '0');
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
