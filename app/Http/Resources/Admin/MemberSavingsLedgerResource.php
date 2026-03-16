<?php

namespace App\Http\Resources\Admin;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberSavingsLedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'svnumber' => $this->svnumber,
            'svtype' => $this->svtype,
            'date_in' => $this->formatDateValue($this->date_in),
            'deposit' => $this->castNumber($this->deposit),
            'withdrawal' => $this->castNumber($this->withdrawal),
            'balance' => $this->castNumber($this->balance),
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
