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

class MonthlyApplicationsExport extends AbstractReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(AppUser $actor, ?Carbon $from = null, ?Carbon $to = null)
    {
        parent::__construct($actor, $from, $to);
    }

    public function collection(): Collection
    {
        return LoanRequest::query()
            ->with(['user'])
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', $this->to))
            ->orderBy('created_at')
            ->get([
                'id', 'reference', 'user_id', 'loan_type_label_snapshot',
                'requested_amount', 'status', 'created_at', 'submitted_at',
                'approved_amount', 'approved_at', 'rejected_at',
            ]);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Reference', 'Applicant', 'Loan Type', 'Requested Amount',
            'Status', 'Submitted', 'Approved Amount', 'Decision Date',
        ];
    }

    /** @param LoanRequest $row */
    public function map($row): array
    {
        $status = $row->status instanceof LoanRequestStatus
            ? $row->status->value
            : (string) $row->status;

        $decisionDate = $row->approved_at ?? $row->rejected_at ?? '';

        return [
            $row->reference,
            $row->user?->name ?? '',
            $row->loan_type_label_snapshot ?? '',
            $row->requested_amount,
            $status,
            $row->submitted_at ? Carbon::parse($row->submitted_at)->toDateString() : '',
            $row->approved_amount ?? '',
            $decisionDate ? Carbon::parse($decisionDate)->toDateString() : '',
        ];
    }
}
