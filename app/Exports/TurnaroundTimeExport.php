<?php

namespace App\Exports;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TurnaroundTimeExport extends AbstractReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(AppUser $actor, ?Carbon $from = null, ?Carbon $to = null)
    {
        parent::__construct($actor, $from, $to);
    }

    public function collection(): Collection
    {
        return LoanRequest::query()
            ->where(function ($q): void {
                $q->whereNotNull('approved_at')
                    ->orWhereNotNull('rejected_at')
                    ->orWhereNotNull('declined_at');
            })
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', $this->to))
            ->orderBy('created_at')
            ->get([
                'id', 'reference', 'loan_type_label_snapshot',
                'status', 'created_at', 'submitted_at',
                'approved_at', 'rejected_at', 'declined_at',
            ]);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Reference', 'Loan Type', 'Status', 'Submitted',
            'Decision Date', 'Processing Days',
        ];
    }

    /** @param LoanRequest $row */
    public function map($row): array
    {
        $status = $row->status instanceof LoanRequestStatus
            ? $row->status->value
            : (string) $row->status;

        $decisionDate = $row->approved_at ?? $row->rejected_at ?? $row->declined_at;
        $processingDays = $decisionDate
            ? Carbon::parse($row->created_at)->diffInDays(Carbon::parse($decisionDate))
            : '';

        return [
            $row->reference,
            $row->loan_type_label_snapshot ?? '',
            $status,
            $row->submitted_at ? Carbon::parse($row->submitted_at)->toDateString() : '',
            $decisionDate ? Carbon::parse($decisionDate)->toDateString() : '',
            $processingDays,
        ];
    }
}
