<?php

namespace App\Services\LoanRequests;

use App\Models\LoanRequest;

/**
 * Resolves the Group Life Insurance (Generali/Grepalife) cycle status
 * (New/Old + cycle number) for the applicant of a loan request's owning
 * member.
 *
 * The applicant's cycle value is auto-computed from the member's wlnmaster
 * loan history via LoanRequestCycleComputeService and is read-only (locked).
 * Spouse and dependent cycle values are not auto-computed — each dependent
 * has their own insurance history, so those remain manually entered by the
 * loan processor (see LoanRequestProcessingUpdateRequest).
 */
class LoanRequestCycleStateService
{
    public function __construct(
        private LoanRequestCycleComputeService $cycleCompute,
    ) {}

    /**
     * @return array<string, array{locked: bool, cycle_status: string, cycle_number: int}>
     */
    public function resolveState(LoanRequest $loanRequest): array
    {
        $computed = $this->cycleCompute->computeCycleForLoanRequest($loanRequest);

        return [
            'applicant' => [
                'locked' => true,
                'cycle_status' => $computed['cycle_status'],
                'cycle_number' => $computed['cycle_number'],
            ],
        ];
    }
}
