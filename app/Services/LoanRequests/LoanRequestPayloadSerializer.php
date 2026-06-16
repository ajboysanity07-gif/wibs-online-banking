<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestCorrectionReport;
use App\Models\LoanRequestNotificationEvent;
use App\Models\LoanRequestPerson;
use App\Support\LocationComposer;
use App\Support\SchemaCapabilities;
use DateTimeInterface;
use Illuminate\Support\Str;

class LoanRequestPayloadSerializer
{
    private const CIVIL_STATUS_OPTIONS = [
        'Single',
        'Married',
        'Separated',
        'Widowed',
    ];

    private const PAYDAY_OPTIONS = [
        'Weekly',
        '15th',
        '30th',
        '15th & 30th',
        'Bi-Weekly',
        'Monthly',
    ];

    private const AUDIT_ACTION_LABELS = [
        'submitted' => 'Submitted',
        LoanRequestChange::ACTION_START_REVIEW => 'Review Started',
        LoanRequestChange::ACTION_ASSIGNMENT_CLAIMED => 'Claimed',
        LoanRequestChange::ACTION_ASSIGNMENT_ASSIGNED => 'Assigned',
        LoanRequestChange::ACTION_ASSIGNMENT_REASSIGNED => 'Reassigned',
        LoanRequestChange::ACTION_ASSIGNMENT_RETURNED_TO_QUEUE => 'Returned to Queue',
        LoanRequestChange::ACTION_ASSIGNMENT_UNASSIGNED_STAFF_UNAVAILABLE => 'Returned to Queue',
        LoanRequestChange::ACTION_REQUEST_REVISION => 'Revision Requested',
        LoanRequestChange::ACTION_REQUEST_MEMBER_INFORMATION => 'Member Information Requested',
        LoanRequestChange::ACTION_REJECT => 'Rejected',
        LoanRequestChange::ACTION_REJECT_DURING_PROCESSING => 'Rejected During Processing',
        LoanRequestChange::ACTION_REOPEN_REJECTED_REQUEST => 'Reopened',
        LoanRequestChange::ACTION_RECOMMEND_APPROVAL => 'Recommended for Approval',
        LoanRequestChange::ACTION_GENERATE_REQUIRED_DOCUMENTS => 'Documents Generated',
        LoanRequestChange::ACTION_REGENERATE_DOCUMENT => 'Document Regenerated',
        LoanRequestChange::ACTION_PROCESSING_DETAILS_UPDATED => 'Processing Details Updated',
        LoanRequestChange::ACTION_APPROVE => 'Approved',
        LoanRequestChange::ACTION_DECLINE => 'Declined',
        LoanRequestChange::ACTION_MANAGER_RETURN_FOR_PROCESSING => 'Returned for Processing',
        LoanRequestChange::ACTION_MANAGER_CHANGED_TERMS => 'Terms Changed',
        LoanRequestChange::ACTION_MEMBER_CONFIRMED_INFORMATION => 'Member Information Confirmed',
        LoanRequestChange::ACTION_MEMBER_ACCEPTED_TERMS => 'Terms Accepted',
        LoanRequestChange::ACTION_MEMBER_DECLINED_TERMS => 'Terms Declined',
        LoanRequestChange::ACTION_WORKFLOW_UPGRADED => 'Workflow Upgraded',
        LoanRequestChange::ACTION_CONVERT_TO_LOAN => 'Converted to Loan',
        LoanRequestChange::ACTION_CANCEL_REQUEST => 'Cancelled',
        LoanRequestChange::ACTION_CANCEL_APPROVED_REQUEST => 'Cancelled',
        LoanRequestChange::ACTION_CREATE_CORRECTED_REQUEST => 'Corrected Request Created',
        LoanRequestChange::ACTION_ADMIN_CREATE_CORRECTED_REQUEST => 'Admin-Corrected Request Created',
        LoanRequestChange::ACTION_ADMIN_UPDATE_CORRECTED_REQUEST_DETAILS => 'Corrected Request Updated',
    ];

    private const AUDIT_STATUS_LABELS = [
        LoanRequestStatus::Draft->value => 'Draft',
        LoanRequestStatus::PendingCoMakerSignatures->value => 'Legacy Pending Co-Maker Signatures',
        LoanRequestStatus::Submitted->value => 'Legacy Submitted',
        LoanRequestStatus::PendingReview->value => 'Pending Processing',
        LoanRequestStatus::UnderReview->value => 'In Processing',
        LoanRequestStatus::NeedsRevision->value => 'Awaiting Member Correction',
        LoanRequestStatus::AwaitingMemberInformation->value => 'Awaiting Member Information',
        LoanRequestStatus::RecommendedForApproval->value => 'For Loan Manager Review',
        LoanRequestStatus::AwaitingMemberAcceptance->value => 'Awaiting Member Acceptance',
        LoanRequestStatus::Rejected->value => 'Rejected During Processing',
        LoanRequestStatus::Approved->value => 'Approved - For WIBS Processing',
        LoanRequestStatus::Declined->value => 'Declined by Loan Manager',
        LoanRequestStatus::MemberDeclinedTerms->value => 'Member Declined Revised Terms',
        LoanRequestStatus::ConvertedToLoan->value => 'Converted to Loan',
        LoanRequestStatus::Cancelled->value => 'Cancelled',
    ];

    private const MEMBER_VISIBLE_AUDIT_ACTIONS = [
        'submitted',
        LoanRequestChange::ACTION_START_REVIEW,
        LoanRequestChange::ACTION_REQUEST_REVISION,
        LoanRequestChange::ACTION_REQUEST_MEMBER_INFORMATION,
        LoanRequestChange::ACTION_REJECT,
        LoanRequestChange::ACTION_REJECT_DURING_PROCESSING,
        LoanRequestChange::ACTION_RECOMMEND_APPROVAL,
        LoanRequestChange::ACTION_APPROVE,
        LoanRequestChange::ACTION_DECLINE,
        LoanRequestChange::ACTION_REOPEN_REJECTED_REQUEST,
        LoanRequestChange::ACTION_MEMBER_ACCEPTED_TERMS,
        LoanRequestChange::ACTION_MEMBER_DECLINED_TERMS,
        LoanRequestChange::ACTION_CONVERT_TO_LOAN,
    ];

    private const MEMBER_VISIBLE_REASON_ACTIONS = [
        LoanRequestChange::ACTION_REQUEST_REVISION,
        LoanRequestChange::ACTION_REQUEST_MEMBER_INFORMATION,
        LoanRequestChange::ACTION_REJECT,
        LoanRequestChange::ACTION_REJECT_DURING_PROCESSING,
        LoanRequestChange::ACTION_DECLINE,
    ];

    private const AUDIT_METADATA_LABELS = [
        'loan_number' => 'Loan Number',
        'loan_status' => 'Loan Status',
        'ledger_control_no' => 'Ledger Control No.',
        'ledger_trans_no' => 'Ledger Transaction No.',
    ];

    public function __construct(
        private LoanRequestDecisionService $decisionService,
        private SchemaCapabilities $schemaCapabilities,
    ) {}

    /**
     * @return array{
     *     loanRequest: array<string, mixed>,
     *     applicant: array<string, mixed>,
     *     coMakerOne: array<string, mixed>,
     *     coMakerTwo: array<string, mixed>
     * }
     */
    public function serializeDetail(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('people', 'reviewedBy', 'cancelledBy');

        return [
            'loanRequest' => $this->serializeLoanRequest($loanRequest),
            'applicant' => $this->serializePerson(
                $loanRequest,
                LoanRequestPersonRole::Applicant,
            ),
            'coMakerOne' => $this->serializePerson(
                $loanRequest,
                LoanRequestPersonRole::CoMakerOne,
            ),
            'coMakerTwo' => $this->serializePerson(
                $loanRequest,
                LoanRequestPersonRole::CoMakerTwo,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeAuditTrail(LoanRequest $loanRequest): array
    {
        return $this->buildAuditTrail($loanRequest, false);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeMemberAuditTrail(LoanRequest $loanRequest): array
    {
        return $this->buildAuditTrail($loanRequest, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeLoanRequest(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing(
            'assignedOfficer.adminProfile',
            'reviewedBy.adminProfile',
            'rejectedBy.adminProfile',
            'approvedBy.adminProfile',
            'declinedBy.adminProfile',
            'cancelledBy',
            'correctedFrom',
            'correctedRequests',
        );
        $correctedRequest = $this->resolveCorrectedRequest($loanRequest);
        $correctionSaved = $loanRequest->corrected_from_id !== null
            ? $this->decisionService->hasSavedCorrectionAfterCreation(
                $loanRequest,
            )
            : false;
        $requiresCorrectionBeforeApproval = $this->decisionService
            ->requiresSavedCorrectionBeforeApproval($loanRequest);

        return [
            'id' => $loanRequest->id,
            'reference' => $loanRequest->reference,
            'status' => $this->normalizeStatus($loanRequest),
            'typecode' => $loanRequest->typecode,
            'loan_type_label_snapshot' => $loanRequest->loan_type_label_snapshot,
            'requested_amount' => $loanRequest->requested_amount,
            'requested_term' => $loanRequest->requested_term,
            'loan_purpose' => $loanRequest->loan_purpose,
            'availment_status' => $loanRequest->availment_status,
            'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
            'assigned_officer_id' => $loanRequest->assigned_officer_id,
            'assigned_processor_id' => $loanRequest->assigned_officer_id,
            'assigned_officer' => $this->serializeActor($loanRequest->assignedOfficer),
            'assigned_processor' => $this->serializeActor($loanRequest->assignedOfficer),
            'workflow_version' => $loanRequest->workflow_version?->value
                ?? (string) $loanRequest->workflow_version,
            'recommended_amount' => $loanRequest->recommended_amount,
            'recommended_term' => $loanRequest->recommended_term,
            'recommended_interest_rate' => $loanRequest->recommended_interest_rate,
            'recommended_payment_frequency' => $loanRequest->recommended_payment_frequency,
            'recommendation_remarks' => $loanRequest->recommendation_remarks,
            'reviewed_by' => $this->serializeActor($loanRequest->reviewedBy),
            'reviewed_at' => $loanRequest->reviewed_at?->toDateTimeString(),
            'review_decision' => $loanRequest->review_decision,
            'review_remarks' => $loanRequest->review_remarks,
            'review_rejection_category' => $loanRequest->review_rejection_category,
            'rejected_by' => $this->serializeActor($loanRequest->rejectedBy),
            'rejected_at' => $loanRequest->rejected_at?->toDateTimeString(),
            'rejection_reason' => $loanRequest->rejection_reason,
            'approved_by' => $this->serializeActor($loanRequest->approvedBy),
            'approved_at' => $loanRequest->approved_at?->toDateTimeString(),
            'approval_remarks' => $loanRequest->approval_remarks,
            'approved_amount' => $loanRequest->approved_amount,
            'approved_term' => $loanRequest->approved_term,
            'approved_interest_rate' => $loanRequest->approved_interest_rate,
            'decision_notes' => $loanRequest->decision_notes,
            'declined_by' => $this->serializeActor($loanRequest->declinedBy),
            'declined_at' => $loanRequest->declined_at?->toDateTimeString(),
            'decline_category' => $loanRequest->decline_category,
            'decline_reason' => $loanRequest->decline_reason,
            'member_action_type' => $loanRequest->member_action_type,
            'member_action_message' => $loanRequest->member_action_message,
            'member_action_fields' => $loanRequest->member_action_fields_json,
            'member_action_requested_at' => $loanRequest->member_action_requested_at?->toDateTimeString(),
            'member_action_resolved_at' => $loanRequest->member_action_resolved_at?->toDateTimeString(),
            'cancelled_by' => $this->serializeActor($loanRequest->cancelledBy),
            'cancelled_at' => $loanRequest->cancelled_at?->toDateTimeString(),
            'cancellation_reason' => $loanRequest->cancellation_reason,
            'corrected_from_id' => $loanRequest->corrected_from_id,
            'corrected_from_reference' => $loanRequest->correctedFrom?->reference,
            'corrected_request_id' => $correctedRequest?->id,
            'corrected_request_reference' => $correctedRequest?->reference,
            'corrected_request_status' => $correctedRequest !== null
                ? $this->normalizeStatus($correctedRequest)
                : null,
            'correction_saved' => $correctionSaved,
            'requires_correction_before_approval' => $requiresCorrectionBeforeApproval,
            'acctno' => $loanRequest->acctno,
        ];
    }

    /**
     * @return array{user_id: int, name: string, display_code: string}|null
     */
    private function serializeActor(mixed $actor): ?array
    {
        if (! $actor instanceof \App\Models\AppUser) {
            return null;
        }

        return [
            'user_id' => $actor->user_id,
            'name' => $actor->adminProfile?->fullname ?? $actor->name,
            'display_code' => $actor->display_code,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAuditTrail(
        LoanRequest $loanRequest,
        bool $memberSafe,
    ): array {
        $loanRequest->loadMissing(
            'user',
            'assignedOfficer.adminProfile',
            'reviewedBy.adminProfile',
            'rejectedBy.adminProfile',
            'approvedBy.adminProfile',
            'declinedBy.adminProfile',
        );

        $changes = $loanRequest->changes()
            ->with('changedBy.adminProfile')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $entries = array_merge(
            $this->buildSyntheticAuditEntries($loanRequest, $changes->all(), $memberSafe),
            $changes
                ->map(
                    fn (LoanRequestChange $change): ?array => $this
                        ->serializeAuditChange($change, $memberSafe),
                )
                ->filter()
                ->values()
                ->all(),
        );

        usort($entries, function (array $left, array $right): int {
            $leftCreatedAt = $left['created_at'] ?? null;
            $rightCreatedAt = $right['created_at'] ?? null;

            if ($leftCreatedAt !== $rightCreatedAt) {
                if ($leftCreatedAt === null) {
                    return 1;
                }

                if ($rightCreatedAt === null) {
                    return -1;
                }

                return strcmp($leftCreatedAt, $rightCreatedAt);
            }

            return ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0);
        });

        return array_values(array_map(static function (array $entry): array {
            unset($entry['sort_order']);

            return $entry;
        }, $entries));
    }

    /**
     * @param  list<LoanRequestChange>  $changes
     * @return list<array<string, mixed>>
     */
    private function buildSyntheticAuditEntries(
        LoanRequest $loanRequest,
        array $changes,
        bool $memberSafe,
    ): array {
        $entries = [];
        $sortOrder = 0;

        $submissionEntry = $this->buildSubmittedAuditEntry(
            $loanRequest,
            $memberSafe,
            $sortOrder++,
        );

        if ($submissionEntry !== null) {
            $entries[] = $submissionEntry;
        }

        foreach (
            $this->buildLegacyStatusFallbackEntries(
                $loanRequest,
                $changes,
                $memberSafe,
                $sortOrder,
            ) as $entry
        ) {
            $entries[] = $entry;
            $sortOrder++;
        }

        return $entries;
    }

    /**
     * @param  list<LoanRequestChange>  $changes
     * @return list<array<string, mixed>>
     */
    private function buildLegacyStatusFallbackEntries(
        LoanRequest $loanRequest,
        array $changes,
        bool $memberSafe,
        int $startingSortOrder,
    ): array {
        $status = $this->rawStatusValue($loanRequest->status);
        $entries = [];
        $sortOrder = $startingSortOrder;

        if (
            $status === LoanRequestStatus::UnderReview->value
            && ! $this->hasAuditAction($changes, LoanRequestChange::ACTION_START_REVIEW, LoanRequestStatus::UnderReview->value)
        ) {
            $entries[] = $this->buildAuditEntry(
                id: sprintf('fallback-start-review-%d', $loanRequest->id),
                action: LoanRequestChange::ACTION_START_REVIEW,
                actor: $memberSafe
                    ? null
                    : $this->serializeAuditActor(
                        $loanRequest->assignedOfficer ?? $loanRequest->reviewedBy,
                    ),
                fromStatus: null,
                toStatus: LoanRequestStatus::UnderReview->value,
                reason: null,
                createdAt: $loanRequest->reviewed_at?->toDateTimeString()
                    ?? $loanRequest->submitted_at?->toDateTimeString(),
                metadata: [],
                sortOrder: $sortOrder++,
            );
        }

        if (
            $status === LoanRequestStatus::NeedsRevision->value
            && ! $this->hasAuditAction($changes, LoanRequestChange::ACTION_REQUEST_REVISION, LoanRequestStatus::NeedsRevision->value)
        ) {
            $entries[] = $this->buildAuditEntry(
                id: sprintf('fallback-needs-revision-%d', $loanRequest->id),
                action: LoanRequestChange::ACTION_REQUEST_REVISION,
                actor: $memberSafe ? null : $this->serializeAuditActor($loanRequest->reviewedBy),
                fromStatus: null,
                toStatus: LoanRequestStatus::NeedsRevision->value,
                reason: $this->resolveAuditReason(
                    LoanRequestChange::ACTION_REQUEST_REVISION,
                    $loanRequest->review_remarks,
                    $memberSafe,
                ),
                createdAt: $loanRequest->reviewed_at?->toDateTimeString(),
                metadata: [],
                sortOrder: $sortOrder++,
            );
        }

        if (
            $status === LoanRequestStatus::Rejected->value
            && ! $this->hasAuditAction($changes, LoanRequestChange::ACTION_REJECT, LoanRequestStatus::Rejected->value)
        ) {
            $entries[] = $this->buildAuditEntry(
                id: sprintf('fallback-rejected-%d', $loanRequest->id),
                action: LoanRequestChange::ACTION_REJECT,
                actor: $memberSafe
                    ? null
                    : $this->serializeAuditActor(
                        $loanRequest->rejectedBy ?? $loanRequest->reviewedBy,
                    ),
                fromStatus: null,
                toStatus: LoanRequestStatus::Rejected->value,
                reason: $this->resolveAuditReason(
                    LoanRequestChange::ACTION_REJECT,
                    $loanRequest->rejection_reason,
                    $memberSafe,
                ),
                createdAt: $loanRequest->rejected_at?->toDateTimeString()
                    ?? $loanRequest->reviewed_at?->toDateTimeString(),
                metadata: [],
                sortOrder: $sortOrder++,
            );
        }

        $hasRecommendationEvidence = $loanRequest->review_decision === LoanRequestStatus::RecommendedForApproval->value
            || $this->hasAuditAction(
                $changes,
                LoanRequestChange::ACTION_RECOMMEND_APPROVAL,
                LoanRequestStatus::RecommendedForApproval->value,
            );

        if (
            in_array($status, [
                LoanRequestStatus::RecommendedForApproval->value,
                LoanRequestStatus::Approved->value,
                LoanRequestStatus::Declined->value,
                LoanRequestStatus::ConvertedToLoan->value,
            ], true)
            && $hasRecommendationEvidence
            && ! $this->hasAuditAction($changes, LoanRequestChange::ACTION_RECOMMEND_APPROVAL, LoanRequestStatus::RecommendedForApproval->value)
        ) {
            $entries[] = $this->buildAuditEntry(
                id: sprintf('fallback-recommended-%d', $loanRequest->id),
                action: LoanRequestChange::ACTION_RECOMMEND_APPROVAL,
                actor: $memberSafe ? null : $this->serializeAuditActor($loanRequest->reviewedBy),
                fromStatus: null,
                toStatus: LoanRequestStatus::RecommendedForApproval->value,
                reason: $this->resolveAuditReason(
                    LoanRequestChange::ACTION_RECOMMEND_APPROVAL,
                    $loanRequest->review_remarks,
                    $memberSafe,
                ),
                createdAt: $loanRequest->reviewed_at?->toDateTimeString(),
                metadata: [],
                sortOrder: $sortOrder++,
            );
        }

        if (
            in_array($status, [
                LoanRequestStatus::Approved->value,
                LoanRequestStatus::ConvertedToLoan->value,
            ], true)
            && ! $this->hasAuditAction($changes, LoanRequestChange::ACTION_APPROVE, LoanRequestStatus::Approved->value)
        ) {
            $entries[] = $this->buildAuditEntry(
                id: sprintf('fallback-approved-%d', $loanRequest->id),
                action: LoanRequestChange::ACTION_APPROVE,
                actor: $memberSafe
                    ? null
                    : $this->serializeAuditActor(
                        $loanRequest->approvedBy ?? $loanRequest->reviewedBy,
                    ),
                fromStatus: null,
                toStatus: LoanRequestStatus::Approved->value,
                reason: $this->resolveAuditReason(
                    LoanRequestChange::ACTION_APPROVE,
                    $loanRequest->approval_remarks ?? $loanRequest->decision_notes,
                    $memberSafe,
                ),
                createdAt: $loanRequest->approved_at?->toDateTimeString()
                    ?? $loanRequest->reviewed_at?->toDateTimeString(),
                metadata: [],
                sortOrder: $sortOrder++,
            );
        }

        if (
            $status === LoanRequestStatus::Declined->value
            && ! $this->hasAuditAction($changes, LoanRequestChange::ACTION_DECLINE, LoanRequestStatus::Declined->value)
        ) {
            $entries[] = $this->buildAuditEntry(
                id: sprintf('fallback-declined-%d', $loanRequest->id),
                action: LoanRequestChange::ACTION_DECLINE,
                actor: $memberSafe
                    ? null
                    : $this->serializeAuditActor(
                        $loanRequest->declinedBy ?? $loanRequest->reviewedBy,
                    ),
                fromStatus: null,
                toStatus: LoanRequestStatus::Declined->value,
                reason: $this->resolveAuditReason(
                    LoanRequestChange::ACTION_DECLINE,
                    $loanRequest->decline_reason ?? $loanRequest->decision_notes,
                    $memberSafe,
                ),
                createdAt: $loanRequest->declined_at?->toDateTimeString()
                    ?? $loanRequest->reviewed_at?->toDateTimeString(),
                metadata: [],
                sortOrder: $sortOrder++,
            );
        }

        if (
            $status === LoanRequestStatus::ConvertedToLoan->value
            && ! $this->hasAuditAction($changes, LoanRequestChange::ACTION_CONVERT_TO_LOAN, LoanRequestStatus::ConvertedToLoan->value)
        ) {
            $entries[] = $this->buildAuditEntry(
                id: sprintf('fallback-converted-%d', $loanRequest->id),
                action: LoanRequestChange::ACTION_CONVERT_TO_LOAN,
                actor: $memberSafe ? null : $this->serializeAuditActor($loanRequest->approvedBy),
                fromStatus: null,
                toStatus: LoanRequestStatus::ConvertedToLoan->value,
                reason: $this->resolveAuditReason(
                    LoanRequestChange::ACTION_CONVERT_TO_LOAN,
                    $loanRequest->decision_notes,
                    $memberSafe,
                ),
                createdAt: null,
                metadata: [],
                sortOrder: $sortOrder++,
            );
        }

        return $entries;
    }

    private function buildSubmittedAuditEntry(
        LoanRequest $loanRequest,
        bool $memberSafe,
        int $sortOrder,
    ): ?array {
        if ($loanRequest->submitted_at === null) {
            return null;
        }

        return $this->buildAuditEntry(
            id: sprintf('submitted-%d', $loanRequest->id),
            action: 'submitted',
            actor: $memberSafe ? null : $this->serializeAuditActor($loanRequest->user),
            fromStatus: LoanRequestStatus::Draft->value,
            toStatus: null,
            reason: null,
            createdAt: $loanRequest->submitted_at->toDateTimeString(),
            metadata: [],
            sortOrder: $sortOrder,
        );
    }

    private function hasAuditAction(
        array $changes,
        string $action,
        ?string $toStatus = null,
    ): bool {
        foreach ($changes as $change) {
            if (! $change instanceof LoanRequestChange) {
                continue;
            }

            if ($change->action !== $action) {
                continue;
            }

            if ($toStatus === null) {
                return true;
            }

            if ($this->rawStatusValue($change->to_status) === $toStatus) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeAuditChange(
        LoanRequestChange $change,
        bool $memberSafe,
    ): ?array {
        if (
            $memberSafe
            && ! in_array($change->action, self::MEMBER_VISIBLE_AUDIT_ACTIONS, true)
        ) {
            return null;
        }

        $change->loadMissing('changedBy.adminProfile');

        return $this->buildAuditEntry(
            id: (string) $change->id,
            action: $change->action,
            actor: $memberSafe ? null : $this->serializeAuditActor($change->changedBy),
            fromStatus: $this->rawStatusValue($change->from_status),
            toStatus: $this->rawStatusValue($change->to_status),
            reason: $this->resolveAuditReason(
                $change->action,
                $change->reason,
                $memberSafe,
            ),
            createdAt: $change->created_at?->toDateTimeString(),
            metadata: $this->serializeAuditMetadata(
                $change->metadata_json,
                $memberSafe,
            ),
            sortOrder: (int) $change->id + 10_000,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return list<array{key: string, label: string, value: string}>
     */
    private function serializeAuditMetadata(
        ?array $metadata,
        bool $memberSafe,
    ): array {
        if ($memberSafe || ! is_array($metadata)) {
            return [];
        }

        $items = [];

        foreach (self::AUDIT_METADATA_LABELS as $key => $label) {
            $value = $metadata[$key] ?? null;

            if (! is_scalar($value)) {
                continue;
            }

            $normalizedValue = trim((string) $value);

            if ($normalizedValue === '') {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => $label,
                'value' => $normalizedValue,
            ];
        }

        return $items;
    }

    private function resolveAuditReason(
        string $action,
        ?string $reason,
        bool $memberSafe,
    ): ?string {
        $normalizedReason = $this->normalizeOptionalString($reason);

        if ($normalizedReason === null) {
            return null;
        }

        if (! $memberSafe) {
            return $normalizedReason;
        }

        return in_array($action, self::MEMBER_VISIBLE_REASON_ACTIONS, true)
            ? $normalizedReason
            : null;
    }

    /**
     * @return array{user_id: int, name: string, acctno: string|null}|null
     */
    private function serializeAuditActor(?AppUser $actor): ?array
    {
        if (! $actor instanceof AppUser) {
            return null;
        }

        return [
            'user_id' => $actor->user_id,
            'name' => $actor->adminProfile?->fullname ?? $actor->name,
            'acctno' => $this->normalizeOptionalString($actor->acctno),
        ];
    }

    /**
     * @param  list<array{key: string, label: string, value: string}>  $metadata
     * @return array<string, mixed>
     */
    private function buildAuditEntry(
        string $id,
        string $action,
        ?array $actor,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $reason,
        ?string $createdAt,
        array $metadata,
        int $sortOrder,
    ): array {
        return [
            'id' => $id,
            'action' => $action,
            'action_label' => $this->resolveAuditActionLabel($action),
            'actor' => $actor,
            'from_status' => $fromStatus,
            'from_status_label' => $this->resolveAuditStatusLabel($fromStatus),
            'to_status' => $toStatus,
            'to_status_label' => $this->resolveAuditStatusLabel($toStatus),
            'reason' => $reason,
            'created_at' => $createdAt,
            'metadata' => $metadata,
            'sort_order' => $sortOrder,
        ];
    }

    private function resolveAuditActionLabel(string $action): string
    {
        return self::AUDIT_ACTION_LABELS[$action]
            ?? Str::of($action)->replace('_', ' ')->headline()->toString();
    }

    private function resolveAuditStatusLabel(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return self::AUDIT_STATUS_LABELS[$status]
            ?? Str::of($status)->replace('_', ' ')->headline()->toString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeCorrectionReports(LoanRequest $loanRequest): array
    {
        $reports = $loanRequest->correctionReports()
            ->with(['user', 'resolvedBy', 'dismissedBy'])
            ->orderByDesc('id')
            ->get();

        return $reports
            ->map(
                fn (LoanRequestCorrectionReport $report): array => $this
                    ->serializeCorrectionReport($report),
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCorrectionReport(
        LoanRequestCorrectionReport $report,
    ): array {
        $report->loadMissing('user', 'resolvedBy', 'dismissedBy');

        return [
            'id' => $report->id,
            'loan_request_id' => $report->loan_request_id,
            'status' => $report->status,
            'issue_description' => $report->issue_description,
            'correct_information' => $report->correct_information,
            'supporting_note' => $report->supporting_note,
            'admin_notes' => $report->admin_notes,
            'reported_at' => $report->created_at?->toDateTimeString(),
            'reported_by' => $report->user
                ? [
                    'user_id' => $report->user->user_id,
                    'name' => $report->user->name,
                    'acctno' => $report->user->acctno,
                ]
                : null,
            'resolved_by' => $report->resolvedBy
                ? [
                    'user_id' => $report->resolvedBy->user_id,
                    'name' => $report->resolvedBy->name,
                ]
                : null,
            'resolved_at' => $report->resolved_at?->toDateTimeString(),
            'dismissed_by' => $report->dismissedBy
                ? [
                    'user_id' => $report->dismissedBy->user_id,
                    'name' => $report->dismissedBy->name,
                ]
                : null,
            'dismissed_at' => $report->dismissed_at?->toDateTimeString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeNotificationHistory(LoanRequest $loanRequest): array
    {
        $events = $loanRequest->notificationEvents()
            ->orderByDesc('id')
            ->get();

        return $events
            ->map(fn (LoanRequestNotificationEvent $event): array => [
                'id' => $event->id,
                'channel' => $event->channel,
                'event_type' => $event->event_type,
                'event_label' => Str::of($event->event_type)
                    ->replace('_', ' ')
                    ->headline()
                    ->toString(),
                'status' => $event->result,
                'queued_at' => $event->queued_at?->toDateTimeString(),
                'sent_at' => $event->sent_at?->toDateTimeString(),
                'failed_at' => $event->failed_at?->toDateTimeString(),
                'last_attempt_at' => $event->last_attempt_at?->toDateTimeString(),
                'attempt_count' => (int) ($event->attempt_count ?? 0),
                'retry_count' => (int) ($event->retry_count ?? 0),
                'reminder_attempts' => (int) ($event->reminder_attempts ?? 0),
                'provider_error' => $this->normalizeOptionalString(
                    $event->provider_error,
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function serializeWorkflowHealth(LoanRequest $loanRequest): array
    {
        $documents = $loanRequest->documents()->get();
        $notifications = $loanRequest->notificationEvents()->get();

        return [
            'processing_age_days' => $this->processingAgeDays($loanRequest),
            'stale_document_count' => $documents
                ->where('is_applicable', true)
                ->where('readiness_status', LoanRequestDocumentReadinessStatus::GeneratedStale)
                ->count(),
            'failed_document_count' => $documents
                ->where('is_applicable', true)
                ->where('readiness_status', LoanRequestDocumentReadinessStatus::GenerationFailed)
                ->count(),
            'legacy_blocker_count' => $documents
                ->where('is_applicable', true)
                ->where('readiness_status', LoanRequestDocumentReadinessStatus::LegacyDataIncomplete)
                ->count(),
            'pending_member_action' => $loanRequest->member_action_type !== null,
            'notification_failure_count' => $notifications
                ->where('result', 'failed')
                ->count(),
            'workflow_failed_job_count' => $this->workflowFailedJobCount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): array {
        $loanRequest->loadMissing('people');

        $person = $loanRequest->people
            ->first(function (LoanRequestPerson $item) use ($role): bool {
                $itemRole = $item->role instanceof LoanRequestPersonRole
                    ? $item->role->value
                    : (string) $item->role;

                return $itemRole === $role->value;
            });

        if ($person === null) {
            return [];
        }

        return $this->hydrateStructuredPersonFields($person->toArray());
    }

    private function normalizeStatus(LoanRequest $loanRequest): string
    {
        return LoanRequestStatus::normalizeValue($loanRequest->status)
            ?? (string) $loanRequest->status;
    }

    private function processingAgeDays(LoanRequest $loanRequest): ?int
    {
        $status = $this->normalizeStatus($loanRequest);

        if (! in_array($status, [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::NeedsRevision->value,
            LoanRequestStatus::AwaitingMemberInformation->value,
            LoanRequestStatus::RecommendedForApproval->value,
            LoanRequestStatus::AwaitingMemberAcceptance->value,
        ], true)) {
            return null;
        }

        if ($loanRequest->submitted_at === null) {
            return null;
        }

        return max(0, now()->diffInDays($loanRequest->submitted_at));
    }

    private function rawStatusValue(mixed $status): ?string
    {
        if ($status instanceof LoanRequestStatus) {
            return $status->value;
        }

        if (! is_string($status)) {
            return null;
        }

        $trimmed = trim($status);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function resolveCorrectedRequest(
        LoanRequest $loanRequest,
    ): ?LoanRequest {
        if (! $loanRequest->relationLoaded('correctedRequests')) {
            return null;
        }

        /** @var LoanRequest|null $correctedRequest */
        $correctedRequest = $loanRequest->correctedRequests
            ->sortByDesc('id')
            ->first();

        return $correctedRequest;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function hydrateStructuredPersonFields(array $person): array
    {
        $birthdate = $this->normalizeDateForInput($person['birthdate'] ?? null);
        $housingStatus = $this->normalizeHousingStatusValue(
            $person['housing_status'] ?? null,
        );
        $civilStatus = $this->normalizeCivilStatusValue(
            $person['civil_status'] ?? null,
        );
        $payday = $this->normalizePaydayValue($person['payday'] ?? null);

        $birthplaceCity = $this->normalizeOptionalString(
            $person['birthplace_city'] ?? null,
        );
        $birthplaceProvince = $this->normalizeOptionalString(
            $person['birthplace_province'] ?? null,
        );
        $legacyBirthplace = $this->normalizeOptionalString(
            $person['birthplace'] ?? null,
        );

        if ($birthplaceCity === null && $birthplaceProvince === null && $legacyBirthplace !== null) {
            $parsed = LocationComposer::parseLegacyBirthplace($legacyBirthplace);
            $birthplaceCity = $parsed['city'];
            $birthplaceProvince = $parsed['province'];
        }

        $birthplace = LocationComposer::composeBirthplace(
            $birthplaceCity,
            $birthplaceProvince,
        );
        $birthplace = $birthplace !== '' ? $birthplace : $legacyBirthplace;

        $address1 = $this->normalizeOptionalString($person['address1'] ?? null);
        $address2 = $this->normalizeOptionalString($person['address2'] ?? null);
        $address3 = $this->normalizeOptionalString($person['address3'] ?? null);
        $legacyAddress = $this->normalizeOptionalString($person['address'] ?? null);

        if ($address1 === null && $address2 === null && $address3 === null && $legacyAddress !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacyAddress);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $address = LocationComposer::compose($address1, $address2, $address3);
        $address = $address !== '' ? $address : $legacyAddress;

        $employerAddress1 = $this->normalizeOptionalString(
            $person['employer_business_address1'] ?? null,
        );
        $employerAddress2 = $this->normalizeOptionalString(
            $person['employer_business_address2'] ?? null,
        );
        $employerAddress3 = $this->normalizeOptionalString(
            $person['employer_business_address3'] ?? null,
        );
        $legacyEmployerAddress = $this->normalizeOptionalString(
            $person['employer_business_address'] ?? null,
        );

        if (
            $employerAddress1 === null
            && $employerAddress2 === null
            && $employerAddress3 === null
            && $legacyEmployerAddress !== null
        ) {
            $parsed = LocationComposer::parseLegacyAddress(
                $legacyEmployerAddress,
            );
            $employerAddress1 = $parsed['address1'];
            $employerAddress2 = $parsed['address2'];
            $employerAddress3 = $parsed['address3'];
        }

        $employerBusinessAddress = LocationComposer::compose(
            $employerAddress1,
            $employerAddress2,
            $employerAddress3,
        );
        $employerBusinessAddress = $employerBusinessAddress !== ''
            ? $employerBusinessAddress
            : $legacyEmployerAddress;

        return array_merge($person, [
            'birthdate' => $birthdate,
            'birthplace' => $birthplace,
            'birthplace_city' => $birthplaceCity,
            'birthplace_province' => $birthplaceProvince,
            'address' => $address,
            'address1' => $address1,
            'address2' => $address2,
            'address3' => $address3,
            'employer_business_address' => $employerBusinessAddress,
            'employer_business_address1' => $employerAddress1,
            'employer_business_address2' => $employerAddress2,
            'employer_business_address3' => $employerAddress3,
            'housing_status' => $housingStatus,
            'civil_status' => $civilStatus,
            'payday' => $payday,
        ]);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function workflowFailedJobCount(): int
    {
        if (! $this->schemaCapabilities->hasTable('failed_jobs')) {
            return 0;
        }

        return \DB::table('failed_jobs')
            ->where(function ($query): void {
                $query
                    ->where('payload', 'like', '%SendLoanWorkflowSmsJob%')
                    ->orWhere('payload', 'like', '%LoanRequestWorkflowStatusNotification%');
            })
            ->count();
    }

    private function normalizeDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $candidate = substr($trimmed, 0, 10);

        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $candidate) === 1
            ? $candidate
            : null;
    }

    private function normalizeHousingStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        if (in_array($upper, ['OWNED', 'OWN', 'OWNER'], true)) {
            return 'OWNED';
        }

        if (in_array($upper, ['RENT', 'RENTAL', 'RENTED', 'RENTING'], true)) {
            return 'RENT';
        }

        return null;
    }

    private function normalizeCivilStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        $resolved = match ($upper) {
            'SINGLE' => 'Single',
            'MARRIED' => 'Married',
            'SEPARATED' => 'Separated',
            'WIDOWED' => 'Widowed',
            'ANNULLED' => null,
            default => $trimmed,
        };

        if ($resolved === null) {
            return null;
        }

        return in_array($resolved, self::CIVIL_STATUS_OPTIONS, true)
            ? $resolved
            : null;
    }

    private function normalizePaydayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array($trimmed, self::PAYDAY_OPTIONS, true)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);
        $compact = preg_replace('/[^0-9A-Z]/', '', $upper) ?? '';

        if ($upper === 'WEEKLY') {
            return 'Weekly';
        }

        if ($upper === 'MONTHLY') {
            return 'Monthly';
        }

        if ($compact === 'BIWEEKLY') {
            return 'Bi-Weekly';
        }

        if ($compact === '15') {
            return '15th';
        }

        if ($compact === '30') {
            return '30th';
        }

        if (str_contains($upper, '15') && str_contains($upper, '30')) {
            return '15th & 30th';
        }

        return null;
    }
}
