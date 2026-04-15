<?php

namespace App\Http\Resources\Spa;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     data: array<string, mixed>,
     *     read_at: string|null,
     *     created_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'data' => is_array($this->resource->data) ? $this->resource->data : [],
            'read_at' => $this->formatDateValue($this->resource->read_at),
            'created_at' => $this->formatDateValue($this->resource->created_at),
        ];
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }
}
