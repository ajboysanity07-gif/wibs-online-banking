<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Notifications\LoanRequestCancelledNotification;
use App\Notifications\LoanRequestDecisionNotification;
use App\Support\SchemaCapabilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanRequestDecisionService
{
    private const CORRECTION_REQUIRED_MESSAGE = 'Please review and save the correction before approving this admin-corrected request.';

    private const CORRECTION_AUDIT_UNAVAILABLE_MESSAGE = 'Correction audit history is unavailable. Please save the correction before approving this admin-corrected request.';

    public function __construct(
        private LoanRequestCorrectionReportService $correctionReports,
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    /**
     * @param  array{approved_amount: float|int|string, approved_term: int|string, decision_notes?: string|null}  $payload
     */
    public function approve(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $payload,
    ): LoanRequest {
        $this->ensureDecisionable($loanRequest, $actor);
        $this->ensureCorrectedRequestReadyForApproval($loanRequest);

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

    public function cancelApprovedRequest(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $cancellationReason,
    ): LoanRequest {
        $updated = DB::transaction(function () use ($loanRequest, $actor, $cancellationReason): LoanRequest {
            $loanRequest->refresh();
            $this->ensureCancellable($loanRequest, $actor);
            $this->ensureNoGeneratedLoanRecords($loanRequest);

            $before = $this->snapshotForAudit($loanRequest);

            $loanRequest->fill([
                'status' => LoanRequestStatus::Cancelled,
                'cancelled_by' => $actor->user_id,
                'cancelled_at' => now(),
                'cancellation_reason' => $cancellationReason,
            ]);

            $loanRequest->save();
            $loanRequest->refresh();

            $this->recordCancellationAudit(
                $loanRequest,
                $actor,
                $cancellationReason,
                $before,
                $this->snapshotForAudit($loanRequest),
            );

            $this->correctionReports->resolveOpenReports(
                $loanRequest,
                $actor,
                $cancellationReason,
            );

            return $loanRequest->loadMissing('reviewedBy', 'cancelledBy');
        });

        $this->notifyMemberOfCancellation($updated, $actor);

        return $updated;
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

    public function requiresSavedCorrectionBeforeApproval(
        LoanRequest $loanRequest,
    ): bool {
        if ($loanRequest->corrected_from_id === null) {
            return false;
        }

        if (! $this->isUnderReview($loanRequest)) {
            return false;
        }

        return ! $this->hasSavedCorrectionAfterCreation($loanRequest);
    }

    public function hasSavedCorrectionAfterCreation(LoanRequest $loanRequest): bool
    {
        if ($loanRequest->corrected_from_id === null) {
            return false;
        }

        if (! $this->isCorrectionAuditHistoryAvailable()) {
            return false;
        }

        $creationAuditId = LoanRequestChange::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where(
                'action',
                LoanRequestChange::ACTION_ADMIN_CREATE_CORRECTED_REQUEST,
            )
            ->max('id');

        $changes = LoanRequestChange::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where(
                'action',
                LoanRequestChange::ACTION_ADMIN_UPDATE_CORRECTED_REQUEST_DETAILS,
            )
            ->orderBy('id')
            ->get();

        return $changes->contains(
            function (LoanRequestChange $change) use ($creationAuditId): bool {
                if ($creationAuditId !== null && $change->id <= $creationAuditId) {
                    return false;
                }

                return $this->changeReflectsSavedCorrection($change);
            },
        );
    }

    public function ensureCorrectedRequestReadyForApproval(
        LoanRequest $loanRequest,
    ): void {
        if ($loanRequest->corrected_from_id === null) {
            return;
        }

        if (! $this->isUnderReview($loanRequest)) {
            return;
        }

        if (! $this->isCorrectionAuditHistoryAvailable()) {
            throw ValidationException::withMessages([
                'approval' => self::CORRECTION_AUDIT_UNAVAILABLE_MESSAGE,
            ]);
        }

        if (! $this->hasSavedCorrectionAfterCreation($loanRequest)) {
            throw ValidationException::withMessages([
                'approval' => self::CORRECTION_REQUIRED_MESSAGE,
            ]);
        }
    }

    private function ensureCancellable(LoanRequest $loanRequest, AppUser $actor): void
    {
        if (! $this->isApproved($loanRequest)) {
            throw ValidationException::withMessages([
                'status' => 'Only approved requests can be cancelled.',
            ]);
        }

        if ($this->isSelfDecision($loanRequest, $actor)) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot cancel your own loan request.',
            ]);
        }
    }

    private function ensureNoGeneratedLoanRecords(LoanRequest $loanRequest): void
    {
        /**
         * TODO: Block cancellation here when approved requests persist a reliable generated
         * loan account, ledger, disbursement, or payment schedule reference.
         */
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
        return $this->statusValue($loanRequest) === LoanRequestStatus::UnderReview->value;
    }

    private function isApproved(LoanRequest $loanRequest): bool
    {
        return $this->statusValue($loanRequest) === LoanRequestStatus::Approved->value;
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
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

    private function notifyMemberOfCancellation(
        LoanRequest $loanRequest,
        AppUser $actor,
    ): void {
        $loanRequest->loadMissing('user');

        $member = $loanRequest->user;

        if ($member === null || ! $member->hasMemberAccess()) {
            return;
        }

        $member->notify(new LoanRequestCancelledNotification($loanRequest, $actor));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordCancellationAudit(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $reason,
        array $before,
        array $after,
    ): void {
        if (! $this->schemaCapabilities->hasTable('loan_request_changes')) {
            return;
        }

        LoanRequestChange::query()->create([
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => LoanRequestChange::ACTION_CANCEL_APPROVED_REQUEST,
            'reason' => $reason,
            'before_json' => $before,
            'after_json' => $after,
            'changed_fields_json' => [
                'status',
                'cancelled_by',
                'cancelled_at',
                'cancellation_reason',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotForAudit(LoanRequest $loanRequest): array
    {
        return [
            'id' => $loanRequest->id,
            'user_id' => $loanRequest->user_id,
            'acctno' => $loanRequest->acctno,
            'status' => $this->statusValue($loanRequest),
            'requested_amount' => $loanRequest->requested_amount,
            'requested_term' => $loanRequest->requested_term,
            'reviewed_by' => $loanRequest->reviewed_by,
            'reviewed_at' => $loanRequest->reviewed_at?->toDateTimeString(),
            'approved_amount' => $loanRequest->approved_amount,
            'approved_term' => $loanRequest->approved_term,
            'decision_notes' => $loanRequest->decision_notes,
            'cancelled_by' => $loanRequest->cancelled_by,
            'cancelled_at' => $loanRequest->cancelled_at?->toDateTimeString(),
            'cancellation_reason' => $loanRequest->cancellation_reason,
        ];
    }

    private function isCorrectionAuditHistoryAvailable(): bool
    {
        return $this->schemaCapabilities->hasTable('loan_request_changes')
            && $this->schemaCapabilities->hasColumn(
                'loan_request_changes',
                'action',
            );
    }

    private function changeReflectsSavedCorrection(
        LoanRequestChange $change,
    ): bool {
        $reason = trim((string) $change->reason);

        if ($reason === '') {
            return false;
        }

        $changedFields = array_values(array_filter(
            $change->changed_fields_json ?? [],
            fn (mixed $field): bool => is_string($field) && trim($field) !== '',
        ));

        if ($changedFields !== []) {
            return true;
        }

        return $change->before_json !== $change->after_json;
    }
}
