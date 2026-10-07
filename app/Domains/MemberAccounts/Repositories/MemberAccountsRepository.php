<?php

namespace App\Domains\MemberAccounts\Repositories;

use App\Models\Amortsched;
use App\Models\Wlnled;
use App\Models\Wlnmaster;
use App\Models\Wsavled;
use App\Models\Wsvmaster;
use App\Support\LoanPaymentSplit;
use App\Support\SchemaCapabilities;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
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

        if ($this->hasColumn('wlnmaster', 'int_rate')) {
            $select[] = 'int_rate';
        }

        if ($this->hasColumn('wlnmaster', 'term_mons')) {
            $select[] = 'term_mons';
        }

        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->select($select)
            ->orderByDesc($orderBy)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Next repayment due date and installment amount per loan, derived from the
     * Amortsched schedule. The next due is the earliest installment the member
     * has not yet covered (schedule balance above the current loan balance);
     * overdue dues add a display-only penalty from wlnmaster.pen_rate.
     *
     * @param  list<string>  $loanNumbers
     * @param  array<string, float|null>  $balancesByLoan
     * @return array<string, array{dueDate: ?string, monthlyDue: ?float, penalty: ?float, penaltyMonths: int}>
     */
    public function getLoanRepaymentSummaries(array $loanNumbers, array $balancesByLoan = []): array
    {
        $loanNumbers = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $loanNumbers),
            static fn (string $value): bool => $value !== '',
        ));

        if ($loanNumbers === [] || ! $this->hasTable('Amortsched')) {
            return $this->amortizationFallbackSummaries($loanNumbers);
        }

        $hasDatePay = $this->hasColumn('Amortsched', 'Date_pay');
        $hasAmortization = $this->hasColumn('Amortsched', 'Amortization');
        $hasBalance = $this->hasColumn('Amortsched', 'Balance');

        if (! $hasDatePay && ! $hasAmortization) {
            return $this->amortizationFallbackSummaries($loanNumbers);
        }

        $dateColumn = $hasDatePay ? 'Date_pay' : null;
        $amortizationColumn = $hasAmortization ? 'Amortization' : null;

        $rows = Amortsched::query()
            ->whereIn('lnnumber', $loanNumbers)
            ->when($dateColumn !== null, static fn ($query) => $query->orderBy('Date_pay'))
            ->get()
            ->sortBy('Date_pay')
            ->values();

        // Legacy loan numbers are space-padded (SQL ignores it, PHP does
        // not), so group by the trimmed number before matching.
        $rowsByLoan = LoanPaymentSplit::indexRowsByLoan($rows);

        $penRates = $this->loanPenaltyRates($loanNumbers);

        $today = Carbon::now()->startOfDay();
        $summaries = [];

        foreach ($loanNumbers as $loanNumber) {
            $scheduledRows = collect($rowsByLoan[(string) $loanNumber] ?? [])->values();

            if ($scheduledRows->isEmpty()) {
                $fallback = $this->amortizationFallbackSummaries([$loanNumber]);

                if ($fallback !== []) {
                    $summaries[(string) $loanNumber] = $fallback[(string) $loanNumber];
                }

                continue;
            }

            // Earliest installment the member has not yet covered: schedule
            // Balance is the remainder AFTER the installment, so the next due
            // is the first row whose balance sits below the current balance.
            $next = null;
            $balance = $balancesByLoan[(string) $loanNumber] ?? null;

            if ($hasBalance && $balance !== null) {
                $next = $scheduledRows->first(function ($row) use ($balance) {
                    $rowBalance = $this->castNumber($row->Balance ?? null);

                    return $rowBalance !== null && $rowBalance < $balance - 0.005;
                });
            }

            // Prefer the earliest unpaid (future or today) installment as the next due.
            $next ??= $scheduledRows->first(function ($row) use ($today) {
                $date = $this->toDate($row->Date_pay ?? null);

                return $date !== null && $date->greaterThanOrEqualTo($today);
            });

            $next ??= $scheduledRows->first();

            if ($next === null) {
                continue;
            }

            $amortization = $amortizationColumn !== null
                ? $this->castNumber($next->Amortization ?? null)
                : null;

            $penalty = null;
            $penaltyMonths = 0;

            $dueDate = $this->toDate($next->Date_pay ?? null);
            $penRate = $penRates[(string) $loanNumber] ?? null;

            if ($dueDate !== null && $dueDate->lessThan($today) && ($amortization ?? 0) > 0 && ($penRate ?? 0) > 0) {
                $daysLate = abs($today->diffInDays($dueDate));
                $penaltyMonths = max(1, (int) ceil($daysLate / 30));
                $penalty = round($amortization * ($penRate / 12 / 100) * $penaltyMonths, 2);
            }

            $summaries[(string) $loanNumber] = [
                'dueDate' => $this->formatDateValue($next->Date_pay ?? null),
                'monthlyDue' => $amortization === null
                    ? null
                    : round($amortization + ($penalty ?? 0), 2),
                'penalty' => $penalty,
                'penaltyMonths' => $penaltyMonths,
            ];
        }

        return $summaries;
    }

    /**
     * Installment amount straight from wlnmaster for loans without schedule
     * rows: no due date and no penalty can be derived.
     *
     * @param  list<string>  $loanNumbers
     * @return array<string, array{dueDate: ?string, monthlyDue: ?float, penalty: ?float, penaltyMonths: int}>
     */
    private function amortizationFallbackSummaries(array $loanNumbers): array
    {
        if ($loanNumbers === [] || ! $this->hasTable('wlnmaster') || ! $this->hasColumn('wlnmaster', 'amortization')) {
            return [];
        }

        $amounts = Wlnmaster::query()
            ->whereIn('lnnumber', $loanNumbers)
            ->pluck('amortization', 'lnnumber');

        $byLoan = [];

        foreach ($amounts as $key => $amount) {
            $byLoan[trim((string) $key)] = $amount;
        }

        $summaries = [];

        foreach ($loanNumbers as $loanNumber) {
            $amount = $this->castNumber($byLoan[(string) $loanNumber] ?? null);

            if ($amount === null) {
                continue;
            }

            $summaries[(string) $loanNumber] = [
                'dueDate' => null,
                'monthlyDue' => $amount,
                'penalty' => null,
                'penaltyMonths' => 0,
            ];
        }

        return $summaries;
    }

    /**
     * @param  list<string>  $loanNumbers
     * @return array<string, float|null>
     */
    private function loanPenaltyRates(array $loanNumbers): array
    {
        if (! $this->hasTable('wlnmaster') || ! $this->hasColumn('wlnmaster', 'pen_rate')) {
            return [];
        }

        $rates = Wlnmaster::query()
            ->whereIn('lnnumber', $loanNumbers)
            ->pluck('pen_rate', 'lnnumber');

        $byLoan = [];

        foreach ($rates as $key => $rate) {
            $byLoan[trim((string) $key)] = $rate;
        }

        $mapped = [];

        foreach ($loanNumbers as $loanNumber) {
            $mapped[(string) $loanNumber] = $this->castNumber($byLoan[(string) $loanNumber] ?? null);
        }

        return $mapped;
    }

    /**
     * Recent loan payments across all loans for an account, newest first.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Wlnled>
     */
    public function getRecentLoanPayments(string $acctno, int $limit = 8): Collection
    {
        if (! $this->hasTable('wlnled')) {
            return collect();
        }

        $limit = max(1, min($limit, 50));

        $select = [
            $this->selectColumnOrDefault('wlnled', 'lnnumber', "''"),
            $this->selectColumnOrDefault('wlnled', 'lntype', "''"),
            $this->hasColumn('wlnled', 'date_in')
                ? 'wlnled.date_in'
                : DB::raw('null as date_in'),
            $this->selectColumnOrDefault('wlnled', 'payments', '0'),
            $this->selectColumnOrDefault('wlnled', 'principal', '0'),
            $this->selectColumnOrDefault('wlnled', 'accruedint', '0'),
        ];

        $query = Wlnled::query()
            ->where('acctno', $acctno)
            ->select($select);

        if ($this->hasColumn('wlnled', 'payments')) {
            $query->where('payments', '>', 0);
        }

        if ($this->hasColumn('wlnled', 'date_in')) {
            $query->orderByDesc('date_in');
        }

        $payments = $query->limit($limit)->get();

        LoanPaymentSplit::attachResolved($payments, $this->schedulesFor($payments));

        return $payments;
    }

    /**
     * Amortsched rows for every loan present in the given payments, for
     * principal/interest split derivation.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $payments
     * @return \Illuminate\Support\Collection<int, \App\Models\Amortsched>
     */
    private function schedulesFor(Collection $payments): Collection
    {
        if (! $this->hasTable('Amortsched')) {
            return collect();
        }

        $loanNumbers = $payments
            ->map(static fn (mixed $row): string => trim((string) ($row->lnnumber ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($loanNumbers === []) {
            return collect();
        }

        return Amortsched::query()
            ->whereIn('lnnumber', $loanNumbers)
            ->orderBy('Date_pay')
            ->get();
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
        ?string $source = null,
        ?string $search = null,
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
                    $this->selectColumnOrDefault(
                        'wlnled',
                        'controlno',
                        'null',
                        'control_no',
                    ),
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
                    $this->selectColumnOrDefault(
                        'wsavled',
                        'controlno',
                        'null',
                        'control_no',
                    ),
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
            ->when(
                in_array($source, ['LOAN', 'SAV'], true),
                fn ($query) => $query->where('source', $source),
            )
            ->when($search !== null && trim($search) !== '', function ($query) use ($search) {
                $like = '%'.trim($search).'%';

                $query->where(fn ($inner) => $inner
                    ->where('number', 'like', $like)
                    ->orWhere('transaction_type', 'like', $like));
            })
            ->orderByDesc('date_in')
            ->orderByDesc('control_no');

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

        $query = Wsvmaster::query()->where('acctno', $acctno);

        if ($this->hasColumn('wsvmaster', 'typecode')) {
            $query->where('typecode', self::LOAN_SECURITY_TYPECODE);
        }

        $loanSecurityBalance = $this->hasColumn('wsvmaster', 'balance')
            ? (float) (clone $query)->sum('balance')
            : 0.0;
        $mortuaryTotal = $this->hasColumn('wsvmaster', 'mortuary')
            ? (float) (clone $query)->sum('mortuary')
            : 0.0;

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

    private function toDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDateValue(mixed $value): ?string
    {
        $date = $this->toDate($value);

        return $date?->format('Y-m-d');
    }

    private function castNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
