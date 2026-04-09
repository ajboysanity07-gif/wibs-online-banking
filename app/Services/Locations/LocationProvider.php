<?php

namespace App\Services\Locations;

interface LocationProvider
{
    /**
     * @return array{
     *     regions: array<string, string>,
     *     provinces: array<string, string>,
     *     localities: list<array{code: string, name: string, type: string}>
     * }
     */
    public function dataset(): array;
}
