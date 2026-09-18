<?php

namespace App\Domains\MemberAccounts\Services;

use App\Domains\MemberAccounts\Repositories\MemberAccountsRepository;
use App\Models\AppUser;
use App\Models\Wmaster;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class MemberAccountsService
{
    /**
     * The dashboard summary alone triggers 10+ sequential queries against the
     * Tailscale-tunneled SQL Server. Cache briefly per account — short enough
     * that balance changes still show up quickly, long enough to collapse
     * repeat dashboard loads (e.g. Inertia back-navigation) to zero queries.
     */
    private const DASHBOARD_SUMMARY_TTL_SECONDS = 90;

    public function __construct(
        private MemberAccountsRepository $repository,
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
    public function getSummary(AppUser|Wmaster $member): array
    {
        $acctno = $this->resolveAcctno($member);

        if ($acctno === null) {
            return $this->emptySummary();
        }

        return $this->repository->getSummary($acctno);
    }

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
    public function getDashboardSummary(AppUser|Wmaster $member): array
    {
        $acctno = $this->resolveAcctno($member);

        if ($acctno === null) {
            return $this->emptySummary();
        }

        return Cache::remember(
            "member_dashboard_summary:{$acctno}",
            self::DASHBOARD_SUMMARY_TTL_SECONDS,
            function () use ($acctno) {
                $summary = $this->repository->getSummary($acctno);
                $ledgerSummary = $this->repository->getLoanSecurityLedgerSummary($acctno);
                $summary['currentLoanSecurityBalance'] = $ledgerSummary['latestBalance'];
                $summary['currentLoanSecurityTotal'] = $ledgerSummary['latestBalance'];
                $summary['lastLoanSecurityTransactionDate'] = $ledgerSummary['lastTransactionDate'];

                return $summary;
            },
        );
    }

    public function getPaginatedLoans(
        AppUser|Wmaster $member,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $acctno = $this->resolveAcctno($member);
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        if ($acctno === null) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        return $this->repository->getPaginatedLoans($acctno, $perPage, $page);
    }

    public function getPaginatedLoanSecurity(
        AppUser|Wmaster $member,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $acctno = $this->resolveAcctno($member);
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        if ($acctno === null) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        return $this->repository->getPaginatedLoanSecurity($acctno, $perPage, $page);
    }

    /**
     * @return array{latestBalance: float, lastTransactionDate: ?string}
     */
    public function getLoanSecurityLedgerSummary(AppUser|Wmaster $member): array
    {
        $acctno = $this->resolveAcctno($member);

        if ($acctno === null) {
            return $this->emptyLoanSecurityLedgerSummary();
        }

        return $this->repository->getLoanSecurityLedgerSummary($acctno);
    }

    public function getPaginatedRecentActions(
        AppUser|Wmaster $member,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $acctno = $this->resolveAcctno($member);
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        if ($acctno === null) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        return $this->repository->getPaginatedRecentActions($acctno, $perPage, $page);
    }

    private function resolveAcctno(AppUser|Wmaster $member): ?string
    {
        $acctno = $member->acctno;

        if (! is_string($acctno) || trim($acctno) === '') {
            return null;
        }

        return $acctno;
    }

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
    private function emptySummary(): array
    {
        return [
            'loanBalanceLeft' => 0.0,
            'currentLoanSecurityBalance' => 0.0,
            'currentLoanSecurityTotal' => 0.0,
            'lastLoanTransactionDate' => null,
            'lastLoanSecurityTransactionDate' => null,
            'recentLoans' => collect(),
            'recentLoanSecurity' => collect(),
        ];
    }

    /**
     * @return array{latestBalance: float, lastTransactionDate: ?string}
     */
    private function emptyLoanSecurityLedgerSummary(): array
    {
        return [
            'latestBalance' => 0.0,
            'lastTransactionDate' => null,
        ];
    }
}
