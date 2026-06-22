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

class RejectionReasonsExport extends AbstractReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(AppUser $actor, ?Carbon $from = null, ?Carbon $to = null)
    {
        parent::__construct($actor, $from, $to);
    }

    public function collection(): Collection
    {
        return LoanRequest::query()
            ->with(['user', 'rejectedBy'])
            ->whereIn('status', [
                LoanRequestStatus::Rejected->value,
                LoanRequestStatus::Declined->value,
            ])
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', $this->to))
            ->orderBy('rejected_at')
            ->get([
                'id', 'reference', 'user_id', 'loan_type_label_snapshot',
                'requested_amount', 'status', 'rejection_reason', 'rejected_at',
                'rejected_by', 'declined_at', 'decline_reason',
            ]);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Reference', 'Applicant', 'Loan Type', 'Requested Amount',
            'Outcome', 'Reason', 'Decision Date', 'Decided By',
        ];
    }

    /** @param LoanRequest $row */
    public function map($row): array
    {
        $status = $row->status instanceof LoanRequestStatus
            ? $row->status->value
            : (string) $row->status;

        $isRejected = $status === LoanRequestStatus::Rejected->value;
        $reason = $isRejected ? $row->rejection_reason : $row->decline_reason;
        $decisionDate = $isRejected ? $row->rejected_at : $row->declined_at;
        $decidedBy = $row->rejectedBy?->name ?? '';

        return [
            $row->reference,
            $row->user?->name ?? '',
            $row->loan_type_label_snapshot ?? '',
            $row->requested_amount,
            $status,
            $reason ?? '',
            $decisionDate ? Carbon::parse($decisionDate)->toDateString() : '',
            $decidedBy,
        ];
    }
}
