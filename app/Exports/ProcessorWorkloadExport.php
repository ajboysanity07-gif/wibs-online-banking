<?php

namespace App\Exports;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProcessorWorkloadExport extends AbstractReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(AppUser $actor, ?Carbon $from = null, ?Carbon $to = null)
    {
        parent::__construct($actor, $from, $to);
    }

    public function collection(): Collection
    {
        $processorIds = AppUser::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::LOAN_PROCESSOR))
            ->pluck('user_id');

        $requests = LoanRequest::query()
            ->with(['assignedOfficer'])
            ->whereIn('assigned_officer_id', $processorIds)
            ->when($this->from, fn ($q) => $q->where('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('created_at', '<=', $this->to))
            ->get(['assigned_officer_id', 'status', 'approved_at', 'rejected_at', 'declined_at', 'created_at']);

        return $requests
            ->groupBy('assigned_officer_id')
            ->map(function (Collection $group, int $officerId): array {
                $officer = $group->first()?->assignedOfficer;
                $approved = $group->whereIn('status', ['approved'])->count();
                $rejected = $group->whereIn('status', ['rejected'])->count();
                $decided = $group
                    ->filter(fn ($r) => $r->approved_at !== null || $r->rejected_at !== null || $r->declined_at !== null);
                $avgDays = $decided->isNotEmpty()
                    ? round($decided->avg(function ($r): float {
                        $d = $r->approved_at ?? $r->rejected_at ?? $r->declined_at;

                        return (float) Carbon::parse($r->created_at)->diffInDays(Carbon::parse($d));
                    }), 1)
                    : null;

                return [
                    'processor_id' => $officerId,
                    'name' => $officer?->name ?? 'Unknown',
                    'total' => $group->count(),
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'avg_processing_days' => $avgDays,
                ];
            })
            ->values();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Processor', 'Total Assigned', 'Approved', 'Rejected', 'Avg Processing Days',
        ];
    }

    /** @param array $row */
    public function map($row): array
    {
        return [
            $row['name'],
            $row['total'],
            $row['approved'],
            $row['rejected'],
            $row['avg_processing_days'] ?? '',
        ];
    }
}
