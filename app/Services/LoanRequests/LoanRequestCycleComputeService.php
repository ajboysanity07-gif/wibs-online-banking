<?php

namespace App\Services\LoanRequests;

use App\Models\LoanRequest;
use App\Models\Wlnmaster;

/**
 * Computes the Group Life Insurance (Generali/Grepalife) cycle status and
 * number for a member based on their total loan history in wlnmaster,
 * minus any Due date loans that were term-1 (no insurance).
 *
 * Formula:
 *   totalLoans     = count of wlnmaster rows for the member's acctno
 *   lumpsumLoans   = count of loan_requests where (Due date + term 1)
 *   insuredLoans   = totalLoans - lumpsumLoans
 *   cycleNumber    = insuredLoans + 1
 *   cycleStatus    = cycleNumber <= 2 ? 'New' : 'Old'
 */
class LoanRequestCycleComputeService
{
    public function computeCycleForLoanRequest(LoanRequest $loanRequest): array
    {
        $acctno = $loanRequest->acctno ?? $loanRequest->user?->acctno;

        if ($acctno === null || $acctno === '') {
            return $this->defaultCycle();
        }

        $acctno = trim((string) $acctno);

        try {
            $totalLoans = Wlnmaster::query()
                ->where('acctno', $acctno)
                ->count();
        } catch (\Throwable) {
            return $this->defaultCycle();
        }

        $lumpsumLoans = LoanRequest::query()
            ->where('acctno', $acctno)
            ->where('id', '!=', $loanRequest->id)
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('requested_payment_frequency', 'Due date')
                        ->where('requested_term', 1);
                })->orWhere(function ($q) {
                    $q->where('recommended_payment_frequency', 'Due date')
                        ->where('recommended_term', 1);
                });
            })
            ->count();

        $insuredLoans = max(0, $totalLoans - $lumpsumLoans);
        $cycleNumber = $insuredLoans + 1;
        $cycleStatus = $cycleNumber <= 2 ? 'New' : 'Old';

        return [
            'cycle_status' => $cycleStatus,
            'cycle_number' => $cycleNumber,
        ];
    }

    /**
     * @return array{cycle_status: string, cycle_number: int}
     */
    private function defaultCycle(): array
    {
        return [
            'cycle_status' => 'New',
            'cycle_number' => 1,
        ];
    }
}
