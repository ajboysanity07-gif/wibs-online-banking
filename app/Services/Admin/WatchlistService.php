<?php

namespace App\Services\Admin;

use Illuminate\Support\Collection;

class WatchlistService
{
    public const UNAVAILABLE_MESSAGE = 'Watchlist signals are not available yet. Connect transactions/savings data to enable eligibility and risk views.';

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getPreview(string $type, int $limit = 5): Collection
    {
        return collect();
    }

    /**
     * @return array{items:\Illuminate\Support\Collection<int, array<string, mixed>>,available:bool,message:string}
     */
    public function getPaginated(string $type, string $search, int $perPage): array
    {
        return [
            'items' => collect(),
            'available' => false,
            'message' => self::UNAVAILABLE_MESSAGE,
        ];
    }
}
