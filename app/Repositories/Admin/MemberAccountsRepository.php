<?php

namespace App\Repositories\Admin;

use App\Models\Wlnmaster;
use App\Models\Wsavled;
use App\Models\Wsvmaster;
use App\Support\SchemaCapabilities;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @note No active/inactive flag was found for WIBS tables, so queries include all rows per acctno.
 */
class MemberAccountsRepository
{
    private const LOAN_SECURITY_TYPECODE = '01';

    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    /**
     * @return array{
     *     loanBalanceLeft: float,
     *     currentLoanSecurityBalance: float,
     *     currentLoanSecurityTotal: float,
     *     lastLoanTransactionDate: ?string,
     *     lastLoanSecurityTransactionDate: ?string,
     *     recentLoans: \Illuminate\Support\Collection<int, mixed>,
     *     recentLoanSecurity: \Illuminate\Support\Collection<int, mixed>
     * }
     */
    public function getSummary(string $acctno, int $recentLimit = 5): array
    {
        $loanBalanceLeft = $this->hasTable('wlnmaster')
            ? (float) Wlnmaster::query()->where('acctno', $acctno)->sum('balance')
            : 0.0;

        [$currentLoanSecurityBalance, $currentLoanSecurityTotal] = $this->getLoanSecurityTotals($acctno);

        return [
            'loanBalanceLeft' => $loanBalanceLeft,
            'currentLoanSecurityBalance' => $currentLoanSecurityBalance,
            'currentLoanSecurityTotal' => $currentLoanSecurityTotal,
            'lastLoanTransactionDate' => $this->getLastLoanTransactionDate($acctno),
            'lastLoanSecurityTransactionDate' => $this->getLastLoanSecurityTransactionDate($acctno),
            'recentLoans' => $this->getRecentLoans($acctno, $recentLimit),
            'recentLoanSecurity' => $this->getRecentLoanSecurity($acctno, $recentLimit),
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
    public function getRecentLoanSecurity(string $acctno, int $limit = 5): Collection
    {
        if (! $this->hasTable('wsvmaster')) {
            return collect();
        }

        $hasLastMove = $this->hasColumn('wsvmaster', 'lastmove');
        $orderBy = $hasLastMove ? 'lastmove' : 'svnumber';

        $query = Wsvmaster::query()
            ->where('acctno', $acctno)
            ->select([
                'svnumber',
                'svtype',
                $this->selectColumnOrDefault('wsvmaster', 'mortuary', '0'),
                $this->selectColumnOrDefault('wsvmaster', 'balance', '0'),
                $this->selectColumnOrDefault('wsvmaster', 'wbalance', '0'),
                $hasLastMove
                    ? 'wsvmaster.lastmove'
                    : DB::raw('null as lastmove'),
            ])
            ->orderByDesc($orderBy)
            ->limit($limit);

        if ($this->hasColumn('wsvmaster', 'typecode')) {
            $query->where('typecode', self::LOAN_SECURITY_TYPECODE);
        }

        return $query->get();
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

    public function getPaginatedLoanSecurity(
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
        $hasLedgerDate = $this->hasColumn('wsavled', 'date_in');

        $orderBy = $hasLedgerDate ? 'wsavled.date_in' : 'wsavled.svnumber';

        $query = Wsavled::query()
            ->where('wsavled.acctno', $acctno)
            ->select([
                'wsavled.svnumber',
                'wsavled.svtype',
                $hasLedgerDate
                    ? 'wsavled.date_in'
                    : DB::raw('null as date_in'),
                $this->selectColumnOrDefault('wsavled', 'deposit', '0'),
                $this->selectColumnOrDefault('wsavled', 'withdrawal', '0'),
                $this->selectColumnOrDefault('wsavled', 'balance', '0'),
            ])
            ->orderByDesc($orderBy);

        if ($hasSavingsLedgerTypecode) {
            $query->where('wsavled.typecode', self::LOAN_SECURITY_TYPECODE);
        } elseif ($hasSavingsMasterTypecode) {
            $query->join('wsvmaster', function ($join) {
                $join->on('wsvmaster.svnumber', '=', 'wsavled.svnumber')
                    ->on('wsvmaster.acctno', '=', 'wsavled.acctno');
            })->where('wsvmaster.typecode', self::LOAN_SECURITY_TYPECODE);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{latestBalance: float, lastTransactionDate: ?string}
     */
    public function getLoanSecurityLedgerSummary(string $acctno): array
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

        $hasLedgerBalance = $this->hasColumn('wsavled', 'balance');
        $hasLedgerDate = $this->hasColumn('wsavled', 'date_in');
        $orderBy = $hasLedgerDate ? 'wsavled.date_in' : 'wsavled.svnumber';

        $query = DB::table('wsavled')
            ->where('wsavled.acctno', $acctno)
            ->select([
                $this->selectColumnOrDefault('wsavled', 'balance', '0'),
                $this->selectColumnOrDefault('wsavled', 'date_in', 'null'),
            ])
            ->orderByDesc($orderBy);

        if ($hasSavingsLedgerTypecode) {
            $query->where('wsavled.typecode', self::LOAN_SECURITY_TYPECODE);
        } elseif ($hasSavingsMasterTypecode) {
            $query->join('wsvmaster', function ($join) {
                $join->on('wsvmaster.svnumber', '=', 'wsavled.svnumber')
                    ->on('wsvmaster.acctno', '=', 'wsavled.acctno');
            })->where('wsvmaster.typecode', self::LOAN_SECURITY_TYPECODE);
        }

        $row = $query->first();
        $latestBalance = $hasLedgerBalance
            ? (float) ($row?->balance ?? 0)
            : $this->resolveLoanSecurityMasterBalance($acctno);
        $lastTransactionDate = $hasLedgerDate
            ? ($row?->date_in ? (string) $row->date_in : null)
            : $this->resolveLoanSecurityMasterLastMove($acctno);

        return [
            'latestBalance' => $latestBalance,
            'lastTransactionDate' => $lastTransactionDate,
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
        $hasSavingsDeposit = $hasSavings && $this->hasColumn('wsavled', 'deposit');
        $hasSavingsWithdrawal = $hasSavings && $this->hasColumn('wsavled', 'withdrawal');

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
                    'wsavled.acctno',
                    'wsavled.svnumber as number',
                    $this->selectColumnOrDefault('wsavled', 'date_in', 'null'),
                    'wsavled.svtype as transaction_type',
                    $this->selectColumnOrDefault(
                        'wsavled',
                        'deposit',
                        '0',
                        'amount',
                    ),
                    $this->selectColumnOrDefault(
                        'wsavled',
                        'withdrawal',
                        '0',
                        'movement',
                    ),
                    $this->selectColumnOrDefault('wsavled', 'balance', '0'),
                    DB::raw("'SAV' as source"),
                    DB::raw('null as principal'),
                    $this->selectColumnOrDefault('wsavled', 'deposit', '0'),
                    $this->selectColumnOrDefault('wsavled', 'withdrawal', '0'),
                    DB::raw('null as payments'),
                    DB::raw('null as debit'),
                ])
                ->where('wsavled.acctno', $acctno)
            : null;

        if ($savingsQuery) {
            if ($hasSavingsDeposit || $hasSavingsWithdrawal) {
                $savingsQuery->where(function ($builder) use (
                    $hasSavingsDeposit,
                    $hasSavingsWithdrawal,
                ) {
                    if ($hasSavingsWithdrawal) {
                        $builder->where('wsavled.withdrawal', '!=', 0);
                    }

                    if ($hasSavingsDeposit) {
                        if ($hasSavingsWithdrawal) {
                            $builder->orWhere('wsavled.deposit', '!=', 0);
                        } else {
                            $builder->where('wsavled.deposit', '!=', 0);
                        }
                    }
                });
            }

            if ($hasSavingsLedgerTypecode) {
                $savingsQuery->where('wsavled.typecode', self::LOAN_SECURITY_TYPECODE);
            } elseif ($hasSavingsMasterTypecode) {
                $savingsQuery->join('wsvmaster', function ($join) {
                    $join->on('wsvmaster.svnumber', '=', 'wsavled.svnumber')
                        ->on('wsvmaster.acctno', '=', 'wsavled.acctno');
                })->where('wsvmaster.typecode', self::LOAN_SECURITY_TYPECODE);
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
    private function getLoanSecurityTotals(string $acctno): array
    {
        if (! $this->hasTable('wsvmaster')) {
            return [0.0, 0.0];
        }

        $balanceSelect = $this->hasColumn('wsvmaster', 'balance')
            ? DB::raw('COALESCE(SUM(balance), 0) as balance_total')
            : DB::raw('0 as balance_total');
        $mortuarySelect = $this->hasColumn('wsvmaster', 'mortuary')
            ? DB::raw('COALESCE(SUM(mortuary), 0) as mortuary_total')
            : DB::raw('0 as mortuary_total');

        $query = Wsvmaster::query()
            ->where('acctno', $acctno)
            ->select([$balanceSelect, $mortuarySelect]);

        if ($this->hasColumn('wsvmaster', 'typecode')) {
            $query->where('typecode', self::LOAN_SECURITY_TYPECODE);
        }

        $totals = $query->first();

        $loanSecurityBalance = (float) ($totals?->balance_total ?? 0);
        $mortuaryTotal = (float) ($totals?->mortuary_total ?? 0);

        return [$loanSecurityBalance, $loanSecurityBalance + $mortuaryTotal];
    }

    private function getLastLoanTransactionDate(string $acctno): ?string
    {
        if (! $this->hasTable('wlnmaster') || ! $this->hasColumn('wlnmaster', 'lastmove')) {
            return null;
        }

        $value = Wlnmaster::query()->where('acctno', $acctno)->max('lastmove');

        return $value ? (string) $value : null;
    }

    private function getLastLoanSecurityTransactionDate(string $acctno): ?string
    {
        if (! $this->hasTable('wsvmaster') || ! $this->hasColumn('wsvmaster', 'lastmove')) {
            return null;
        }

        $query = Wsvmaster::query()->where('acctno', $acctno);

        if ($this->hasColumn('wsvmaster', 'typecode')) {
            $query->where('typecode', self::LOAN_SECURITY_TYPECODE);
        }

        $value = $query->max('lastmove');

        return $value ? (string) $value : null;
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaCapabilities->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->schemaCapabilities->hasColumn($table, $column);
    }

    /**
     * @return string|\Illuminate\Database\Query\Expression
     */
    private function selectColumnOrDefault(
        string $table,
        string $column,
        string $defaultExpression,
        ?string $alias = null,
    ): mixed {
        $alias = $alias ?? $column;

        if ($this->hasColumn($table, $column)) {
            if ($alias === $column) {
                return $table.'.'.$column;
            }

            return $table.'.'.$column.' as '.$alias;
        }

        return DB::raw($defaultExpression.' as '.$alias);
    }

    private function resolveLoanSecurityMasterBalance(string $acctno): float
    {
        if (! $this->hasTable('wsvmaster')) {
            return 0.0;
        }

        $query = Wsvmaster::query()->where('acctno', $acctno);

        if ($this->hasColumn('wsvmaster', 'typecode')) {
            $query->where('typecode', self::LOAN_SECURITY_TYPECODE);
        }

        if ($this->hasColumn('wsvmaster', 'balance')) {
            return (float) $query->sum('balance');
        }

        if ($this->hasColumn('wsvmaster', 'wbalance')) {
            return (float) $query->sum('wbalance');
        }

        return 0.0;
    }

    private function resolveLoanSecurityMasterLastMove(string $acctno): ?string
    {
        if (! $this->hasTable('wsvmaster') || ! $this->hasColumn('wsvmaster', 'lastmove')) {
            return null;
        }

        $query = Wsvmaster::query()->where('acctno', $acctno);

        if ($this->hasColumn('wsvmaster', 'typecode')) {
            $query->where('typecode', self::LOAN_SECURITY_TYPECODE);
        }

        $value = $query->max('lastmove');

        return $value ? (string) $value : null;
    }

    private function emptyPaginator(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $page);
    }
}
