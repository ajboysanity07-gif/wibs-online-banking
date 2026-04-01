<?php

namespace App\Services\Admin;

use App\Models\AppUser;
use App\Models\Wmaster;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class MembersService
{
    public function getPaginated(
        string $search,
        ?string $registration,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 50));
        $direction = $sort === 'oldest' ? 'asc' : 'desc';
        $loadWmaster = Schema::hasTable('wmaster');

        if (! $loadWmaster) {
            if ($registration === 'unregistered') {
                return new LengthAwarePaginator([], 0, $perPage, 1);
            }

            $query = AppUser::query()
                ->whereDoesntHave('adminProfile')
                ->with(['userProfile']);

            if ($search !== '') {
                $searchLike = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($builder) use ($searchLike) {
                    $builder->where('appusers.acctno', 'like', $searchLike)
                        ->orWhere('appusers.username', 'like', $searchLike)
                        ->orWhere('appusers.email', 'like', $searchLike);
                });
            }

            return $query
                ->orderBy('appusers.created_at', $direction)
                ->paginate($perPage);
        }

        $query = Wmaster::query()
            ->leftJoin('appusers', 'appusers.acctno', '=', 'wmaster.acctno')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'appusers.user_id')
            ->leftJoin('admin_profiles', 'admin_profiles.user_id', '=', 'appusers.user_id')
            ->whereNull('admin_profiles.user_id')
            ->select([
                'wmaster.*',
                'appusers.user_id',
                'appusers.username',
                'appusers.email',
                'appusers.phoneno',
                'appusers.created_at',
                'user_profiles.status as portal_status',
                'user_profiles.reviewed_at',
                'user_profiles.reviewed_by',
            ]);

        if ($registration === 'registered') {
            $query->whereNotNull('appusers.user_id');
        } elseif ($registration === 'unregistered') {
            $query->whereNull('appusers.user_id');
        }

        if ($search !== '') {
            $searchLike = '%'.addcslashes($search, '%_\\').'%';
            $hasWmasterEmail = Schema::hasColumn('wmaster', 'email_address');

            $query->where(function ($builder) use ($searchLike, $hasWmasterEmail) {
                $builder->where('wmaster.acctno', 'like', $searchLike)
                    ->orWhere('wmaster.lname', 'like', $searchLike)
                    ->orWhere('wmaster.fname', 'like', $searchLike)
                    ->orWhere('wmaster.mname', 'like', $searchLike)
                    ->orWhere('wmaster.bname', 'like', $searchLike)
                    ->orWhere('appusers.username', 'like', $searchLike)
                    ->orWhere('appusers.email', 'like', $searchLike);

                if ($hasWmasterEmail) {
                    $builder->orWhere('wmaster.email_address', 'like', $searchLike);
                }
            });
        }

        if (Schema::hasColumn('wmaster', 'datemem')) {
            $query->orderByRaw(
                sprintf('COALESCE(appusers.created_at, wmaster.datemem) %s', $direction),
            );
        } else {
            $query->orderBy('appusers.created_at', $direction);
        }

        return $query
            ->orderBy('wmaster.acctno', $direction)
            ->paginate($perPage);
    }

    public function getMemberDetail(string $memberKey): AppUser|Wmaster
    {
        $memberKey = trim($memberKey);

        if ($memberKey === '') {
            abort(404);
        }

        if (str_starts_with($memberKey, 'acct-')) {
            $acctno = substr($memberKey, 5);

            return $this->findByAccountNumber($acctno);
        }

        $user = AppUser::query()
            ->whereKey($memberKey)
            ->whereDoesntHave('adminProfile')
            ->with($this->memberRelations())
            ->first();

        if ($user !== null) {
            return $user;
        }

        return $this->findByAccountNumber($memberKey);
    }

    private function findByAccountNumber(string $acctno): AppUser|Wmaster
    {
        $acctno = trim($acctno);

        if ($acctno === '') {
            abort(404);
        }

        $user = AppUser::query()
            ->where('acctno', $acctno)
            ->whereDoesntHave('adminProfile')
            ->with($this->memberRelations())
            ->first();

        if ($user !== null) {
            return $user;
        }

        if (! Schema::hasTable('wmaster')) {
            abort(404);
        }

        return Wmaster::query()
            ->where('acctno', $acctno)
            ->firstOrFail();
    }

    /**
     * @return array<int, string>
     */
    private function memberRelations(): array
    {
        $relations = ['userProfile.reviewedBy'];

        if (Schema::hasTable('wmaster')) {
            $relations[] = 'wmaster';
        }

        return $relations;
    }
}
