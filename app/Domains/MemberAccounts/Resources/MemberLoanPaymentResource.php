<?php

namespace App\Domains\MemberAccounts\Resources;

use App\Support\LoanPaymentSplit;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberLoanPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->formatDateValue($this->date_in),
            'lnnumber' => $this->lnnumber === null ? null : (string) $this->lnnumber,
            'lntype' => $this->lntype === null ? null : (string) $this->lntype,
            'amount' => $this->castNumber($this->payments ?? null),
            'principal' => $this->castNumber(LoanPaymentSplit::read($this->resource, 'split_principal', 'principal')),
            'interest' => $this->castNumber(LoanPaymentSplit::read($this->resource, 'split_interest', 'accruedint')),
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
