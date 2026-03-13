<?php

namespace App\Repositories\Admin;

use App\Models\Wlnmaster;
use App\Models\Wsvmaster;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @note No active/inactive flag was found for WIBS tables, so queries include all rows per acctno.
 */
class MemberAccountsRepository
{
    /**
     * @return array{
     *     loanBalanceLeft: float,
     *     currentPersonalSavings: float,
     *     currentSavingsBalance: float,
     *     lastLoanTransactionDate: ?string,
     *     lastSavingsTransactionDate: ?string,
     *     recentLoans: \Illuminate\Support\Collection<int, mixed>,
     *     recentSavings: \Illuminate\Support\Collection<int, mixed>
     * }
     */
    public function getSummary(string $acctno, int $recentLimit = 5): array
    {
        $loanBalanceLeft = $this->hasTable('wlnmaster')
            ? (float) Wlnmaster::query()->where('acctno', $acctno)->sum('balance')
            : 0.0;

        [$currentPersonalSavings, $currentSavingsBalance] = $this->getSavingsTotals($acctno);

        return [
            'loanBalanceLeft' => $loanBalanceLeft,
            'currentPersonalSavings' => $currentPersonalSavings,
            'currentSavingsBalance' => $currentSavingsBalance,
            'lastLoanTransactionDate' => $this->getLastLoanTransactionDate($acctno),
            'lastSavingsTransactionDate' => $this->getLastSavingsTransactionDate($acctno),
            'recentLoans' => $this->getRecentLoans($acctno, $recentLimit),
            'recentSavings' => $this->getRecentSavings($acctno, $recentLimit),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     *
     * @note If wlnmaster.initial is missing, principal is selected as initial.
     */
    public function getRecentLoans(string $acctno, int $limit = 5): Collection
    {
        if (! $this->hasTable('wlnmaster')) {
            return collect();
        }

        $orderBy = $this->resolveLoanOrderColumn();
        $select = [
            'lnnumber',
            'lntype',
            'principal',
            'balance',
            'lastmove',
        ];

        if ($this->hasColumn('wlnmaster', 'initial')) {
            $select[] = 'initial';
        } else {
            $select[] = DB::raw('principal as initial');
        }

        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->select($select)
            ->orderByDesc($orderBy)
            ->limit($limit)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function getRecentSavings(string $acctno, int $limit = 5): Collection
    {
        if (! $this->hasTable('wsvmaster')) {
            return collect();
        }

        $orderBy = $this->hasColumn('wsvmaster', 'lastmove') ? 'lastmove' : 'svnumber';

        return Wsvmaster::query()
            ->where('acctno', $acctno)
            ->select([
                'svnumber',
                'svtype',
                'mortuary',
                'balance',
                'wbalance',
                'lastmove',
            ])
            ->orderByDesc($orderBy)
            ->limit($limit)
            ->get();
    }

    public function getPaginatedLoans(
        string $acctno,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        if (! $this->hasTable('wlnmaster')) {
            return $this->emptyPaginator($perPage, $page);
        }

        $orderBy = $this->resolveLoanOrderColumn();
        $select = [
            'lnnumber',
            'lntype',
            'principal',
            'balance',
            'lastmove',
        ];

        if ($this->hasColumn('wlnmaster', 'initial')) {
            $select[] = 'initial';
        } else {
            $select[] = DB::raw('principal as initial');
        }

        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->select($select)
            ->orderByDesc($orderBy)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getPaginatedSavings(
        string $acctno,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        if (! $this->hasTable('wsvmaster')) {
            return $this->emptyPaginator($perPage, $page);
        }

        $orderBy = $this->hasColumn('wsvmaster', 'lastmove') ? 'lastmove' : 'svnumber';

        return Wsvmaster::query()
            ->where('acctno', $acctno)
            ->select([
                'svnumber',
                'svtype',
                'mortuary',
                'balance',
                'wbalance',
                'lastmove',
            ])
            ->orderByDesc($orderBy)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getPaginatedRecentActions(
        string $acctno,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $hasLoans = $this->hasTable('wlnled');
        $hasSavings = $this->hasTable('wsavled');

        if (! $hasLoans && ! $hasSavings) {
            return $this->emptyPaginator($perPage, $page);
        }

        $loanQuery = $hasLoans
            ? DB::table('wlnled')
                ->select([
                    'acctno',
                    'lnnumber as number',
                    'date_in',
                    'lntype as transaction_type',
                    'principal as amount',
                    'payments as movement',
                    'balance',
                    DB::raw("'LOAN' as source"),
                    'principal',
                    DB::raw('null as deposit'),
                    DB::raw('null as withdrawal'),
                    'payments',
                    'debit',
                ])
                ->where('acctno', $acctno)
                ->where(function ($builder) {
                    $builder->where('principal', '!=', 0)
                        ->orWhere('payments', '!=', 0);
                })
            : null;

        $savingsQuery = $hasSavings
            ? DB::table('wsavled')
                ->select([
                    'acctno',
                    'svnumber as number',
                    'date_in',
                    'svtype as transaction_type',
                    'deposit as amount',
                    'withdrawal as movement',
                    'balance',
                    DB::raw("'SAV' as source"),
                    DB::raw('null as principal'),
                    'deposit',
                    'withdrawal',
                    DB::raw('null as payments'),
                    DB::raw('null as debit'),
                ])
                ->where('acctno', $acctno)
                ->where(function ($builder) {
                    $builder->where('withdrawal', '!=', 0)
                        ->orWhere('deposit', '!=', 0);
                })
            : null;

        if ($loanQuery && $savingsQuery) {
            $union = $loanQuery->unionAll($savingsQuery);
        } elseif ($loanQuery) {
            $union = $loanQuery;
        } else {
            $union = $savingsQuery;
        }

        $baseQuery = DB::query()
            ->fromSub($union, 'account_actions')
            ->orderByDesc('date_in');

        $total = (clone $baseQuery)->count();
        $items = $baseQuery->forPage($page, $perPage)->get();

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    private function resolveLoanOrderColumn(): string
    {
        if ($this->hasColumn('wlnmaster', 'lastmove')) {
            return 'lastmove';
        }

        if ($this->hasColumn('wlnmaster', 'dateopen')) {
            return 'dateopen';
        }

        return 'lnnumber';
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function getSavingsTotals(string $acctno): array
    {
        if (! $this->hasTable('wsvmaster')) {
            return [0.0, 0.0];
        }

        $totals = Wsvmaster::query()
            ->where('acctno', $acctno)
            ->selectRaw('COALESCE(SUM(balance), 0) as balance_total, COALESCE(SUM(mortuary), 0) as mortuary_total')
            ->first();

        $personalSavings = (float) ($totals?->balance_total ?? 0);
        $mortuaryTotal = (float) ($totals?->mortuary_total ?? 0);

        return [$personalSavings, $personalSavings + $mortuaryTotal];
    }

    private function getLastLoanTransactionDate(string $acctno): ?string
    {
        if (! $this->hasTable('wlnmaster') || ! $this->hasColumn('wlnmaster', 'lastmove')) {
            return null;
        }

        $value = Wlnmaster::query()->where('acctno', $acctno)->max('lastmove');

        return $value ? (string) $value : null;
    }

    private function getLastSavingsTransactionDate(string $acctno): ?string
    {
        if (! $this->hasTable('wsvmaster') || ! $this->hasColumn('wsvmaster', 'lastmove')) {
            return null;
        }

        $value = Wsvmaster::query()->where('acctno', $acctno)->max('lastmove');

        return $value ? (string) $value : null;
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    private function emptyPaginator(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $page);
    }
}
