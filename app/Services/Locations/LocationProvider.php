<?php

namespace App\Services\Locations;

interface LocationProvider
{
    /**
     * @return array{
     *     regions: array<string, string>,
     *     provinces: array<string, string>,
     *     localities: list<array{code: string, name: string, type: string, province_code?: string|null, region_code?: string|null}>
     * }
     */
    public function dataset(): array;
}
