<?php

namespace App\Services\Admin;

use App\Models\AppUser;
use Illuminate\Pagination\LengthAwarePaginator;

class PendingApprovalsService
{
    public function getPaginated(string $search, string $sort, int $perPage): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 50));
        $sort = $sort === 'oldest' ? 'oldest' : 'newest';
        $direction = $sort === 'oldest' ? 'asc' : 'desc';

        $query = AppUser::query()
            ->whereDoesntHave('adminProfile')
            ->whereHas('userProfile', function ($builder) {
                $builder->where('status', 'pending');
            })
            ->with([
                'wmaster:acctno,bname',
                'userProfile',
            ]);

        if ($search !== '') {
            $searchLike = '%'.addcslashes($search, '%_\\').'%';

            $query->leftJoin('wmaster', 'wmaster.acctno', '=', 'appusers.acctno')
                ->select('appusers.*')
                ->where(function ($builder) use ($searchLike) {
                    $builder->where('appusers.acctno', 'like', $searchLike)
                        ->orWhere('appusers.username', 'like', $searchLike)
                        ->orWhere('appusers.email', 'like', $searchLike)
                        ->orWhere('wmaster.bname', 'like', $searchLike);
                });
        }

        return $query
            ->orderBy('appusers.created_at', $direction)
            ->paginate($perPage);
    }
}
