<?php

namespace App\Services\Admin\MemberLoans\Exports;

use App\Models\Wlnled;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LoanPaymentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private Collection $payments,
    ) {}

    public function collection(): Collection
    {
        return $this->payments;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Transaction Date',
            'Reference No',
            'Loan Type',
            'Principal',
            'Payment Amount',
            'Debit',
            'Credit',
            'Balance',
            'Accrued Interest',
            'Status',
            'Remarks',
            'Control No',
            'Transaction No',
        ];
    }

    /**
     * @param  \App\Models\Wlnled  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $date = $row->date_in;

        return [
            $this->formatDateValue($date),
            $this->resolveReference($row),
            $row->lntype,
            $this->castNumber($row->principal),
            $this->castNumber($row->payments),
            $this->castNumber($row->debit),
            $this->castNumber($row->credit),
            $this->castNumber($row->balance),
            $this->castNumber($row->accruedint),
            $row->lnstatus,
            $row->grouploan,
            $row->controlno,
            $row->transno,
        ];
    }

    private function resolveReference(Wlnled $row): ?string
    {
        if ($row->mreference !== null && $row->mreference !== '') {
            return (string) $row->mreference;
        }

        if ($row->transno !== null && $row->transno !== '') {
            return (string) $row->transno;
        }

        if ($row->controlno !== null && $row->controlno !== '') {
            return (string) $row->controlno;
        }

        return null;
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function castNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
