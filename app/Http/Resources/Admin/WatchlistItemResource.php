<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WatchlistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = is_array($this->resource) ? $this->resource : [];

        return [
            'user_id' => $resource['user_id'] ?? null,
            'member_name' => $resource['member_name'] ?? null,
            'username' => $resource['username'] ?? null,
            'acctno' => $resource['acctno'] ?? null,
            'last_activity_at' => $resource['last_activity_at'] ?? null,
            'savings_balance' => $resource['savings_balance'] ?? null,
            'badge' => $resource['badge'] ?? null,
        ];
    }
}
