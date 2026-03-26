<?php

namespace App\Repositories\Admin;

use App\Models\Amortsched;
use App\Models\Wlnled;
use App\Models\Wlnmaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MemberLoansRepository
{
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

        $value = $this->scheduleQuery()
            ->where('lnnumber', $loanNumber)
            ->whereDate('Date_pay', '>=', Carbon::today())
            ->orderBy('Date_pay')
            ->value('Date_pay');

        return $this->formatDateValue($value);
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

        return $query
            ->orderByDesc('date_in')
            ->paginate($perPage, ['*'], 'page', $page);
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

        return $query->orderBy('date_in')->get();
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
            ->whereDate('date_in', '<', $startDate)
            ->orderByDesc('date_in')
            ->value('balance');

        return $this->castNumber($value);
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
            $query->whereDate('date_in', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->whereDate('date_in', '<=', $endDate);
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

        if ($connection === null) {
            return Schema::hasTable('Amortsched');
        }

        return Schema::connection($connection)->hasTable('Amortsched');
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

    private function castNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
