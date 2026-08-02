<?php

namespace App\Services\Reports;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportMetricsService
{
    /**
     * @return array{
     *     pending_count:int,
     *     approved_count:int,
     *     rejected_count:int,
     *     approval_rate:float,
     *     average_processing_days:float|null,
     *     portfolio_total:float,
     *     status_breakdown:array<string,int>,
     *     daily_trend:array<string,int>
     * }
     */
    public function dashboardMetrics(AppUser $actor, ?Carbon $from, ?Carbon $to): array
    {
        $base = $this->baseQuery($actor);

        $inRange = (clone $base)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $pendingCount = (clone $inRange)
            ->whereIn('status', $this->pendingStatuses())
            ->count();

        $approvedCount = (clone $inRange)
            ->where('status', LoanRequestStatus::Approved->value)
            ->count();

        $rejectedCount = (clone $inRange)
            ->where('status', LoanRequestStatus::Rejected->value)
            ->count();

        $decidedCount = $approvedCount + $rejectedCount;
        $approvalRate = $decidedCount > 0
            ? round($approvedCount / $decidedCount * 100, 1)
            : 0.0;

        $averageProcessingDays = $this->averageProcessingDays(clone $inRange);

        $portfolioTotal = (float) (clone $inRange)
            ->where('status', LoanRequestStatus::Approved->value)
            ->whereNotNull('approved_amount')
            ->sum('approved_amount');

        $statusBreakdown = (clone $inRange)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->mapWithKeys(fn ($count, $status) => [$this->statusValue($status) => (int) $count])
            ->all();

        $dailyTrend = $this->dailyTrend($actor, $from, $to);

        return [
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'approval_rate' => $approvalRate,
            'average_processing_days' => $averageProcessingDays,
            'portfolio_total' => $portfolioTotal,
            'status_breakdown' => $statusBreakdown,
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * @return array<string,int>
     */
    public function applicationVolume(?Carbon $from, ?Carbon $to): array
    {
        return LoanRequest::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at')
            ->get(['created_at'])
            ->groupBy(fn ($r) => Carbon::parse($r->created_at)->format('Y-m-d'))
            ->map->count()
            ->all();
    }

    /**
     * @return list<array{processor_id:int,name:string,assigned:int,approved:int,rejected:int,avg_days:float|null}>
     */
    public function staffPerformance(?Carbon $from, ?Carbon $to): array
    {
        $baseQuery = fn () => LoanRequest::query()
            ->whereNotNull('assigned_officer_id')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $assignedCounts = $baseQuery()
            ->selectRaw('assigned_officer_id, count(*) as aggregate')
            ->groupBy('assigned_officer_id')
            ->pluck('aggregate', 'assigned_officer_id');

        $statusCounts = $baseQuery()
            ->selectRaw('assigned_officer_id, status, count(*) as aggregate')
            ->groupBy('assigned_officer_id', 'status')
            ->get()
            ->groupBy('assigned_officer_id');

        $officers = AppUser::query()
            ->whereIn('user_id', $assignedCounts->keys())
            ->get(['user_id', 'username', 'email'])
            ->keyBy('user_id');

        $decidedRows = $baseQuery()
            ->where(function ($q): void {
                $q->whereNotNull('approved_at')
                    ->orWhereNotNull('rejected_at')
                    ->orWhereNotNull('declined_at');
            })
            ->get(['assigned_officer_id', 'created_at', 'approved_at', 'rejected_at', 'declined_at'])
            ->groupBy('assigned_officer_id');

        return $assignedCounts
            ->map(function ($assigned, $officerId) use ($statusCounts, $officers, $decidedRows) {
                $officerId = (int) $officerId;
                $statuses = $statusCounts->get($officerId, collect())
                    ->mapWithKeys(fn ($row) => [$this->statusValue($row->status) => (int) $row->aggregate]);

                return [
                    'processor_id' => $officerId,
                    'name' => $officers->get($officerId)?->name ?? 'Unknown',
                    'assigned' => (int) $assigned,
                    'approved' => (int) ($statuses[LoanRequestStatus::Approved->value] ?? 0),
                    'rejected' => (int) ($statuses[LoanRequestStatus::Rejected->value] ?? 0),
                    'avg_days' => $this->averageProcessingDaysFromCollection(
                        $decidedRows->get($officerId, collect()),
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     queue_count:int,
     *     aging_count:int,
     *     aging_threshold_days:int,
     *     items:list<array{id:int,reference:string,status:string,created_at:string,business_days_in_queue:int,is_aging:bool}>
     * }
     */
    public function processorQueue(AppUser $processor): array
    {
        $threshold = (int) config('loan_workflow.report_aging_threshold_days', 3);

        $requests = LoanRequest::query()
            ->where('assigned_officer_id', $processor->user_id)
            ->whereIn('status', $this->pendingStatuses())
            ->orderBy('created_at')
            ->get(['id', 'reference', 'status', 'created_at']);

        $items = $requests->map(function (LoanRequest $r) use ($threshold): array {
            $businessDays = $this->businessDaysSince(Carbon::parse($r->created_at));
            $isAging = $businessDays >= $threshold;

            return [
                'id' => (int) $r->id,
                'reference' => (string) $r->reference,
                'status' => $this->statusValue($r->status),
                'created_at' => Carbon::parse($r->created_at)->toDateTimeString(),
                'business_days_in_queue' => $businessDays,
                'is_aging' => $isAging,
            ];
        })->all();

        return [
            'queue_count' => count($items),
            'aging_count' => count(array_filter($items, fn ($i) => $i['is_aging'])),
            'aging_threshold_days' => $threshold,
            'items' => array_values($items),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<LoanRequest>
     */
    private function baseQuery(AppUser $actor): \Illuminate\Database\Eloquent\Builder
    {
        $query = LoanRequest::query();

        if (
            $actor->hasRole(Role::LOAN_PROCESSOR)
            && ! $actor->hasRole(Role::SUPERADMIN)
            && ! $actor->hasRole(Role::LOAN_MANAGER)
        ) {
            $query->where('assigned_officer_id', $actor->user_id);
        }

        return $query;
    }

    /**
     * @return array<string,int>
     */
    private function dailyTrend(AppUser $actor, ?Carbon $from, ?Carbon $to): array
    {
        $trendFrom = $from ?? Carbon::today()->subDays(29);
        $trendTo = $to ?? Carbon::today();

        return $this->baseQuery($actor)
            ->where('created_at', '>=', $trendFrom->startOfDay())
            ->where('created_at', '<=', $trendTo->endOfDay())
            ->orderBy('created_at')
            ->get(['created_at'])
            ->groupBy(fn ($r) => Carbon::parse($r->created_at)->format('Y-m-d'))
            ->map->count()
            ->all();
    }

    private function averageProcessingDays(\Illuminate\Database\Eloquent\Builder $query): ?float
    {
        $rows = $query
            ->where(function ($q): void {
                $q->whereNotNull('approved_at')
                    ->orWhereNotNull('rejected_at')
                    ->orWhereNotNull('declined_at');
            })
            ->get(['created_at', 'approved_at', 'rejected_at', 'declined_at']);

        return $this->averageProcessingDaysFromCollection($rows);
    }

    private function averageProcessingDaysFromCollection(Collection $rows): ?float
    {
        $days = $rows
            ->map(function ($r): ?float {
                $decidedAt = $r->approved_at ?? $r->rejected_at ?? $r->declined_at;
                if ($decidedAt === null) {
                    return null;
                }

                return (float) Carbon::parse($r->created_at)->diffInDays(Carbon::parse($decidedAt));
            })
            ->filter(fn ($v) => $v !== null);

        if ($days->isEmpty()) {
            return null;
        }

        return round((float) $days->avg(), 1);
    }

    private function businessDaysSince(Carbon $since): int
    {
        $days = 0;
        $cursor = $since->copy()->startOfDay();
        $today = Carbon::today();

        while ($cursor->lt($today)) {
            if (! $cursor->isWeekend()) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof LoanRequestStatus
            ? $status->value
            : (string) $status;
    }

    /**
     * @return list<string>
     */
    private function pendingStatuses(): array
    {
        return [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::NeedsRevision->value,
            LoanRequestStatus::AwaitingMemberInformation->value,
            LoanRequestStatus::RecommendedForApproval->value,
            LoanRequestStatus::AwaitingMemberAcceptance->value,
        ];
    }
}
