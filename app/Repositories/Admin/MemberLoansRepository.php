<?php

namespace App\Repositories\Admin;

use App\Models\Amortsched;
use App\Models\Wlnled;
use App\Models\Wlnmaster;
use App\Support\LoanPaymentSplit;
use App\Support\SchemaCapabilities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MemberLoansRepository
{
    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    public function findLoan(string $acctno, string $loanNumber): ?Wlnmaster
    {
        if (! $this->hasTable('wlnmaster')) {
            return null;
        }

        return Wlnmaster::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber)
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Amortsched>
     */
    public function getScheduleEntries(string $loanNumber): Collection
    {
        if (! $this->hasScheduleTable()) {
            return collect();
        }

        return $this->scheduleQuery()
            ->where('lnnumber', $loanNumber)
            ->select([
                'lnnumber',
                'Date_pay',
                'Amortization',
                'Interest',
                'Balance',
                'controlno',
            ])
            ->orderBy('Date_pay')
            ->get();
    }

    public function getNextPaymentDate(string $loanNumber): ?string
    {
        if (! $this->hasScheduleTable()) {
            return null;
        }

        $today = Carbon::today();
        $value = $this->scheduleQuery()
            ->where('lnnumber', $loanNumber)
            ->where('Date_pay', '>=', $today->copy()->startOfDay())
            ->orderBy('Date_pay')
            ->value('Date_pay');

        return $this->formatDateValue($value);
    }

    /**
     * Next schedule installment the member has not yet covered: the first row
     * whose post-installment Balance sits below the current loan balance.
     * Falls back to the earliest upcoming due, then the first row. Closed
     * (zero-balance) loans have no next installment.
     */
    public function getNextUnpaidScheduleEntry(
        string $loanNumber,
        ?float $balance,
    ): ?Amortsched {
        if (! $this->hasScheduleTable()) {
            return null;
        }

        if ($balance !== null && $balance <= 0) {
            return null;
        }

        $entries = $this->getScheduleEntries($loanNumber);

        if ($entries->isEmpty()) {
            return null;
        }

        if ($balance !== null) {
            $covered = $entries->first(function (Amortsched $entry) use ($balance) {
                $rowBalance = $this->castNumber($entry->Balance ?? null);

                return $rowBalance !== null && $rowBalance < $balance - 0.005;
            });

            if ($covered !== null) {
                return $covered;
            }
        }

        $today = Carbon::today()->startOfDay();

        $upcoming = $entries->first(function (Amortsched $entry) use ($today) {
            try {
                $date = Carbon::parse((string) ($entry->Date_pay ?? ''));
            } catch (\Throwable) {
                return false;
            }

            return $date->startOfDay()->greaterThanOrEqualTo($today);
        });

        return $upcoming ?? $entries->first();
    }

    /**
     * @note Last payment date uses wlnled.payments > 0 to identify actual payments.
     */
    public function getLastPaymentDate(
        string $acctno,
        string $loanNumber,
    ): ?string {
        if (! $this->hasTable('wlnled') || ! $this->hasColumn('wlnled', 'payments')) {
            return null;
        }

        $value = Wlnled::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber)
            ->where('payments', '>', 0)
            ->max('date_in');

        return $this->formatDateValue($value);
    }

    public function getPaginatedPayments(
        string $acctno,
        string $loanNumber,
        ?Carbon $startDate,
        ?Carbon $endDate,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        if (! $this->hasTable('wlnled')) {
            return $this->emptyPaginator($perPage, $page);
        }

        $query = Wlnled::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber)
            ->select($this->paymentSelectColumns());

        $this->applyPaymentAmountFilter($query);
        $this->applyDateRange($query, $startDate, $endDate);

        $paginator = $query
            ->orderByDesc('date_in')
            ->paginate($perPage, ['*'], 'page', $page);

        $this->attachResolvedSplit($paginator->getCollection());

        return $paginator;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Wlnled>
     */
    public function getPaymentsForExport(
        string $acctno,
        string $loanNumber,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): Collection {
        if (! $this->hasTable('wlnled')) {
            return collect();
        }

        $query = Wlnled::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber)
            ->select($this->paymentSelectColumns());

        $this->applyPaymentAmountFilter($query);
        $this->applyDateRange($query, $startDate, $endDate);

        $rows = $query->orderBy('date_in')->get();

        $this->attachResolvedSplit($rows);

        return $rows;
    }

    /**
     * Attach schedule-derived principal/interest splits to ledger rows.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $rows
     */
    private function attachResolvedSplit(Collection $rows): void
    {
        if ($rows->isEmpty() || ! $this->hasScheduleTable()) {
            return;
        }

        $loanNumbers = $rows
            ->map(static fn (mixed $row): string => trim((string) ($row->lnnumber ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($loanNumbers === []) {
            return;
        }

        $schedules = $this->scheduleQuery()
            ->whereIn('lnnumber', $loanNumbers)
            ->orderBy('Date_pay')
            ->get();

        LoanPaymentSplit::attachResolved($rows, $schedules);
    }

    public function getOpeningBalance(
        string $acctno,
        string $loanNumber,
        ?Carbon $startDate,
    ): ?float {
        if (
            $startDate === null ||
            ! $this->hasTable('wlnled') ||
            ! $this->hasColumn('wlnled', 'balance')
        ) {
            return null;
        }

        $value = Wlnled::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber)
            ->where('date_in', '<', $startDate->copy()->startOfDay())
            ->orderByDesc('date_in')
            ->value('balance');

        return $this->castNumber($value);
    }

    /**
     * Distinct ledger months (newest first) for one loan, each with its
     * movement count for the Documents tab statement rows.
     *
     * @return list<array{month: string, transactions: int}>
     */
    public function getStatementMonths(string $acctno, string $loanNumber): array
    {
        if (! $this->hasTable('wlnled') || ! $this->hasColumn('wlnled', 'date_in')) {
            return [];
        }

        $counts = [];

        foreach (
            Wlnled::query()
                ->where('acctno', $acctno)
                ->where('lnnumber', $loanNumber)
                ->whereNotNull('date_in')
                ->orderByDesc('date_in')
                ->get(['date_in']) as $row
        ) {
            $month = $this->monthKeyFromValue($row->date_in ?? null);

            if ($month === null) {
                continue;
            }

            $counts[$month] = ($counts[$month] ?? 0) + 1;
        }

        return array_map(
            static fn (string $month): array => [
                'month' => $month,
                'transactions' => $counts[$month],
            ],
            array_keys($counts),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Wlnled>
     */
    public function getStatementMovements(
        string $acctno,
        string $loanNumber,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): Collection {
        if (! $this->hasTable('wlnled')) {
            return collect();
        }

        $query = Wlnled::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber)
            ->select($this->paymentSelectColumns());

        $this->applyPaymentAmountFilter($query);
        $this->applyDateRange($query, $startDate, $endDate);

        $movements = $query->orderBy('date_in')->orderBy('controlno')->get();

        $this->attachResolvedSplit($movements);

        return $movements;
    }

    public function getDerivedOpeningBalance(
        string $acctno,
        string $loanNumber,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): ?float {
        if (
            ! $this->hasTable('wlnled') ||
            ! $this->hasColumn('wlnled', 'balance')
        ) {
            return null;
        }

        $query = Wlnled::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber);

        $this->applyPaymentAmountFilter($query);
        $this->applyDateRange($query, $startDate, $endDate);

        $select = ['date_in', 'balance'];

        if ($this->hasColumn('wlnled', 'principal')) {
            $select[] = 'principal';
        }

        if ($this->hasColumn('wlnled', 'payments')) {
            $select[] = 'payments';
        }

        $row = $query->orderBy('date_in')->select($select)->first();

        if ($row === null) {
            return null;
        }

        $balance = $this->castNumber($row->balance ?? null);

        if ($balance === null) {
            return null;
        }

        $principal = $this->castNumber($row->principal ?? null) ?? 0.0;
        $payments = $this->castNumber($row->payments ?? null) ?? 0.0;

        return $balance - $principal + $payments;
    }

    public function getClosingBalance(
        string $acctno,
        string $loanNumber,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): ?float {
        if (! $this->hasTable('wlnled') || ! $this->hasColumn('wlnled', 'balance')) {
            return null;
        }

        $query = Wlnled::query()
            ->where('acctno', $acctno)
            ->where('lnnumber', $loanNumber);

        $this->applyDateRange($query, $startDate, $endDate);

        $value = $query->orderByDesc('date_in')->value('balance');

        return $this->castNumber($value);
    }

    private function applyDateRange(
        Builder $query,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): void {
        if ($startDate !== null) {
            $query->where('date_in', '>=', $startDate->copy()->startOfDay());
        }

        if ($endDate !== null) {
            $query->where('date_in', '<=', $endDate->copy()->endOfDay());
        }
    }

    private function applyPaymentAmountFilter(Builder $query): void
    {
        $principalColumn = $this->hasColumn('wlnled', 'principal') ? 'principal' : null;
        $paymentsColumn = $this->hasColumn('wlnled', 'payments') ? 'payments' : null;

        if ($principalColumn === null && $paymentsColumn === null) {
            return;
        }

        $query->where(function (Builder $builder) use ($principalColumn, $paymentsColumn) {
            if ($principalColumn !== null) {
                $builder->where($principalColumn, '!=', 0);
            }

            if ($paymentsColumn !== null) {
                $method = $principalColumn !== null ? 'orWhere' : 'where';
                $builder->{$method}($paymentsColumn, '!=', 0);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function paymentSelectColumns(): array
    {
        $columns = [
            'acctno',
            'lnnumber',
            'lntype',
            'date_in',
        ];

        $optional = [
            'mreference',
            'principal',
            'payments',
            'debit',
            'credit',
            'balance',
            'accruedint',
            'lnstatus',
            'controlno',
            'transno',
            'initial',
            'grouploan',
        ];

        foreach ($optional as $column) {
            if ($this->hasColumn('wlnled', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function scheduleQuery(): Builder
    {
        $connection = $this->scheduleConnectionName();

        if ($connection === null) {
            return Amortsched::query();
        }

        return Amortsched::on($connection);
    }

    private function scheduleConnectionName(): ?string
    {
        $connections = config('database.connections');

        if (is_array($connections) && array_key_exists('rbank2', $connections)) {
            return 'rbank2';
        }

        return null;
    }

    private function hasScheduleTable(): bool
    {
        $connection = $this->scheduleConnectionName();

        return $this->schemaCapabilities->hasTable('Amortsched', $connection);
    }

    private function hasTable(string $table): bool
    {
        return $this->schemaCapabilities->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->schemaCapabilities->hasColumn($table, $column);
    }

    private function emptyPaginator(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $page);
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private function monthKeyFromValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m');
        } catch (\Throwable) {
            return null;
        }
    }

    private function castNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
