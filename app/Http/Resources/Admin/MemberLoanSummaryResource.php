<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberLoanSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'balance' => $this->castNumber(data_get($resource, 'balance')),
            'recommended_payment' => $this->castNullableNumber(data_get($resource, 'recommendedPayment')),
            'next_payment_date' => data_get($resource, 'nextPaymentDate'),
            'last_payment_date' => data_get($resource, 'lastPaymentDate'),
        ];
    }

    private function castNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function castNullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
