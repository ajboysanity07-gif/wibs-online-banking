<?php

namespace App\Services\Admin\MemberLoans;

use App\Models\AppUser;
use App\Models\Wlnmaster;
use App\Models\Wmaster;
use App\Repositories\Admin\MemberLoansRepository;
use App\Support\LoanPaymentSplit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MemberLoanService
{
    private const RANGE_CURRENT_MONTH = 'current_month';

    private const RANGE_CURRENT_YEAR = 'current_year';

    private const RANGE_LAST_30_DAYS = 'last_30_days';

    private const RANGE_ALL = 'all';

    private const RANGE_CUSTOM = 'custom';

    public function __construct(
        private MemberLoansRepository $repository,
    ) {}

    /**
     * @return array{
     *     loan: \App\Models\Wlnmaster,
     *     summary: array{
     *         balance: float,
     *         nextPaymentDate: ?string,
     *         lastPaymentDate: ?string
     *     },
     *     schedule: \Illuminate\Support\Collection<int, \App\Models\Amortsched>
     * }
     */
    public function getSchedulePageData(
        AppUser|Wmaster $member,
        string $loanNumber,
    ): array {
        $context = $this->resolveLoanContext($member, $loanNumber);
        $loan = $context['loan'];
        $summary = $this->buildSummary($context['acctno'], $loan);

        return [
            'loan' => $loan,
            'summary' => $summary,
            'schedule' => $this->repository->getScheduleEntries($loan->lnnumber),
        ];
    }

    /**
     * @return array{
     *     loan: \App\Models\Wlnmaster,
     *     summary: array{
     *         balance: float,
     *         nextPaymentDate: ?string,
     *         lastPaymentDate: ?string
     *     },
     *     payments: \Illuminate\Pagination\LengthAwarePaginator,
     *     filters: array{range: string, start: ?string, end: ?string},
     *     openingBalance: ?float,
     *     closingBalance: ?float,
     *     schedule: \Illuminate\Support\Collection<int, \App\Models\Amortsched>
     * }
     */
    public function getPaymentsPageData(
        AppUser|Wmaster $member,
        string $loanNumber,
        ?string $range,
        ?string $start,
        ?string $end,
        int $perPage,
        int $page,
    ): array {
        $perPage = $this->normalizePerPage($perPage);
        $page = $this->normalizePage($page);
        $context = $this->resolveLoanContext($member, $loanNumber);
        $loan = $context['loan'];
        $summary = $this->buildSummary($context['acctno'], $loan);
        $dateRange = $this->resolveDateRange($range, $start, $end);

        $paginator = $this->repository->getPaginatedPayments(
            $context['acctno'],
            $loan->lnnumber,
            $dateRange['start'],
            $dateRange['end'],
            $perPage,
            $page,
        );

        return [
            'loan' => $loan,
            'summary' => $summary,
            'payments' => $paginator,
            'filters' => [
                'range' => $dateRange['range'],
                'start' => $dateRange['startDate'],
                'end' => $dateRange['endDate'],
            ],
            'openingBalance' => $this->resolveOpeningBalance(
                $context['acctno'],
                $loan->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
            ),
            'closingBalance' => $this->repository->getClosingBalance(
                $context['acctno'],
                $loan->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
            ),
            'schedule' => $this->repository->getScheduleEntries(
                $loan->lnnumber,
            ),
        ];
    }

    /**
     * Statement-of-account data for one loan and month: opening balance,
     * ordered movements with principal/interest split, totals, closing balance.
     *
     * @return array{
     *     month: string,
     *     periodLabel: string,
     *     issuedAt: string,
     *     openingBalance: ?float,
     *     closingBalance: ?float,
     *     totals: array{principal: float, interest: float, payments: float, count: int},
     *     movements: list<array{date: ?string, reference: string, description: string, principal: ?float, interest: ?float, credit: float, balance: ?float}>
     * }
     */
    public function getStatementData(
        AppUser|Wmaster $member,
        string $loanNumber,
        string $month,
    ): array {
        $context = $this->resolveLoanContext($member, $loanNumber);
        $acctno = $context['acctno'];
        $loan = $context['loan'];

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            abort(422, 'Invalid month');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $movements = $this->repository->getStatementMovements(
            $acctno,
            $loan->lnnumber,
            $start,
            $end,
        );

        $rows = [];
        $principalTotal = 0.0;
        $interestTotal = 0.0;
        $paymentsTotal = 0.0;

        foreach ($movements as $row) {
            $principal = $this->castNullableNumber(LoanPaymentSplit::read($row, 'split_principal', 'principal'));
            $interest = $this->castNullableNumber(LoanPaymentSplit::read($row, 'split_interest', 'accruedint'));
            $credit = $this->castNumberValue($row->payments ?? null);

            $principalTotal += $principal ?? 0.0;
            $interestTotal += $interest ?? 0.0;
            $paymentsTotal += $credit;

            $reference = trim((string) ($row->mreference ?? ''));
            if ($reference === '') {
                $reference = trim((string) ($row->transno ?? ''));
            }
            if ($reference === '') {
                $reference = trim((string) ($row->controlno ?? ''));
            }

            $rows[] = [
                'date' => $this->formatDateOnly($row->date_in ?? null),
                'reference' => $reference !== '' ? $reference : '--',
                'description' => $credit > 0
                    ? 'Payment'
                    : 'Ledger entry',
                'principal' => $principal,
                'interest' => $interest,
                'credit' => $credit,
                'balance' => $this->castNullableNumber($row->balance ?? null),
            ];
        }

        $openingBalance = $this->resolveOpeningBalance($acctno, $loan->lnnumber, $start, $end);
        $closingBalance = $this->repository->getClosingBalance($acctno, $loan->lnnumber, $start, $end);

        return [
            'month' => $month,
            'periodLabel' => $start->format('F Y'),
            'issuedAt' => Carbon::now()->format('Y-m-d'),
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
            'totals' => [
                'principal' => round($principalTotal, 2),
                'interest' => round($interestTotal, 2),
                'payments' => round($paymentsTotal, 2),
                'count' => count($rows),
            ],
            'movements' => $rows,
            'memberAddress' => $this->resolveMemberAddress($acctno),
        ];
    }

    private function resolveMemberAddress(string $acctno): ?string
    {
        // address1 holds the clean one-line address; address repeats it.
        $column = Schema::hasColumn('wmaster', 'address1') ? 'address1' : 'address';
        $value = trim((string) Wmaster::query()->where('acctno', $acctno)->value($column));

        return $value === '' ? null : $value;
    }

    /**
     * @return list<array{month: string, transactions: int}>
     */
    public function getStatementMonths(
        AppUser|Wmaster $member,
        string $loanNumber,
    ): array {
        $context = $this->resolveLoanContext($member, $loanNumber);

        return $this->repository->getStatementMonths(
            $context['acctno'],
            $context['loan']->lnnumber,
        );
    }

    /**
     * @return array{items: \Illuminate\Support\Collection<int, \App\Models\Amortsched>}
     */
    public function getScheduleEntries(
        AppUser|Wmaster $member,
        string $loanNumber,
    ): array {
        $context = $this->resolveLoanContext($member, $loanNumber);

        return [
            'items' => $this->repository->getScheduleEntries(
                $context['loan']->lnnumber,
            ),
        ];
    }

    public function getPayments(
        AppUser|Wmaster $member,
        string $loanNumber,
        ?string $range,
        ?string $start,
        ?string $end,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $perPage = $this->normalizePerPage($perPage);
        $page = $this->normalizePage($page);
        $context = $this->resolveLoanContext($member, $loanNumber);
        $dateRange = $this->resolveDateRange($range, $start, $end);

        return $this->repository->getPaginatedPayments(
            $context['acctno'],
            $context['loan']->lnnumber,
            $dateRange['start'],
            $dateRange['end'],
            $perPage,
            $page,
        );
    }

    /**
     * @return array{
     *     paginator: \Illuminate\Pagination\LengthAwarePaginator,
     *     filters: array{range: string, start: ?string, end: ?string}
     * }
     */
    public function getPaymentsWithFilters(
        AppUser|Wmaster $member,
        string $loanNumber,
        ?string $range,
        ?string $start,
        ?string $end,
        int $perPage,
        int $page,
    ): array {
        $perPage = $this->normalizePerPage($perPage);
        $page = $this->normalizePage($page);
        $context = $this->resolveLoanContext($member, $loanNumber);
        $dateRange = $this->resolveDateRange($range, $start, $end);

        return [
            'paginator' => $this->repository->getPaginatedPayments(
                $context['acctno'],
                $context['loan']->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
                $perPage,
                $page,
            ),
            'filters' => [
                'range' => $dateRange['range'],
                'start' => $dateRange['startDate'],
                'end' => $dateRange['endDate'],
            ],
        ];
    }

    /**
     * @return array{
     *     paginator: \Illuminate\Pagination\LengthAwarePaginator,
     *     filters: array{range: string, start: ?string, end: ?string},
     *     openingBalance: ?float,
     *     closingBalance: ?float
     * }
     */
    public function getPaymentsWithBalances(
        AppUser|Wmaster $member,
        string $loanNumber,
        ?string $range,
        ?string $start,
        ?string $end,
        int $perPage,
        int $page,
    ): array {
        $perPage = $this->normalizePerPage($perPage);
        $page = $this->normalizePage($page);
        $context = $this->resolveLoanContext($member, $loanNumber);
        $dateRange = $this->resolveDateRange($range, $start, $end);

        return [
            'paginator' => $this->repository->getPaginatedPayments(
                $context['acctno'],
                $context['loan']->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
                $perPage,
                $page,
            ),
            'filters' => [
                'range' => $dateRange['range'],
                'start' => $dateRange['startDate'],
                'end' => $dateRange['endDate'],
            ],
            'openingBalance' => $this->resolveOpeningBalance(
                $context['acctno'],
                $context['loan']->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
            ),
            'closingBalance' => $this->repository->getClosingBalance(
                $context['acctno'],
                $context['loan']->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
            ),
        ];
    }

    /**
     * @return array{
     *     loan: \App\Models\Wlnmaster,
     *     summary: array{
     *         balance: float,
     *         nextPaymentDate: ?string,
     *         lastPaymentDate: ?string
     *     },
     *     payments: \Illuminate\Support\Collection<int, \App\Models\Wlnled>,
     *     filters: array{range: string, start: ?string, end: ?string},
     *     openingBalance: ?float,
     *     closingBalance: ?float
     * }
     */
    public function getPaymentsExportData(
        AppUser|Wmaster $member,
        string $loanNumber,
        ?string $range,
        ?string $start,
        ?string $end,
    ): array {
        $context = $this->resolveLoanContext($member, $loanNumber);
        $loan = $context['loan'];
        $summary = $this->buildSummary($context['acctno'], $loan);
        $dateRange = $this->resolveDateRange($range, $start, $end);

        return [
            'loan' => $loan,
            'summary' => $summary,
            'payments' => $this->repository->getPaymentsForExport(
                $context['acctno'],
                $loan->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
            ),
            'filters' => [
                'range' => $dateRange['range'],
                'start' => $dateRange['startDate'],
                'end' => $dateRange['endDate'],
            ],
            'openingBalance' => $this->resolveOpeningBalance(
                $context['acctno'],
                $loan->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
            ),
            'closingBalance' => $this->repository->getClosingBalance(
                $context['acctno'],
                $loan->lnnumber,
                $dateRange['start'],
                $dateRange['end'],
            ),
        ];
    }

    /**
     * @return array{balance: float, nextPaymentDate: ?string, lastPaymentDate: ?string}
     */
    private function buildSummary(string $acctno, Wlnmaster $loan): array
    {
        $nextEntry = $this->repository->getNextUnpaidScheduleEntry(
            $loan->lnnumber,
            $loan->balance === null ? null : (float) $loan->balance,
        );

        return [
            'balance' => (float) ($loan->balance ?? 0),
            'nextPaymentDate' => $nextEntry !== null
                ? $this->formatScheduleDate($nextEntry->Date_pay ?? null)
                : $this->repository->getNextPaymentDate(
                    $loan->lnnumber,
                ),
            'lastPaymentDate' => $this->repository->getLastPaymentDate(
                $acctno,
                $loan->lnnumber,
            ),
        ];
    }

    private function formatScheduleDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * @return array{acctno: string, loan: \App\Models\Wlnmaster}
     */
    private function resolveLoanContext(
        AppUser|Wmaster $member,
        string $loanNumber,
    ): array {
        if ($member instanceof AppUser) {
            $member->loadMissing('adminProfile');

            if ($member->isAdminOnly()) {
                abort(404);
            }
        }

        $acctno = $this->resolveAcctno($member);
        $loanNumber = trim($loanNumber);

        if ($loanNumber === '') {
            abort(404);
        }

        $loan = $this->repository->findLoan($acctno, $loanNumber);

        if ($loan === null) {
            abort(404);
        }

        return [
            'acctno' => $acctno,
            'loan' => $loan,
        ];
    }

    private function resolveAcctno(AppUser|Wmaster $member): string
    {
        $acctno = $member->acctno;

        if (! is_string($acctno) || trim($acctno) === '') {
            abort(404);
        }

        return trim($acctno);
    }

    private function normalizePerPage(int $perPage): int
    {
        return max(1, min($perPage, 50));
    }

    private function normalizePage(int $page): int
    {
        return max(1, $page);
    }

    private function castNumberValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function castNullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function formatDateOnly(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolveOpeningBalance(
        string $acctno,
        string $loanNumber,
        ?Carbon $startDate,
        ?Carbon $endDate,
    ): ?float {
        $openingBalance = $this->repository->getOpeningBalance(
            $acctno,
            $loanNumber,
            $startDate,
        );

        if ($openingBalance !== null) {
            return $openingBalance;
        }

        return $this->repository->getDerivedOpeningBalance(
            $acctno,
            $loanNumber,
            $startDate,
            $endDate,
        );
    }

    /**
     * @return array{
     *     range: string,
     *     start: ?\Illuminate\Support\Carbon,
     *     end: ?\Illuminate\Support\Carbon,
     *     startDate: ?string,
     *     endDate: ?string
     * }
     */
    private function resolveDateRange(
        ?string $range,
        ?string $start,
        ?string $end,
    ): array {
        $now = Carbon::now();
        $range = $range ?: self::RANGE_ALL;

        if (! in_array($range, $this->allowedRanges(), true)) {
            $range = self::RANGE_ALL;
        }

        if ($range === self::RANGE_CURRENT_YEAR) {
            $startDate = $now->copy()->startOfYear();
            $endDate = $now->copy()->endOfYear();
        } elseif ($range === self::RANGE_LAST_30_DAYS) {
            $startDate = $now->copy()->subDays(30);
            $endDate = $now->copy();
        } elseif ($range === self::RANGE_ALL) {
            $startDate = null;
            $endDate = null;
        } elseif ($range === self::RANGE_CUSTOM) {
            $startDate = $start ? Carbon::parse($start) : null;
            $endDate = $end ? Carbon::parse($end) : null;
        } else {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        }

        return [
            'range' => $range,
            'start' => $startDate,
            'end' => $endDate,
            'startDate' => $startDate?->toDateString(),
            'endDate' => $endDate?->toDateString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedRanges(): array
    {
        return [
            self::RANGE_CURRENT_MONTH,
            self::RANGE_CURRENT_YEAR,
            self::RANGE_LAST_30_DAYS,
            self::RANGE_ALL,
            self::RANGE_CUSTOM,
        ];
    }
}
