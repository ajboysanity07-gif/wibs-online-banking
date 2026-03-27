<?php

namespace App\Services\Admin;

use App\Models\AppUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class MembersService
{
    public function getPaginated(
        string $search,
        ?string $status,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 50));
        $direction = $sort === 'oldest' ? 'asc' : 'desc';
        $loadWmaster = Schema::hasTable('wmaster');

        $query = AppUser::query()
            ->whereDoesntHave('adminProfile')
            ->with(
                $loadWmaster
                    ? ['wmaster:acctno,fname,mname,lname,bname', 'userProfile']
                    : ['userProfile'],
            );

        if ($status !== null) {
            $query->whereHas('userProfile', function ($builder) use ($status) {
                $builder->where('status', $status);
            });
        }

        if ($search !== '') {
            $searchLike = '%'.addcslashes($search, '%_\\').'%';

            if ($loadWmaster) {
                $query->leftJoin('wmaster', 'wmaster.acctno', '=', 'appusers.acctno')
                    ->select('appusers.*')
                    ->where(function ($builder) use ($searchLike) {
                        $builder->where('appusers.acctno', 'like', $searchLike)
                            ->orWhere('appusers.username', 'like', $searchLike)
                            ->orWhere('appusers.email', 'like', $searchLike)
                            ->orWhere('wmaster.lname', 'like', $searchLike)
                            ->orWhere('wmaster.fname', 'like', $searchLike)
                            ->orWhere('wmaster.mname', 'like', $searchLike)
                            ->orWhere('wmaster.bname', 'like', $searchLike);
                    });
            } else {
                $query->where(function ($builder) use ($searchLike) {
                    $builder->where('appusers.acctno', 'like', $searchLike)
                        ->orWhere('appusers.username', 'like', $searchLike)
                        ->orWhere('appusers.email', 'like', $searchLike);
                });
            }
        }

        return $query
            ->orderBy('appusers.created_at', $direction)
            ->paginate($perPage);
    }

    public function getMemberDetail(AppUser $user): AppUser
    {
        $relations = ['userProfile.reviewedBy'];

        if (Schema::hasTable('wmaster')) {
            $relations[] = 'wmaster:acctno,fname,mname,lname,bname';
        }

        return $user->loadMissing($relations);
    }
}
