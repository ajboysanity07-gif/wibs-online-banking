<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = is_array($this->resource) ? $this->resource : [];

        return [
            'id' => $resource['id'] ?? null,
            'member_name' => $resource['member_name'] ?? null,
            'status' => $resource['status'] ?? null,
            'created_at' => $resource['created_at'] ?? null,
            'summary' => $resource['summary'] ?? null,
        ];
    }
}
