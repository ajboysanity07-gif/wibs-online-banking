<?php

namespace App\Services\Admin;

use Illuminate\Support\Collection;

class RequestsService
{
    public const UNAVAILABLE_MESSAGE = 'Requests module coming soon.';

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getPreview(int $limit = 5): Collection
    {
        return collect();
    }

    /**
     * @return array{items:\Illuminate\Support\Collection<int, array<string, mixed>>,available:bool,message:string}
     */
    public function getPaginated(string $search, int $perPage): array
    {
        return [
            'items' => collect(),
            'available' => false,
            'message' => self::UNAVAILABLE_MESSAGE,
        ];
    }
}
