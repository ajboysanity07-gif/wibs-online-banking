<?php

namespace App\Http\Resources\Admin;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberLoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $initial = $this->initial ?? $this->principal;

        return [
            'lnnumber' => $this->lnnumber,
            'lntype' => $this->lntype,
            'principal' => $this->castNumber($this->principal),
            'balance' => $this->castNumber($this->balance),
            'lastmove' => $this->formatDateValue($this->lastmove),
            'initial' => $this->castNumber($initial),
        ];
    }

    private function castNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
