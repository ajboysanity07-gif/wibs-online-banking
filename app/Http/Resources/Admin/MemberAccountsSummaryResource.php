<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberAccountsSummaryResource extends JsonResource
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
            'loanBalanceLeft' => $this->castNumber(
                data_get($resource, 'loanBalanceLeft'),
            ),
            'currentPersonalSavings' => $this->castNumber(
                data_get($resource, 'currentPersonalSavings'),
            ),
            'currentSavingsBalance' => $this->castNumber(
                data_get($resource, 'currentSavingsBalance'),
            ),
            'lastLoanTransactionDate' => data_get(
                $resource,
                'lastLoanTransactionDate',
            ),
            'lastSavingsTransactionDate' => data_get(
                $resource,
                'lastSavingsTransactionDate',
            ),
            'recentLoans' => MemberLoanResource::collection(
                data_get($resource, 'recentLoans', collect())
            )->resolve(),
            'recentSavings' => MemberSavingsResource::collection(
                data_get($resource, 'recentSavings', collect())
            )->resolve(),
        ];
    }

    private function castNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }
}
