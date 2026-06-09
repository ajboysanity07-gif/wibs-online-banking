<?php

namespace App\Policies;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Permission;
use App\Models\Role;

class LoanRequestPolicy
{
    public function viewAny(AppUser $user): bool
    {
        return $user->hasPermission(Permission::LOAN_VIEW);
    }

    public function view(AppUser $user, LoanRequest $loanRequest): bool
    {
        if ($this->ownsLoanRequest($user, $loanRequest)) {
            return $user->hasPermission(Permission::LOAN_VIEW);
        }

        return $user->hasPermission(Permission::LOAN_VIEW)
            && $user->hasAnyRole([
                Role::ADMIN,
                Role::LOAN_OFFICER,
                Role::LOAN_MANAGER,
            ]);
    }

    public function create(AppUser $user): bool
    {
        return $user->hasMemberAccess()
            && $user->hasPermission(Permission::LOAN_CREATE);
    }

    public function update(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->resubmit($user, $loanRequest);
    }

    public function resubmit(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $user->hasMemberAccess()
            && $user->hasPermission(Permission::LOAN_CREATE)
            && $this->ownsLoanRequest($user, $loanRequest)
            && $this->statusValue($loanRequest) === LoanRequestStatus::NeedsRevision->value;
    }

    public function startReview(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_REVIEW,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::PendingReview->value;
    }

    public function requestRevision(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_REQUEST_REVISION,
        ) && in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
        ], true);
    }

    public function reject(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_REJECT,
        ) && in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
        ], true);
    }

    public function recommendApproval(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_RECOMMEND_APPROVAL,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::UnderReview->value;
    }

    public function approve(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_APPROVE,
        ) && (
            $this->statusValue($loanRequest) === LoanRequestStatus::RecommendedForApproval->value
            || $this->canUseLegacyAdminDecisionPath($user, $loanRequest)
        );
    }

    public function decline(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_DECLINE,
        ) && (
            $this->statusValue($loanRequest) === LoanRequestStatus::RecommendedForApproval->value
            || $this->canUseLegacyAdminDecisionPath($user, $loanRequest)
        );
    }

    public function convertToLoan(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_CONVERT_TO_LOAN,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::Approved->value;
    }

    public function delete(AppUser $user, LoanRequest $loanRequest): bool
    {
        return false;
    }

    public function restore(AppUser $user, LoanRequest $loanRequest): bool
    {
        return false;
    }

    public function forceDelete(AppUser $user, LoanRequest $loanRequest): bool
    {
        return false;
    }

    private function canActOnAnotherUsersRequest(
        AppUser $user,
        LoanRequest $loanRequest,
        string $permission,
    ): bool {
        return ! $this->ownsLoanRequest($user, $loanRequest)
            && $user->hasPermission($permission);
    }

    private function canUseLegacyAdminDecisionPath(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        return $user->hasRole(Role::ADMIN)
            && LoanRequestStatus::normalizeValue($loanRequest->status)
                === LoanRequestStatus::UnderReview->value;
    }

    private function ownsLoanRequest(AppUser $user, LoanRequest $loanRequest): bool
    {
        if ($loanRequest->user_id !== null && $loanRequest->user_id === $user->user_id) {
            return true;
        }

        $requestAcctno = trim((string) ($loanRequest->acctno ?? ''));
        $userAcctno = trim((string) ($user->acctno ?? ''));

        if ($requestAcctno === '' || $userAcctno === '') {
            return false;
        }

        return $requestAcctno === $userAcctno;
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
    }
}
