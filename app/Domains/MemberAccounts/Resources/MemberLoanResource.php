<?php

namespace App\Domains\MemberAccounts\Resources;

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
            'monthlyDue' => $this->castNumber($this->monthlyDue ?? null),
            'dueDate' => $this->formatDateValue($this->dueDate ?? null),
            'penalty' => $this->castNumber($this->penalty ?? null),
            'intRate' => $this->castNumber($this->int_rate ?? null),
            'monthlyRate' => $this->monthlyRate(),
            'termMonths' => $this->castNumber($this->term_mons ?? null),
        ];
    }

    private function castNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * wlnmaster.int_rate is an ANNUAL percent; UI displays the monthly cut.
     */
    private function monthlyRate(): ?float
    {
        $annual = $this->castNumber($this->int_rate ?? null);

        if ($annual === null) {
            return null;
        }

        return round($annual / 12, 2);
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
