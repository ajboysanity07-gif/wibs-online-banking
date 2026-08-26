<?php

namespace App\Services\LoanRequests;

use App\Models\LoanRequest;
use App\Models\MemberDependentProfile;

/**
 * Resolves the Group Life Insurance (Generali/Grepalife) cycle status
 * (New/Old + cycle number) for the applicant, spouse, and each dependent
 * slot of a loan request's owning member.
 *
 * Cycle values are auto-computed from the member's wlnmaster loan history
 * via LoanRequestCycleComputeService. All slots are read-only (locked).
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

        $state = [
            'applicant' => [
                'locked' => true,
                'cycle_status' => $computed['cycle_status'],
                'cycle_number' => $computed['cycle_number'],
            ],
            'spouse' => [
                'locked' => true,
                'cycle_status' => $computed['cycle_status'],
                'cycle_number' => $computed['cycle_number'],
            ],
        ];

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                $state["{$category}_{$slot}"] = [
                    'locked' => true,
                    'cycle_status' => $computed['cycle_status'],
                    'cycle_number' => $computed['cycle_number'],
                ];
            }
        }

        return $state;
    }
}
