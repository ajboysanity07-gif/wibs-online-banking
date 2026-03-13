<?php

namespace App\Services\Admin\MemberAccounts;

use App\Models\AppUser;
use App\Repositories\Admin\MemberAccountsRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class MemberAccountsService
{
    public function __construct(
        private MemberAccountsRepository $repository,
    ) {}

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
    public function getSummary(AppUser $member): array
    {
        $acctno = $this->resolveAcctno($member);

        if ($acctno === null) {
            return $this->emptySummary();
        }

        return $this->repository->getSummary($acctno);
    }

    public function getPaginatedLoans(
        AppUser $member,
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

    public function getPaginatedSavings(
        AppUser $member,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $acctno = $this->resolveAcctno($member);
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        if ($acctno === null) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        return $this->repository->getPaginatedSavings($acctno, $perPage, $page);
    }

    public function getPaginatedRecentActions(
        AppUser $member,
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

    private function resolveAcctno(AppUser $member): ?string
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
     *     currentPersonalSavings: float,
     *     currentSavingsBalance: float,
     *     lastLoanTransactionDate: ?string,
     *     lastSavingsTransactionDate: ?string,
     *     recentLoans: \Illuminate\Support\Collection<int, mixed>,
     *     recentSavings: \Illuminate\Support\Collection<int, mixed>
     * }
     */
    private function emptySummary(): array
    {
        return [
            'loanBalanceLeft' => 0.0,
            'currentPersonalSavings' => 0.0,
            'currentSavingsBalance' => 0.0,
            'lastLoanTransactionDate' => null,
            'lastSavingsTransactionDate' => null,
            'recentLoans' => collect(),
            'recentSavings' => collect(),
        ];
    }
}
