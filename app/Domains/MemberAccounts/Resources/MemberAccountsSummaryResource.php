<?php

namespace App\Domains\MemberAccounts\Resources;

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
            'currentLoanSecurityBalance' => $this->castNumber(
                data_get($resource, 'currentLoanSecurityBalance'),
            ),
            'currentLoanSecurityTotal' => $this->castNumber(
                data_get($resource, 'currentLoanSecurityTotal'),
            ),
            'lastLoanTransactionDate' => data_get(
                $resource,
                'lastLoanTransactionDate',
            ),
            'lastLoanSecurityTransactionDate' => data_get(
                $resource,
                'lastLoanSecurityTransactionDate',
            ),
            'recentLoans' => MemberLoanResource::collection(
                data_get($resource, 'recentLoans', collect())
            )->resolve(),
            'recentLoanSecurity' => MemberLoanSecurityResource::collection(
                data_get($resource, 'recentLoanSecurity', collect())
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
