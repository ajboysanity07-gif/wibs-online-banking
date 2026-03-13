<?php

namespace App\Services\Admin;

use App\Models\AppUser;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    /**
     * @return array{pendingCount:int,activeCount:int,totalCount:int,requestsCount:?int,lastSync:?string}
     */
    public function getMetrics(): array
    {
        $countsBase = AppUser::query()->whereDoesntHave('adminProfile');

        $pendingCount = (clone $countsBase)
            ->whereHas('userProfile', function ($query) {
                $query->where('status', 'pending');
            })
            ->count();

        $activeCount = (clone $countsBase)
            ->whereHas('userProfile', function ($query) {
                $query->where('status', 'active');
            })
            ->count();

        $totalCount = (clone $countsBase)->count();

        return [
            'pendingCount' => $pendingCount,
            'activeCount' => $activeCount,
            'totalCount' => $totalCount,
            'requestsCount' => null,
            'lastSync' => 'Manual WIBS Desktop processing',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\AppUser>
     */
    public function getPendingApprovalsPreview(int $limit = 5): Collection
    {
        return AppUser::query()
            ->whereDoesntHave('adminProfile')
            ->whereHas('userProfile', function ($query) {
                $query->where('status', 'pending');
            })
            ->with([
                'wmaster:acctno,bname',
                'userProfile',
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'user_id',
                'username',
                'email',
                'acctno',
                'created_at',
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getRecentRequestsPreview(int $limit = 5): Collection
    {
        return collect();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getWatchlistPreview(string $type, int $limit = 5): Collection
    {
        return collect();
    }
}
