<?php

namespace App\Http\Resources\Admin;

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
            'date_in' => $this->formatDateValue($this->date_in),
            'reference_no' => $this->resolveReference(),
            'loan_type' => $this->lntype,
            'principal' => $this->castNumber($this->principal),
            'payment_amount' => $this->castNumber($this->payments),
            'debit' => $this->castNumber($this->debit),
            'credit' => $this->castNumber($this->credit),
            'balance' => $this->castNumber($this->balance),
            'accrued_interest' => $this->castNumber($this->accruedint),
            'status' => $this->lnstatus,
            'remarks' => $this->grouploan,
            'control_no' => $this->controlno,
            'transaction_no' => $this->transno,
        ];
    }

    private function resolveReference(): ?string
    {
        if ($this->mreference !== null && $this->mreference !== '') {
            return (string) $this->mreference;
        }

        if ($this->transno !== null && $this->transno !== '') {
            return (string) $this->transno;
        }

        if ($this->controlno !== null && $this->controlno !== '') {
            return (string) $this->controlno;
        }

        return null;
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
