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

        return $this->canMonitorOtherUsersRequest($user);
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
        return $this->canStartReviewWorkflow(
            $user,
            $loanRequest,
        );
    }

    public function claim(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_CLAIM,
        ) && $this->isAssignableOperationalStatus($loanRequest);
    }

    public function manageAssignment(AppUser $user, LoanRequest $loanRequest): bool
    {
        if (! $user->hasActiveStaffAccess()) {
            return false;
        }

        if (! $this->isAssignableOperationalStatus($loanRequest)) {
            return false;
        }

        return $user->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT)
            || $user->isLegacySuperadmin();
    }

    public function returnToQueue(AppUser $user, LoanRequest $loanRequest): bool
    {
        if (! $this->isAssignableOperationalStatus($loanRequest)) {
            return false;
        }

        if (
            $user->hasActiveStaffAccess()
            && (
                $user->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT)
                || $user->isLegacySuperadmin()
            )
        ) {
            return $loanRequest->assigned_officer_id !== null;
        }

        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_RETURN_TO_QUEUE,
        ) && $loanRequest->assigned_officer_id === $user->user_id;
    }

    public function requestRevision(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAssignedRequest(
            $user,
            $loanRequest,
            Permission::LOAN_REQUEST_REVISION,
        ) && in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
        ], true);
    }

    public function updateProcessingDetails(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        return $this->canActOnAssignedRequest(
            $user,
            $loanRequest,
            Permission::LOAN_REVIEW,
        ) && in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::NeedsRevision->value,
            LoanRequestStatus::AwaitingMemberInformation->value,
        ], true);
    }

    public function requestMemberAction(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        return $this->updateProcessingDetails($user, $loanRequest);
    }

    public function rejectDuringProcessing(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        return $this->canActOnAssignedRequest(
            $user,
            $loanRequest,
            Permission::LOAN_REJECT,
        ) && in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::NeedsRevision->value,
            LoanRequestStatus::AwaitingMemberInformation->value,
        ], true);
    }

    public function reject(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAssignedRequest(
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
        return $this->canActOnAssignedRequest(
            $user,
            $loanRequest,
            Permission::LOAN_RECOMMEND_APPROVAL,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::UnderReview->value;
    }

    public function generateDocuments(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->updateProcessingDetails($user, $loanRequest);
    }

    public function approve(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_APPROVE,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::RecommendedForApproval->value;
    }

    public function returnForProcessing(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        if (! $user->hasActiveStaffAccess() || $this->ownsLoanRequest($user, $loanRequest)) {
            return false;
        }

        if (! in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::RecommendedForApproval->value,
            LoanRequestStatus::AwaitingMemberAcceptance->value,
        ], true)) {
            return false;
        }

        return $user->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT)
            || $user->hasPermission(Permission::LOAN_APPROVE)
            || $user->hasPermission(Permission::LOAN_DECLINE)
            || $user->isLegacySuperadmin();
    }

    public function reopenRejectedRequest(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_MANAGE_ASSIGNMENT,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::Rejected->value;
    }

    public function upgradeWorkflowVersion(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_MANAGE_ASSIGNMENT,
        );
    }

    public function respondToMemberAction(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        return $this->ownsLoanRequest($user, $loanRequest)
            && in_array($this->statusValue($loanRequest), [
                LoanRequestStatus::AwaitingMemberInformation->value,
                LoanRequestStatus::AwaitingMemberAcceptance->value,
            ], true);
    }

    public function decline(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_DECLINE,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::RecommendedForApproval->value;
    }

    public function markForWibsEncoding(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_WIBS_ENCODE,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::ConvertedToLoan->value;
    }

    public function recordWibsReference(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_WIBS_ENCODE,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::ForWibsEncoding->value;
    }

    public function scheduleWibsRelease(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_WIBS_ENCODE,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::WibsLoanCreated->value;
    }

    public function confirmWibsRelease(AppUser $user, LoanRequest $loanRequest): bool
    {
        return $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_WIBS_ENCODE,
        ) && $this->statusValue($loanRequest) === LoanRequestStatus::ReleaseScheduled->value;
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
            && $user->hasActiveStaffAccess()
            && $user->hasPermission($permission);
    }

    private function canActOnAssignedRequest(
        AppUser $user,
        LoanRequest $loanRequest,
        string $permission,
    ): bool {
        return $this->canActOnAnotherUsersRequest($user, $loanRequest, $permission)
            && $loanRequest->assigned_officer_id === $user->user_id;
    }

    private function canStartReviewWorkflow(
        AppUser $user,
        LoanRequest $loanRequest,
    ): bool {
        if (! $this->canActOnAnotherUsersRequest(
            $user,
            $loanRequest,
            Permission::LOAN_REVIEW,
        )) {
            return false;
        }

        if ($this->statusValue($loanRequest) !== LoanRequestStatus::PendingReview->value) {
            return false;
        }

        return $loanRequest->assigned_officer_id === null
            || $loanRequest->assigned_officer_id === $user->user_id;
    }

    private function canMonitorOtherUsersRequest(AppUser $user): bool
    {
        if (! $user->hasActiveStaffAccess()) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return $user->hasPermission(Permission::LOAN_VIEW)
                || $user->isLegacySuperadmin();
        }

        return $user->hasPermission(Permission::LOAN_VIEW)
            && $user->hasAnyRole([
                Role::SUPERADMIN,
                Role::LOAN_PROCESSOR,
                Role::LOAN_MANAGER,
            ]);
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

    private function isAssignableOperationalStatus(LoanRequest $loanRequest): bool
    {
        return in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::NeedsRevision->value,
        ], true);
    }
}
