<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Notifications\LoanRequestDecisionNotification;
use Illuminate\Validation\ValidationException;

class LoanRequestDecisionService
{
    /**
     * @param  array{approved_amount: float|int|string, approved_term: int|string, decision_notes?: string|null}  $payload
     */
    public function approve(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $payload,
    ): LoanRequest {
        $this->ensureDecisionable($loanRequest, $actor);

        $loanRequest->fill([
            'status' => LoanRequestStatus::Approved,
            'reviewed_by' => $actor->user_id,
            'reviewed_at' => now(),
            'approved_amount' => $payload['approved_amount'],
            'approved_term' => $payload['approved_term'],
            'decision_notes' => $payload['decision_notes'] ?? null,
        ]);

        $loanRequest->save();

        $this->notifyMember($loanRequest);

        return $loanRequest->refresh()->loadMissing('reviewedBy');
    }

    public function decline(
        LoanRequest $loanRequest,
        AppUser $actor,
        ?string $decisionNotes = null,
    ): LoanRequest {
        $this->ensureDecisionable($loanRequest, $actor);

        $loanRequest->fill([
            'status' => LoanRequestStatus::Declined,
            'reviewed_by' => $actor->user_id,
            'reviewed_at' => now(),
            'approved_amount' => null,
            'approved_term' => null,
            'decision_notes' => $decisionNotes,
        ]);

        $loanRequest->save();

        $this->notifyMember($loanRequest);

        return $loanRequest->refresh()->loadMissing('reviewedBy');
    }

    public function canDecide(LoanRequest $loanRequest, AppUser $actor): bool
    {
        if (! $this->isUnderReview($loanRequest)) {
            return false;
        }

        return ! $this->isSelfDecision($loanRequest, $actor);
    }

    public function isOwnRequest(LoanRequest $loanRequest, AppUser $actor): bool
    {
        return $this->isSelfDecision($loanRequest, $actor);
    }

    private function ensureDecisionable(LoanRequest $loanRequest, AppUser $actor): void
    {
        if (! $this->isUnderReview($loanRequest)) {
            throw ValidationException::withMessages([
                'status' => 'Only under review requests can be decided.',
            ]);
        }

        if ($this->isSelfDecision($loanRequest, $actor)) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot decide your own loan request.',
            ]);
        }
    }

    private function isUnderReview(LoanRequest $loanRequest): bool
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return $status === LoanRequestStatus::UnderReview->value;
    }

    private function isSelfDecision(LoanRequest $loanRequest, AppUser $actor): bool
    {
        if ($loanRequest->user_id !== null && $loanRequest->user_id === $actor->user_id) {
            return true;
        }

        $requestAcctno = $loanRequest->acctno;
        $actorAcctno = $actor->acctno;

        if ($requestAcctno === null || $actorAcctno === null) {
            return false;
        }

        $requestAcctno = trim((string) $requestAcctno);
        $actorAcctno = trim((string) $actorAcctno);

        if ($requestAcctno === '' || $actorAcctno === '') {
            return false;
        }

        return $requestAcctno === $actorAcctno;
    }

    private function notifyMember(LoanRequest $loanRequest): void
    {
        $loanRequest->loadMissing('user');

        $member = $loanRequest->user;

        if ($member === null || ! $member->hasMemberAccess()) {
            return;
        }

        $member->notify(new LoanRequestDecisionNotification(
            $loanRequest,
            $loanRequest->reviewedBy,
        ));
    }
}
