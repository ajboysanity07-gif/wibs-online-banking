<?php

namespace App\Domains\MemberAccounts\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberLoanSecurityResource extends JsonResource
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
            'mortuary' => $this->castNumber($this->mortuary),
            'balance' => $this->castNumber($this->balance),
            'wbalance' => $this->castNumber($this->wbalance),
            'lastmove' => $this->formatDateValue($this->lastmove),
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
