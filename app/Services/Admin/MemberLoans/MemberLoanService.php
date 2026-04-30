<?php

namespace App\Services\Admin\MemberLoans;

use App\Models\AppUser;
use App\Models\Wlnmaster;
use App\Models\Wmaster;
use App\Repositories\Admin\MemberLoansRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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
     *         recommendedPayment: ?float,
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
     *         recommendedPayment: ?float,
     *         nextPaymentDate: ?string,
     *         lastPaymentDate: ?string
     *     },
     *     payments: \Illuminate\Pagination\LengthAwarePaginator,
     *     filters: array{range: string, start: ?string, end: ?string},
     *     openingBalance: ?float,
     *     closingBalance: ?float
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
        ];
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
     *         recommendedPayment: ?float,
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
     * @return array{balance: float, recommendedPayment: ?float, nextPaymentDate: ?string, lastPaymentDate: ?string}
     */
    private function buildSummary(string $acctno, Wlnmaster $loan): array
    {
        return [
            'balance' => (float) ($loan->balance ?? 0),
            'recommendedPayment' => $this->repository->getNextPaymentAmount(
                $loan->lnnumber,
            ),
            'nextPaymentDate' => $this->repository->getNextPaymentDate(
                $loan->lnnumber,
            ),
            'lastPaymentDate' => $this->repository->getLastPaymentDate(
                $acctno,
                $loan->lnnumber,
            ),
        ];
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
        $range = $range ?: self::RANGE_CURRENT_MONTH;

        if (! in_array($range, $this->allowedRanges(), true)) {
            $range = self::RANGE_CURRENT_MONTH;
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
