<?php

namespace App\Repositories\Admin;

use App\Models\Wlnmaster;
use App\Models\Wsavled;
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
    private const PERSONAL_SAVINGS_TYPECODE = 4;

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
        if (! $this->hasTable('wsvmaster') || ! $this->hasColumn('wsvmaster', 'typecode')) {
            return collect();
        }

        $orderBy = $this->hasColumn('wsvmaster', 'lastmove') ? 'lastmove' : 'svnumber';

        return Wsvmaster::query()
            ->where('acctno', $acctno)
            ->where('typecode', self::PERSONAL_SAVINGS_TYPECODE)
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
        if (! $this->hasTable('wsavled')) {
            return $this->emptyPaginator($perPage, $page);
        }

        $hasSavingsLedgerTypecode = $this->hasColumn('wsavled', 'typecode');
        $hasSavingsMasterTypecode = $this->hasTable('wsvmaster')
            && $this->hasColumn('wsvmaster', 'typecode');

        if (! $hasSavingsLedgerTypecode && ! $hasSavingsMasterTypecode) {
            return $this->emptyPaginator($perPage, $page);
        }

        $orderBy = $this->hasColumn('wsavled', 'date_in')
            ? 'wsavled.date_in'
            : 'wsavled.svnumber';

        $query = Wsavled::query()
            ->where('wsavled.acctno', $acctno)
            ->select([
                'wsavled.svnumber',
                'wsavled.svtype',
                'wsavled.date_in',
                'wsavled.deposit',
                'wsavled.withdrawal',
                'wsavled.balance',
            ])
            ->orderByDesc($orderBy);

        if ($hasSavingsLedgerTypecode) {
            $query->where('wsavled.typecode', self::PERSONAL_SAVINGS_TYPECODE);
        } else {
            $query->join('wsvmaster', function ($join) {
                $join->on('wsvmaster.svnumber', '=', 'wsavled.svnumber')
                    ->on('wsvmaster.acctno', '=', 'wsavled.acctno');
            })->where('wsvmaster.typecode', self::PERSONAL_SAVINGS_TYPECODE);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{latestBalance: float, lastTransactionDate: ?string}
     */
    public function getPersonalSavingsLedgerSummary(string $acctno): array
    {
        if (! $this->hasTable('wsavled')) {
            return ['latestBalance' => 0.0, 'lastTransactionDate' => null];
        }

        $hasSavingsLedgerTypecode = $this->hasColumn('wsavled', 'typecode');
        $hasSavingsMasterTypecode = $this->hasTable('wsvmaster')
            && $this->hasColumn('wsvmaster', 'typecode');

        if (! $hasSavingsLedgerTypecode && ! $hasSavingsMasterTypecode) {
            return ['latestBalance' => 0.0, 'lastTransactionDate' => null];
        }

        $orderBy = $this->hasColumn('wsavled', 'date_in')
            ? 'wsavled.date_in'
            : 'wsavled.svnumber';

        $query = DB::table('wsavled')
            ->where('wsavled.acctno', $acctno)
            ->select([
                'wsavled.balance',
                'wsavled.date_in',
            ])
            ->orderByDesc($orderBy);

        if ($hasSavingsLedgerTypecode) {
            $query->where('wsavled.typecode', self::PERSONAL_SAVINGS_TYPECODE);
        } else {
            $query->join('wsvmaster', function ($join) {
                $join->on('wsvmaster.svnumber', '=', 'wsavled.svnumber')
                    ->on('wsvmaster.acctno', '=', 'wsavled.acctno');
            })->where('wsvmaster.typecode', self::PERSONAL_SAVINGS_TYPECODE);
        }

        $row = $query->first();

        return [
            'latestBalance' => (float) ($row?->balance ?? 0),
            'lastTransactionDate' => $row?->date_in ? (string) $row->date_in : null,
        ];
    }

    public function getPaginatedRecentActions(
        string $acctno,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $hasLoans = $this->hasTable('wlnled');
        $hasSavings = $this->hasTable('wsavled');
        $hasSavingsLedgerTypecode = $hasSavings && $this->hasColumn('wsavled', 'typecode');
        $hasSavingsMasterTypecode = $this->hasTable('wsvmaster')
            && $this->hasColumn('wsvmaster', 'typecode');

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

        $savingsQuery = $hasSavings && ($hasSavingsLedgerTypecode || $hasSavingsMasterTypecode)
            ? DB::table('wsavled')
                ->select([
                    'wsavled.acctno',
                    'wsavled.svnumber as number',
                    'wsavled.date_in',
                    'wsavled.svtype as transaction_type',
                    'wsavled.deposit as amount',
                    'wsavled.withdrawal as movement',
                    'wsavled.balance',
                    DB::raw("'SAV' as source"),
                    DB::raw('null as principal'),
                    'wsavled.deposit',
                    'wsavled.withdrawal',
                    DB::raw('null as payments'),
                    DB::raw('null as debit'),
                ])
                ->where('wsavled.acctno', $acctno)
                ->where(function ($builder) {
                    $builder->where('wsavled.withdrawal', '!=', 0)
                        ->orWhere('wsavled.deposit', '!=', 0);
                })
            : null;

        if ($savingsQuery) {
            if ($hasSavingsLedgerTypecode) {
                $savingsQuery->where('wsavled.typecode', self::PERSONAL_SAVINGS_TYPECODE);
            } else {
                $savingsQuery->join('wsvmaster', function ($join) {
                    $join->on('wsvmaster.svnumber', '=', 'wsavled.svnumber')
                        ->on('wsvmaster.acctno', '=', 'wsavled.acctno');
                })->where('wsvmaster.typecode', self::PERSONAL_SAVINGS_TYPECODE);
            }
        }

        if (! $loanQuery && ! $savingsQuery) {
            return $this->emptyPaginator($perPage, $page);
        }

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
        if (! $this->hasTable('wsvmaster') || ! $this->hasColumn('wsvmaster', 'typecode')) {
            return [0.0, 0.0];
        }

        $totals = Wsvmaster::query()
            ->where('acctno', $acctno)
            ->where('typecode', self::PERSONAL_SAVINGS_TYPECODE)
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
        if (
            ! $this->hasTable('wsvmaster')
            || ! $this->hasColumn('wsvmaster', 'lastmove')
            || ! $this->hasColumn('wsvmaster', 'typecode')
        ) {
            return null;
        }

        $value = Wsvmaster::query()
            ->where('acctno', $acctno)
            ->where('typecode', self::PERSONAL_SAVINGS_TYPECODE)
            ->max('lastmove');

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
