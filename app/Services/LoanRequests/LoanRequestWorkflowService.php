<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Notifications\LoanRequestDecisionNotification;
use App\Support\SchemaCapabilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LoanRequestWorkflowService
{
    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
        private LoanRequestDecisionService $decisionService,
        private LoanRequestLoanConversionService $loanConversionService,
    ) {}

    public function startReview(
        LoanRequest $loanRequest,
        AppUser $actor,
        ?string $remarks = null,
    ): LoanRequest {
        return DB::transaction(function () use (
            $loanRequest,
            $actor,
            $remarks,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize('startReview', $lockedLoanRequest);
            $this->ensureStatus(
                $lockedLoanRequest,
                [LoanRequestStatus::PendingReview],
                'Only pending review requests can be started.',
            );

            $before = $this->snapshotForAudit($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);
            $assignedOfficerChanged = $lockedLoanRequest->assigned_officer_id === null;

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::UnderReview,
                'assigned_officer_id' => $lockedLoanRequest->assigned_officer_id
                    ?? $actor->user_id,
            ]);
            $lockedLoanRequest->save();

            $updated = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordWorkflowAudit(
                $updated,
                $actor,
                LoanRequestChange::ACTION_START_REVIEW,
                $remarks,
                $fromStatus,
                $this->statusValue($updated),
                array_values(array_filter([
                    'status',
                    $assignedOfficerChanged ? 'assigned_officer_id' : null,
                ])),
                [
                    'remarks' => $this->normalizeOptionalText($remarks),
                    'assigned_officer_id' => $updated->assigned_officer_id,
                ],
                $before,
                $this->snapshotForAudit($updated),
            );

            return $updated;
        });
    }

    public function requestRevision(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $remarks,
    ): LoanRequest {
        return DB::transaction(function () use (
            $loanRequest,
            $actor,
            $remarks,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize(
                'requestRevision',
                $lockedLoanRequest,
            );
            $this->ensureStatus(
                $lockedLoanRequest,
                [LoanRequestStatus::PendingReview, LoanRequestStatus::UnderReview],
                'Only pending review or under review requests can be sent back for revision.',
            );

            $before = $this->snapshotForAudit($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::NeedsRevision,
                'reviewed_by' => $actor->user_id,
                'reviewed_at' => now(),
                'review_decision' => LoanRequestStatus::NeedsRevision->value,
                'review_remarks' => $remarks,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
            $lockedLoanRequest->save();

            $updated = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordWorkflowAudit(
                $updated,
                $actor,
                LoanRequestChange::ACTION_REQUEST_REVISION,
                $remarks,
                $fromStatus,
                $this->statusValue($updated),
                [
                    'status',
                    'reviewed_by',
                    'reviewed_at',
                    'review_decision',
                    'review_remarks',
                ],
                [
                    'review_decision' => LoanRequestStatus::NeedsRevision->value,
                ],
                $before,
                $this->snapshotForAudit($updated),
            );

            return $updated;
        });
    }

    public function reject(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $rejectionReason,
    ): LoanRequest {
        return DB::transaction(function () use (
            $loanRequest,
            $actor,
            $rejectionReason,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize('reject', $lockedLoanRequest);
            $this->ensureStatus(
                $lockedLoanRequest,
                [LoanRequestStatus::PendingReview, LoanRequestStatus::UnderReview],
                'Only pending review or under review requests can be rejected.',
            );

            $before = $this->snapshotForAudit($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::Rejected,
                'reviewed_by' => $actor->user_id,
                'reviewed_at' => now(),
                'review_decision' => LoanRequestStatus::Rejected->value,
                'review_remarks' => null,
                'rejected_by' => $actor->user_id,
                'rejected_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);
            $lockedLoanRequest->save();

            $updated = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordWorkflowAudit(
                $updated,
                $actor,
                LoanRequestChange::ACTION_REJECT,
                $rejectionReason,
                $fromStatus,
                $this->statusValue($updated),
                [
                    'status',
                    'reviewed_by',
                    'reviewed_at',
                    'review_decision',
                    'rejected_by',
                    'rejected_at',
                    'rejection_reason',
                ],
                [
                    'review_decision' => LoanRequestStatus::Rejected->value,
                ],
                $before,
                $this->snapshotForAudit($updated),
            );

            return $updated;
        });
    }

    public function recommendApproval(
        LoanRequest $loanRequest,
        AppUser $actor,
        ?string $reviewRemarks = null,
    ): LoanRequest {
        return DB::transaction(function () use (
            $loanRequest,
            $actor,
            $reviewRemarks,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize(
                'recommendApproval',
                $lockedLoanRequest,
            );
            $this->ensureStatus(
                $lockedLoanRequest,
                [LoanRequestStatus::UnderReview],
                'Only under review requests can be recommended for approval.',
            );

            $before = $this->snapshotForAudit($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::RecommendedForApproval,
                'reviewed_by' => $actor->user_id,
                'reviewed_at' => now(),
                'review_decision' => LoanRequestStatus::RecommendedForApproval->value,
                'review_remarks' => $this->normalizeOptionalText($reviewRemarks),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
            $lockedLoanRequest->save();

            $updated = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordWorkflowAudit(
                $updated,
                $actor,
                LoanRequestChange::ACTION_RECOMMEND_APPROVAL,
                $this->normalizeOptionalText($reviewRemarks),
                $fromStatus,
                $this->statusValue($updated),
                [
                    'status',
                    'reviewed_by',
                    'reviewed_at',
                    'review_decision',
                    'review_remarks',
                ],
                [
                    'review_decision' => LoanRequestStatus::RecommendedForApproval->value,
                ],
                $before,
                $this->snapshotForAudit($updated),
            );

            return $updated;
        });
    }

    /**
     * @param  array{
     *     approved_amount: float|int|string,
     *     approved_term: int|string,
     *     approved_interest_rate?: float|int|string|null,
     *     approval_remarks?: string|null
     * }  $payload
     */
    public function approve(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $payload,
    ): LoanRequest {
        $updated = DB::transaction(function () use (
            $loanRequest,
            $actor,
            $payload,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize('approve', $lockedLoanRequest);
            $this->ensureStatus(
                $lockedLoanRequest,
                [LoanRequestStatus::RecommendedForApproval],
                'Only recommended requests can be approved through the workflow endpoint.',
            );
            $this->decisionService->ensureCorrectedRequestReadyForApproval(
                $lockedLoanRequest,
            );

            $before = $this->snapshotForAudit($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);
            $approvalRemarks = $this->normalizeOptionalText(
                $payload['approval_remarks'] ?? null,
            );

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::Approved,
                'approved_by' => $actor->user_id,
                'approved_at' => now(),
                'approved_amount' => $payload['approved_amount'],
                'approved_term' => $payload['approved_term'],
                'approved_interest_rate' => $payload['approved_interest_rate'] ?? null,
                'approval_remarks' => $approvalRemarks,
                'decision_notes' => $approvalRemarks,
            ]);
            $lockedLoanRequest->save();

            $updatedLoanRequest = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordWorkflowAudit(
                $updatedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_APPROVE,
                $approvalRemarks,
                $fromStatus,
                $this->statusValue($updatedLoanRequest),
                [
                    'status',
                    'approved_by',
                    'approved_at',
                    'approved_amount',
                    'approved_term',
                    'approved_interest_rate',
                    'approval_remarks',
                    'decision_notes',
                ],
                [
                    'approved_amount' => $updatedLoanRequest->approved_amount,
                    'approved_term' => $updatedLoanRequest->approved_term,
                    'approved_interest_rate' => $updatedLoanRequest->approved_interest_rate,
                ],
                $before,
                $this->snapshotForAudit($updatedLoanRequest),
            );

            return $updatedLoanRequest;
        });

        $this->notifyMemberOfDecision($updated, $actor);

        return $updated;
    }

    public function decline(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $declineReason,
    ): LoanRequest {
        $updated = DB::transaction(function () use (
            $loanRequest,
            $actor,
            $declineReason,
        ): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize('decline', $lockedLoanRequest);
            $this->ensureStatus(
                $lockedLoanRequest,
                [LoanRequestStatus::RecommendedForApproval],
                'Only recommended requests can be declined through the workflow endpoint.',
            );

            $before = $this->snapshotForAudit($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::Declined,
                'declined_by' => $actor->user_id,
                'declined_at' => now(),
                'decline_reason' => $declineReason,
                'decision_notes' => $declineReason,
            ]);
            $lockedLoanRequest->save();

            $updatedLoanRequest = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordWorkflowAudit(
                $updatedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_DECLINE,
                $declineReason,
                $fromStatus,
                $this->statusValue($updatedLoanRequest),
                [
                    'status',
                    'declined_by',
                    'declined_at',
                    'decline_reason',
                    'decision_notes',
                ],
                [],
                $before,
                $this->snapshotForAudit($updatedLoanRequest),
            );

            return $updatedLoanRequest;
        });

        $this->notifyMemberOfDecision($updated, $actor);

        return $updated;
    }

    /**
     * @return array{
     *     loanRequest: \App\Models\LoanRequest,
     *     loan: array{
     *         loan_id: string,
     *         loan_number: string,
     *         loan_status: string,
     *         ledger_control_no: string|null,
     *         ledger_trans_no: string|null
     *     }
     * }
     */
    public function convertToLoan(
        LoanRequest $loanRequest,
        AppUser $actor,
        ?string $remarks = null,
    ): array {
        return DB::transaction(function () use (
            $loanRequest,
            $actor,
            $remarks,
        ): array {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);

            Gate::forUser($actor)->authorize(
                'convertToLoan',
                $lockedLoanRequest,
            );
            $this->ensureStatus(
                $lockedLoanRequest,
                [LoanRequestStatus::Approved],
                'Only approved requests can be converted to actual loans.',
            );

            $before = $this->snapshotForAudit($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);
            $normalizedRemarks = $this->normalizeOptionalText($remarks);
            $decisionNotesChanged = $normalizedRemarks !== null
                && $normalizedRemarks !== $lockedLoanRequest->decision_notes;
            $loanMetadata = $this->loanConversionService
                ->createLoanForApprovedRequest($lockedLoanRequest, $actor);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::ConvertedToLoan,
                'decision_notes' => $normalizedRemarks ?? $lockedLoanRequest->decision_notes,
            ]);
            $lockedLoanRequest->save();

            $updatedLoanRequest = $this->refreshLoanRequest($lockedLoanRequest);

            $this->recordWorkflowAudit(
                $updatedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_CONVERT_TO_LOAN,
                $normalizedRemarks,
                $fromStatus,
                $this->statusValue($updatedLoanRequest),
                array_values(array_filter([
                    'status',
                    $decisionNotesChanged ? 'decision_notes' : null,
                ])),
                $loanMetadata,
                $before,
                $this->snapshotForAudit($updatedLoanRequest),
            );

            return [
                'loanRequest' => $updatedLoanRequest,
                'loan' => $loanMetadata,
            ];
        }, attempts: 5);
    }

    private function lockLoanRequest(LoanRequest $loanRequest): LoanRequest
    {
        return LoanRequest::query()
            ->whereKey($loanRequest->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  list<LoanRequestStatus>  $allowedStatuses
     */
    private function ensureStatus(
        LoanRequest $loanRequest,
        array $allowedStatuses,
        string $message,
    ): void {
        $status = $this->statusValue($loanRequest);
        $allowedValues = array_map(
            static fn (LoanRequestStatus $allowedStatus): string => $allowedStatus->value,
            $allowedStatuses,
        );

        if (! in_array($status, $allowedValues, true)) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }

    private function refreshLoanRequest(LoanRequest $loanRequest): LoanRequest
    {
        return $loanRequest->refresh()->loadMissing(
            'assignedOfficer.adminProfile',
            'reviewedBy.adminProfile',
            'rejectedBy.adminProfile',
            'approvedBy.adminProfile',
            'declinedBy.adminProfile',
            'cancelledBy',
            'user',
        );
    }

    private function notifyMemberOfDecision(
        LoanRequest $loanRequest,
        AppUser $actor,
    ): void {
        $loanRequest->loadMissing('user');

        $member = $loanRequest->user;

        if ($member === null || ! $member->hasMemberAccess()) {
            return;
        }

        $member->notify(new LoanRequestDecisionNotification($loanRequest, $actor));
    }

    /**
     * @param  list<string>  $changedFields
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordWorkflowAudit(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $action,
        ?string $reason,
        string $fromStatus,
        string $toStatus,
        array $changedFields,
        array $metadata,
        array $before,
        array $after,
    ): void {
        if (! $this->schemaCapabilities->hasTable('loan_request_changes')) {
            return;
        }

        $audit = [
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => $action,
            'reason' => $reason ?? '',
            'before_json' => $before,
            'after_json' => $after,
            'changed_fields_json' => $changedFields,
        ];

        if ($this->schemaCapabilities->hasColumn('loan_request_changes', 'from_status')) {
            $audit['from_status'] = $fromStatus;
        }

        if ($this->schemaCapabilities->hasColumn('loan_request_changes', 'to_status')) {
            $audit['to_status'] = $toStatus;
        }

        if ($this->schemaCapabilities->hasColumn('loan_request_changes', 'metadata_json')) {
            $audit['metadata_json'] = $metadata !== [] ? $metadata : null;
        }

        LoanRequestChange::query()->create($audit);
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
            'assigned_officer_id' => $loanRequest->assigned_officer_id,
            'reviewed_by' => $loanRequest->reviewed_by,
            'reviewed_at' => $loanRequest->reviewed_at?->toDateTimeString(),
            'review_decision' => $loanRequest->review_decision,
            'review_remarks' => $loanRequest->review_remarks,
            'rejected_by' => $loanRequest->rejected_by,
            'rejected_at' => $loanRequest->rejected_at?->toDateTimeString(),
            'rejection_reason' => $loanRequest->rejection_reason,
            'approved_by' => $loanRequest->approved_by,
            'approved_at' => $loanRequest->approved_at?->toDateTimeString(),
            'approval_remarks' => $loanRequest->approval_remarks,
            'approved_amount' => $loanRequest->approved_amount,
            'approved_term' => $loanRequest->approved_term,
            'approved_interest_rate' => $loanRequest->approved_interest_rate,
            'decision_notes' => $loanRequest->decision_notes,
            'declined_by' => $loanRequest->declined_by,
            'declined_at' => $loanRequest->declined_at?->toDateTimeString(),
            'decline_reason' => $loanRequest->decline_reason,
        ];
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
    }

    private function normalizeOptionalText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }
}
