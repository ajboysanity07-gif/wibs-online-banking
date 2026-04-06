<?php

namespace App\Services\Admin;

use App\Models\AppUser;
use App\Models\Wmaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class MembersService
{
    /**
     * @return array{
     *     member: \App\Models\AppUser|\App\Models\Wmaster,
     *     memberKey: string,
     *     userId: ?int,
     *     acctno: string,
     *     memberName: string,
     *     registrationStatus: string,
     *     portalStatus: ?string
     * }
     */
    public function resolveAccountContext(string $memberKey): array
    {
        $member = $this->getMemberDetail($memberKey);

        $context = $this->buildAccountContext($member);

        if ($context === null) {
            abort(404);
        }

        return $context;
    }

    /**
     * @return array{
     *     member: \App\Models\AppUser|\App\Models\Wmaster,
     *     memberKey: string,
     *     userId: ?int,
     *     acctno: string,
     *     memberName: string,
     *     registrationStatus: string,
     *     portalStatus: ?string
     * }|null
     */
    public function resolveAccountContextFromMember(AppUser|Wmaster $member): ?array
    {
        return $this->buildAccountContext($member);
    }

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

            $query = $this->memberLookupQuery()
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

        $user = $this->memberLookupQuery()
            ->whereKey($memberKey)
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

        $user = $this->memberLookupQuery()
            ->where('acctno', $acctno)
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
     * @return array{
     *     member: \App\Models\AppUser|\App\Models\Wmaster,
     *     memberKey: string,
     *     userId: ?int,
     *     acctno: string,
     *     memberName: string,
     *     registrationStatus: string,
     *     portalStatus: ?string
     * }|null
     */
    private function buildAccountContext(AppUser|Wmaster $member): ?array
    {
        if ($member instanceof AppUser) {
            $member->loadMissing('userProfile');
        }

        $acctno = $this->resolveAcctno($member);

        if ($acctno === null) {
            return null;
        }

        $userId = $member instanceof AppUser ? $member->user_id : null;
        $memberName = $this->resolveMemberName($member);
        $portalStatus = $member instanceof AppUser
            ? $member->userProfile?->status
            : null;

        return [
            'member' => $member,
            'memberKey' => $this->resolveMemberKey($userId, $acctno),
            'userId' => $userId,
            'acctno' => $acctno,
            'memberName' => $memberName,
            'registrationStatus' => $userId === null ? 'unregistered' : 'registered',
            'portalStatus' => $portalStatus,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function memberRelations(): array
    {
        $relations = ['adminProfile', 'userProfile.reviewedBy'];

        if (Schema::hasTable('wmaster')) {
            $relations[] = 'wmaster';
        }

        return $relations;
    }

    private function memberLookupQuery(): Builder
    {
        return AppUser::query()
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('adminProfile')
                    ->orWhereNotNull('acctno');
            });
    }

    private function resolveAcctno(AppUser|Wmaster $member): ?string
    {
        $acctno = $member->acctno;

        if (! is_string($acctno)) {
            return null;
        }

        $acctno = trim($acctno);

        return $acctno !== '' ? $acctno : null;
    }

    private function resolveMemberName(AppUser|Wmaster $member): string
    {
        if ($member instanceof Wmaster) {
            $name = $member->displayName();

            return $name !== '' ? $name : 'Member';
        }

        $name = null;

        if (Schema::hasTable('wmaster')) {
            $member->loadMissing('wmaster');
            $name = $member->wmaster?->displayName();
        }

        if (! is_string($name) || trim($name) === '') {
            $name = $member->username;
        }

        if (! is_string($name) || trim($name) === '') {
            $name = $member->email;
        }

        if (! is_string($name) || trim($name) === '') {
            $name = $member->acctno;
        }

        return is_string($name) && trim($name) !== '' ? $name : 'Member';
    }

    private function resolveMemberKey(?int $userId, string $acctno): string
    {
        if ($userId !== null) {
            return (string) $userId;
        }

        return 'acct-'.$acctno;
    }
}
