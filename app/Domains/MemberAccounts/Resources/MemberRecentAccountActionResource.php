<?php

namespace App\Domains\MemberAccounts\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberRecentAccountActionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $source = (string) ($this->source ?? '');
        $number = $this->number ?? null;
        $prefix = $source === 'LOAN' ? 'LN' : 'SV';
        $lnSvNumber = $number === null ? null : $prefix.(string) $number;

        return [
            'acctno' => $this->acctno,
            'ln_sv_number' => $lnSvNumber,
            'date_in' => $this->formatDateValue($this->date_in),
            'transaction_type' => $this->transaction_type,
            'amount' => $this->castNumber($this->amount),
            'movement' => $this->castNumber($this->movement),
            'balance' => $this->castNumber($this->balance),
            'source' => $source !== '' ? $source : null,
            'principal' => $this->castNumber($this->principal),
            'deposit' => $this->castNumber($this->deposit),
            'withdrawal' => $this->castNumber($this->withdrawal),
            'payments' => $this->castNumber($this->payments),
            'debit' => $this->castNumber($this->debit),
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
