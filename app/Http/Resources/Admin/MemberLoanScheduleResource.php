<?php

namespace App\Http\Resources\Admin;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberLoanScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'lnnumber' => $this->lnnumber,
            'date_pay' => $this->formatDateValue($this->Date_pay),
            'amortization' => $this->castNumber($this->Amortization),
            'interest' => $this->castNumber($this->Interest),
            'balance' => $this->castNumber($this->Balance),
            'control_no' => $this->controlno,
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
