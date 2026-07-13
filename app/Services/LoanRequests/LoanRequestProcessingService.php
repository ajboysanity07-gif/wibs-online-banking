<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentKey;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestDataChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanRequestProcessingService
{
    public function __construct(
        private LoanRequestService $loanRequestService,
        private LoanRequestDataService $dataService,
        private LoanRequestDocumentWorkflowService $documentWorkflowService,
        private LoanRequestNotificationService $notificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProcessingDetails(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $payload,
    ): LoanRequest {
        return DB::transaction(function () use ($loanRequest, $actor, $payload): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $this->ensureProcessorEditableStatus($lockedLoanRequest);

            $reason = trim((string) ($payload['reason'] ?? ''));
            $informationSource = trim((string) ($payload['information_source'] ?? ''));

            if ($reason === '' || $informationSource === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A reason and information source are required for processing updates.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);

            $loanRequestPayload = $this->processingRequestPayload($payload);

            if ($loanRequestPayload !== []) {
                $this->loanRequestService->fillSubmittedDetails(
                    $lockedLoanRequest,
                    $loanRequestPayload,
                );
            }

            if ($this->peoplePayloadExists($payload)) {
                $this->loanRequestService->upsertPeopleSnapshots(
                    $lockedLoanRequest,
                    $payload,
                );
            }

            foreach ([
                'recommended_amount',
                'recommended_term',
                'recommended_interest_rate',
                'recommended_payment_frequency',
                'recommendation_remarks',
            ] as $field) {
                if (array_key_exists($field, $payload)) {
                    $lockedLoanRequest->setAttribute($field, $payload[$field]);
                }
            }

            $processingPayload = is_array($payload['processing'] ?? null)
                ? $payload['processing']
                : [];

            if (! array_key_exists('witness_one_name', $processingPayload)
                || trim((string) ($processingPayload['witness_one_name'] ?? '')) === ''
            ) {
                $processorDisplayName = $lockedLoanRequest->assignedProcessor?->adminProfile?->fullname
                    ?? $lockedLoanRequest->assignedProcessor?->name
                    ?? $lockedLoanRequest->assignedProcessor?->username;

                if ($processorDisplayName !== null) {
                    $processingPayload['witness_one_name'] = $processorDisplayName;
                }
            }

            $changedProcessingFields = $this->dataService->applyStaffUpdates(
                $lockedLoanRequest,
                $actor,
                $processingPayload,
                $reason,
                $informationSource,
            );

            $lockedLoanRequest->save();
            $lockedLoanRequest->refresh()->loadMissing('people', 'dataEntries');

            $after = $this->editableSnapshot($lockedLoanRequest);
            $changedFields = $this->recordSnapshotChanges(
                $lockedLoanRequest,
                $actor,
                $before,
                $after,
                $reason,
                $informationSource,
            );

            $allChangedFields = array_values(array_unique(array_merge(
                $changedFields,
                $changedProcessingFields,
            )));

            if ($allChangedFields === []) {
                throw ValidationException::withMessages([
                    'processing' => 'Please update at least one value before saving.',
                ]);
            }

            $this->documentWorkflowService->markAffectedDocumentsStale(
                $lockedLoanRequest,
                $allChangedFields,
            );

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_PROCESSING_DETAILS_UPDATED,
                $reason,
                $fromStatus,
                $this->statusValue($lockedLoanRequest),
                $allChangedFields,
                [
                    'information_source' => $informationSource,
                ],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh()->loadMissing('people', 'dataEntries');
        });
    }

    /**
     * @param  array{
     *     action_type:string,
     *     message:string,
     *     reason:string,
     *     field_keys?:array<int, string>
     * }  $payload
     */
    public function requestMemberAction(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $payload,
    ): LoanRequest {
        $updated = DB::transaction(function () use ($loanRequest, $actor, $payload): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $this->ensureProcessorEditableStatus($lockedLoanRequest);

            $actionType = trim((string) $payload['action_type']);
            $message = trim((string) $payload['message']);
            $reason = trim((string) $payload['reason']);
            $fieldKeys = array_values(array_filter(
                array_map(
                    static fn (mixed $fieldKey): ?string => is_string($fieldKey) && trim($fieldKey) !== ''
                        ? trim($fieldKey)
                        : null,
                    $payload['field_keys'] ?? [],
                ),
            ));

            if (! in_array($actionType, ['needs_revision', 'awaiting_member_information'], true)) {
                throw ValidationException::withMessages([
                    'action_type' => 'Unsupported member-action type.',
                ]);
            }

            if ($message === '' || $reason === '') {
                throw ValidationException::withMessages([
                    'message' => 'A message and reason are required.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);
            $fromStatus = $this->statusValue($lockedLoanRequest);
            $status = $actionType === 'needs_revision'
                ? LoanRequestStatus::NeedsRevision
                : LoanRequestStatus::AwaitingMemberInformation;

            $lockedLoanRequest->fill([
                'status' => $status,
                'member_action_type' => $actionType,
                'member_action_message' => $message,
                'member_action_fields_json' => $fieldKeys,
                'member_action_requested_by' => $actor->user_id,
                'member_action_requested_at' => now(),
                'member_action_resolved_at' => null,
            ]);
            $lockedLoanRequest->save();

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                $actionType === 'needs_revision'
                    ? LoanRequestChange::ACTION_REQUEST_REVISION
                    : LoanRequestChange::ACTION_REQUEST_MEMBER_INFORMATION,
                $reason,
                $fromStatus,
                $status->value,
                ['status', 'member_action_type', 'member_action_message'],
                [
                    'requested_fields' => $this->dataService->fieldDescriptors($fieldKeys),
                    'member_visible_message' => $message,
                ],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh();
        });

        $this->notificationService->notifyMember(
            $updated,
            $updated->status === LoanRequestStatus::NeedsRevision
                ? LoanRequestNotificationService::EVENT_AWAITING_MEMBER_CORRECTION
                : LoanRequestNotificationService::EVENT_AWAITING_MEMBER_INFORMATION,
            [
                'title' => $updated->status === LoanRequestStatus::NeedsRevision
                    ? 'Loan request needs correction'
                    : 'Loan request needs more information',
                'message' => trim((string) $updated->member_action_message),
                'reason' => $payload['reason'],
            ],
            $actor,
        );

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveMemberInformation(
        LoanRequest $loanRequest,
        AppUser $member,
        array $payload,
    ): LoanRequest {
        return DB::transaction(function () use ($loanRequest, $member, $payload): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $this->ensureOwnedByMember($lockedLoanRequest, $member);

            if ($this->statusValue($lockedLoanRequest) !== LoanRequestStatus::AwaitingMemberInformation->value) {
                throw ValidationException::withMessages([
                    'status' => 'This request is not waiting for member information.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);
            $this->dataService->syncMemberSections($lockedLoanRequest, $payload);
            $requestedFieldKeys = is_array($lockedLoanRequest->member_action_fields_json)
                ? array_values(array_filter($lockedLoanRequest->member_action_fields_json, 'is_string'))
                : [];

            $missingRequestedFields = array_values(array_filter(
                $requestedFieldKeys,
                function (string $fieldKey) use ($lockedLoanRequest): bool {
                    $value = $this->dataService->loadFlatValues($lockedLoanRequest)[$fieldKey] ?? null;

                    return $value === null || trim((string) $value) === '';
                },
            ));

            if ($missingRequestedFields !== []) {
                throw ValidationException::withMessages([
                    'fields' => sprintf(
                        'Please complete these fields: %s.',
                        implode(
                            ', ',
                            array_map(
                                fn (string $fieldKey): string => $this->dataService->fieldLabel(
                                    $fieldKey,
                                ),
                                $missingRequestedFields,
                            ),
                        ),
                    ),
                ]);
            }

            $lockedLoanRequest->fill([
                'status' => $lockedLoanRequest->assigned_officer_id !== null
                    ? LoanRequestStatus::UnderReview
                    : LoanRequestStatus::PendingReview,
                'member_action_type' => null,
                'member_action_message' => null,
                'member_action_fields_json' => null,
                'member_action_resolved_at' => now(),
            ]);
            $lockedLoanRequest->save();

            $this->documentWorkflowService->markAffectedDocumentsStale(
                $lockedLoanRequest,
                $requestedFieldKeys,
            );

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $member,
                LoanRequestChange::ACTION_MEMBER_CONFIRMED_INFORMATION,
                'Member supplied the requested information.',
                $before['status'],
                $this->statusValue($lockedLoanRequest),
                ['status', 'member_action_type'],
                [
                    'resolved_fields' => $this->dataService->fieldDescriptors(
                        $requestedFieldKeys,
                    ),
                ],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh()->loadMissing('dataEntries');
        });
    }

    public function rejectDuringProcessing(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $category,
        string $memberVisibleReason,
    ): LoanRequest {
        $updated = DB::transaction(function () use ($loanRequest, $actor, $category, $memberVisibleReason): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $this->ensureProcessorEditableStatus($lockedLoanRequest);

            $normalizedCategory = trim($category);
            $normalizedReason = trim($memberVisibleReason);

            if ($normalizedCategory === '' || $normalizedReason === '') {
                throw ValidationException::withMessages([
                    'rejection' => 'A rejection category and reason are required.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::Rejected,
                'reviewed_by' => $actor->user_id,
                'reviewed_at' => now(),
                'review_decision' => LoanRequestStatus::Rejected->value,
                'review_rejection_category' => $normalizedCategory,
                'rejected_by' => $actor->user_id,
                'rejected_at' => now(),
                'rejection_reason' => $normalizedReason,
                'member_action_type' => null,
                'member_action_message' => null,
                'member_action_fields_json' => null,
                'member_action_resolved_at' => now(),
            ]);
            $lockedLoanRequest->save();

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_REJECT_DURING_PROCESSING,
                $normalizedReason,
                $before['status'],
                LoanRequestStatus::Rejected->value,
                [
                    'status',
                    'review_rejection_category',
                    'rejection_reason',
                ],
                [
                    'rejection_category' => $normalizedCategory,
                ],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh();
        });

        $this->notificationService->notifyMember(
            $updated,
            LoanRequestNotificationService::EVENT_REJECTED_DURING_PROCESSING,
            [
                'title' => 'Loan request rejected during processing',
                'message' => sprintf(
                    'Your loan request %s was rejected during processing.',
                    $updated->reference,
                ),
                'reason' => $updated->rejection_reason,
            ],
            $actor,
        );

        return $updated;
    }

    public function reopenRejectedRequest(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $reason,
        bool $retainAssignment = false,
    ): LoanRequest {
        $updated = DB::transaction(function () use ($loanRequest, $actor, $reason, $retainAssignment): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $normalizedReason = trim($reason);

            if ($normalizedReason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required to reopen a rejected request.',
                ]);
            }

            if ($this->statusValue($lockedLoanRequest) !== LoanRequestStatus::Rejected->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only rejected requests can be reopened.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => $retainAssignment && $lockedLoanRequest->assigned_officer_id !== null
                    ? LoanRequestStatus::UnderReview
                    : LoanRequestStatus::PendingReview,
                'review_decision' => null,
                'review_rejection_category' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'reopened_by' => $actor->user_id,
                'reopened_at' => now(),
            ]);
            $lockedLoanRequest->save();

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_REOPEN_REJECTED_REQUEST,
                $normalizedReason,
                $before['status'],
                $this->statusValue($lockedLoanRequest),
                ['status', 'reopened_by', 'reopened_at'],
                [
                    'retain_assignment' => $retainAssignment,
                ],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh();
        });

        $this->notificationService->notifyMember(
            $updated,
            LoanRequestNotificationService::EVENT_REOPENED,
            [
                'title' => 'Loan request reopened',
                'message' => sprintf(
                    'Your loan request %s has been reopened for continued processing.',
                    $updated->reference,
                ),
                'reason' => $reason,
            ],
            $actor,
        );

        return $updated;
    }

    public function returnForProcessing(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $reason,
    ): LoanRequest {
        return DB::transaction(function () use ($loanRequest, $actor, $reason): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $normalizedReason = trim($reason);

            if ($normalizedReason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required to return a request for processing.',
                ]);
            }

            if (! in_array($this->statusValue($lockedLoanRequest), [
                LoanRequestStatus::RecommendedForApproval->value,
                LoanRequestStatus::AwaitingMemberAcceptance->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only manager-review requests can be returned for processing.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::UnderReview,
                'member_action_type' => null,
                'member_action_message' => null,
                'member_action_fields_json' => null,
                'member_action_resolved_at' => now(),
            ]);
            $lockedLoanRequest->save();

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_MANAGER_RETURN_FOR_PROCESSING,
                $normalizedReason,
                $before['status'],
                LoanRequestStatus::UnderReview->value,
                ['status'],
                [],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh();
        });
    }

    /**
     * @param  array{
     *     approved_amount?:mixed,
     *     approved_term?:mixed,
     *     approved_interest_rate?:mixed,
     *     approved_payment_frequency?:mixed,
     *     approval_remarks?:mixed
     * }  $payload
     */
    public function approveByManager(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $payload,
    ): LoanRequest {
        $updated = DB::transaction(function () use ($loanRequest, $actor, $payload): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $isDocumentWorkflowV2 = $this->workflowVersionValue($lockedLoanRequest)
                === LoanRequestWorkflowVersion::DocumentWorkflowV2->value;

            if ($this->statusValue($lockedLoanRequest) !== LoanRequestStatus::RecommendedForApproval->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only recommended requests can be approved from manager review.',
                ]);
            }

            if ($isDocumentWorkflowV2) {
                $this->documentWorkflowService->ensureRecommendationReady(
                    $lockedLoanRequest,
                );
            }

            $approvedAmount = $payload['approved_amount'] ?? $lockedLoanRequest->recommended_amount;
            $approvedTerm = $payload['approved_term'] ?? $lockedLoanRequest->recommended_term;
            $approvedInterestRate = $payload['approved_interest_rate'] ?? $lockedLoanRequest->recommended_interest_rate;
            $approvedPaymentFrequency = $payload['approved_payment_frequency'] ?? $lockedLoanRequest->recommended_payment_frequency;
            $approvalRemarks = trim((string) ($payload['approval_remarks'] ?? ''));
            $before = $this->editableSnapshot($lockedLoanRequest);

            $termsChanged = ! $this->sameNumericValue(
                $approvedAmount,
                $lockedLoanRequest->recommended_amount,
            )
                || ! $this->sameIntegerValue(
                    $approvedTerm,
                    $lockedLoanRequest->recommended_term,
                )
                || ! $this->sameNumericValue(
                    $approvedInterestRate,
                    $lockedLoanRequest->recommended_interest_rate,
                )
                || ! $this->sameTextValue(
                    $approvedPaymentFrequency,
                    $lockedLoanRequest->recommended_payment_frequency,
                );

            if ($isDocumentWorkflowV2 && $termsChanged) {
                $lockedLoanRequest->fill([
                    'status' => LoanRequestStatus::AwaitingMemberAcceptance,
                    'recommended_amount' => $approvedAmount,
                    'recommended_term' => $approvedTerm,
                    'recommended_interest_rate' => $approvedInterestRate,
                    'recommended_payment_frequency' => $approvedPaymentFrequency,
                    'member_action_type' => 'terms_acceptance',
                    'member_action_message' => $approvalRemarks !== ''
                        ? $approvalRemarks
                        : 'Please review and accept the revised loan terms to continue processing.',
                    'member_action_fields_json' => [
                        'recommended_amount',
                        'recommended_term',
                        'recommended_interest_rate',
                        'recommended_payment_frequency',
                    ],
                    'member_action_requested_by' => $actor->user_id,
                    'member_action_requested_at' => now(),
                    'member_action_resolved_at' => null,
                ]);
                $lockedLoanRequest->save();

                $this->documentWorkflowService->markAffectedDocumentsStale(
                    $lockedLoanRequest,
                    [
                        'recommended_amount',
                        'recommended_term',
                        'recommended_interest_rate',
                        'recommended_payment_frequency',
                    ],
                );

                $after = $this->editableSnapshot($lockedLoanRequest);

                $this->recordAudit(
                    $lockedLoanRequest,
                    $actor,
                    LoanRequestChange::ACTION_MANAGER_CHANGED_TERMS,
                    $approvalRemarks !== '' ? $approvalRemarks : 'Manager changed member-impacting terms.',
                    $before['status'],
                    LoanRequestStatus::AwaitingMemberAcceptance->value,
                    [
                        'status',
                        'recommended_amount',
                        'recommended_term',
                        'recommended_interest_rate',
                        'recommended_payment_frequency',
                    ],
                    [],
                    $before,
                    $after,
                );

                return $lockedLoanRequest->refresh();
            }

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::Approved,
                'approved_by' => $actor->user_id,
                'approved_at' => now(),
                'approved_amount' => $approvedAmount,
                'approved_term' => $approvedTerm,
                'approved_interest_rate' => $approvedInterestRate,
                'approval_remarks' => $approvalRemarks !== '' ? $approvalRemarks : null,
                'decision_notes' => $approvalRemarks !== '' ? $approvalRemarks : null,
            ]);
            $lockedLoanRequest->save();

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_APPROVE,
                $approvalRemarks,
                $before['status'],
                LoanRequestStatus::Approved->value,
                [
                    'status',
                    'approved_amount',
                    'approved_term',
                    'approved_interest_rate',
                ],
                [],
                $before,
                $after,
            );

            if ($isDocumentWorkflowV2) {
                $approverDisplayName = $actor->adminProfile?->fullname
                    ?? $actor->name
                    ?? $actor->username;

                $this->dataService->applyStaffUpdates(
                    $lockedLoanRequest,
                    $actor,
                    ['witness_two_name' => $approverDisplayName],
                    'Witness 2 recorded upon approval',
                    'System — automated on loan approval',
                );

                $lockedLoanRequest->load('dataEntries');

                $this->documentWorkflowService->generateDocument(
                    $lockedLoanRequest,
                    LoanRequestDocumentKey::LoanInformation,
                    $actor,
                );
                $this->documentWorkflowService->generateDocument(
                    $lockedLoanRequest,
                    LoanRequestDocumentKey::PromissoryNote,
                    $actor,
                );
            }

            return $lockedLoanRequest->refresh();
        });

        $this->notificationService->notifyMember(
            $updated,
            $updated->status === LoanRequestStatus::AwaitingMemberAcceptance
                ? LoanRequestNotificationService::EVENT_AWAITING_MEMBER_ACCEPTANCE
                : LoanRequestNotificationService::EVENT_APPROVED_FOR_WIBS,
            [
                'title' => $updated->status === LoanRequestStatus::AwaitingMemberAcceptance
                    ? 'Loan request terms need your confirmation'
                    : 'Loan request approved for WIBS processing',
                'message' => $updated->status === LoanRequestStatus::AwaitingMemberAcceptance
                    ? trim((string) $updated->member_action_message)
                    : sprintf(
                        'Your loan request %s was approved and is ready for WIBS processing.',
                        $updated->reference,
                    ),
                'reason' => $payload['approval_remarks'] ?? null,
            ],
            $actor,
        );

        return $updated;
    }

    public function declineByManager(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $category,
        string $reason,
    ): LoanRequest {
        $updated = DB::transaction(function () use ($loanRequest, $actor, $category, $reason): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $normalizedCategory = trim($category);
            $normalizedReason = trim($reason);

            if ($normalizedCategory === '' || $normalizedReason === '') {
                throw ValidationException::withMessages([
                    'decline' => 'A decline category and reason are required.',
                ]);
            }

            if ($this->statusValue($lockedLoanRequest) !== LoanRequestStatus::RecommendedForApproval->value) {
                throw ValidationException::withMessages([
                    'status' => 'Only recommended requests can be declined from manager review.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'status' => LoanRequestStatus::Declined,
                'declined_by' => $actor->user_id,
                'declined_at' => now(),
                'decline_category' => $normalizedCategory,
                'decline_reason' => $normalizedReason,
                'decision_notes' => $normalizedReason,
            ]);
            $lockedLoanRequest->save();

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_DECLINE,
                $normalizedReason,
                $before['status'],
                LoanRequestStatus::Declined->value,
                ['status', 'decline_category', 'decline_reason'],
                [],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh();
        });

        $this->notificationService->notifyMember(
            $updated,
            LoanRequestNotificationService::EVENT_DECLINED_BY_MANAGER,
            [
                'title' => 'Loan request declined by the loan manager',
                'message' => sprintf(
                    'Your loan request %s was declined by the loan manager.',
                    $updated->reference,
                ),
                'reason' => $updated->decline_reason,
            ],
            $actor,
        );

        return $updated;
    }

    public function respondToTerms(
        LoanRequest $loanRequest,
        AppUser $member,
        bool $accepted,
        ?string $reason = null,
    ): LoanRequest {
        return DB::transaction(function () use ($loanRequest, $member, $accepted, $reason): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $this->ensureOwnedByMember($lockedLoanRequest, $member);

            if ($this->statusValue($lockedLoanRequest) !== LoanRequestStatus::AwaitingMemberAcceptance->value) {
                throw ValidationException::withMessages([
                    'status' => 'This request is not waiting for member acceptance.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);
            $nextStatus = $accepted
                ? ($lockedLoanRequest->assigned_officer_id !== null
                    ? LoanRequestStatus::UnderReview
                    : LoanRequestStatus::PendingReview)
                : LoanRequestStatus::MemberDeclinedTerms;

            $lockedLoanRequest->fill([
                'status' => $nextStatus,
                'member_action_type' => null,
                'member_action_message' => null,
                'member_action_fields_json' => null,
                'member_action_resolved_at' => now(),
                'decision_notes' => $accepted
                    ? $lockedLoanRequest->decision_notes
                    : trim((string) $reason),
            ]);
            $lockedLoanRequest->save();

            $after = $this->editableSnapshot($lockedLoanRequest);

            $this->recordAudit(
                $lockedLoanRequest,
                $member,
                $accepted
                    ? LoanRequestChange::ACTION_MEMBER_ACCEPTED_TERMS
                    : LoanRequestChange::ACTION_MEMBER_DECLINED_TERMS,
                trim((string) $reason),
                $before['status'],
                $nextStatus->value,
                ['status', 'member_action_type'],
                [],
                $before,
                $after,
            );

            return $lockedLoanRequest->refresh();
        });
    }

    public function upgradeWorkflowVersion(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $reason,
    ): LoanRequest {
        return DB::transaction(function () use ($loanRequest, $actor, $reason): LoanRequest {
            $lockedLoanRequest = $this->lockLoanRequest($loanRequest);
            $normalizedReason = trim($reason);

            if ($normalizedReason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required to upgrade a legacy workflow request.',
                ]);
            }

            if ($this->workflowVersionValue($lockedLoanRequest) !== LoanRequestWorkflowVersion::LegacyV1->value) {
                return $lockedLoanRequest;
            }

            if (in_array($this->statusValue($lockedLoanRequest), [
                LoanRequestStatus::Approved->value,
                LoanRequestStatus::Declined->value,
                LoanRequestStatus::Rejected->value,
                LoanRequestStatus::Cancelled->value,
                LoanRequestStatus::ConvertedToLoan->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Terminal requests cannot be upgraded to the Phase 5 workflow.',
                ]);
            }

            $before = $this->editableSnapshot($lockedLoanRequest);

            $lockedLoanRequest->fill([
                'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
                'workflow_upgraded_by' => $actor->user_id,
                'workflow_upgraded_at' => now(),
                'workflow_upgrade_reason' => $normalizedReason,
            ]);
            $lockedLoanRequest->save();

            $this->recordAudit(
                $lockedLoanRequest,
                $actor,
                LoanRequestChange::ACTION_WORKFLOW_UPGRADED,
                $normalizedReason,
                $before['status'],
                $this->statusValue($lockedLoanRequest),
                ['workflow_version'],
                [
                    'from_workflow_version' => $before['workflow_version'],
                    'to_workflow_version' => $this->workflowVersionValue(
                        $lockedLoanRequest,
                    ),
                ],
                $before,
                $this->editableSnapshot($lockedLoanRequest),
            );

            $this->documentWorkflowService->refreshChecklist($lockedLoanRequest);

            return $lockedLoanRequest->refresh();
        });
    }

    private function lockLoanRequest(LoanRequest $loanRequest): LoanRequest
    {
        return LoanRequest::query()
            ->whereKey($loanRequest->id)
            ->with(['people', 'dataEntries', 'user'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureProcessorEditableStatus(LoanRequest $loanRequest): void
    {
        if (! in_array($this->statusValue($loanRequest), [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::NeedsRevision->value,
            LoanRequestStatus::AwaitingMemberInformation->value,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'This request is not currently editable for processing.',
            ]);
        }
    }

    private function ensureOwnedByMember(
        LoanRequest $loanRequest,
        AppUser $member,
    ): void {
        if (
            (int) $loanRequest->user_id !== (int) $member->user_id
            && trim((string) $loanRequest->acctno) !== trim((string) $member->acctno)
        ) {
            throw ValidationException::withMessages([
                'loan_request' => 'You can only respond to your own loan request.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function processingRequestPayload(array $payload): array
    {
        if (is_array($payload['loan_request'] ?? null)) {
            return [
                ...$payload['loan_request'],
                'applicant' => $payload['applicant'] ?? null,
                'co_maker_1' => $payload['co_maker_1'] ?? null,
                'co_maker_2' => $payload['co_maker_2'] ?? null,
            ];
        }

        return [
            'typecode' => $payload['typecode'] ?? null,
            'requested_amount' => $payload['requested_amount'] ?? null,
            'requested_term' => $payload['requested_term'] ?? null,
            'loan_purpose' => $payload['loan_purpose'] ?? null,
            'availment_status' => $payload['availment_status'] ?? null,
            'applicant' => $payload['applicant'] ?? null,
            'co_maker_1' => $payload['co_maker_1'] ?? null,
            'co_maker_2' => $payload['co_maker_2'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function peoplePayloadExists(array $payload): bool
    {
        return is_array($payload['applicant'] ?? null)
            || is_array($payload['co_maker_1'] ?? null)
            || is_array($payload['co_maker_2'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function editableSnapshot(LoanRequest $loanRequest): array
    {
        return [
            'status' => $this->statusValue($loanRequest),
            'workflow_version' => $this->workflowVersionValue($loanRequest),
            'loan_request' => [
                'typecode' => $loanRequest->typecode,
                'requested_amount' => $loanRequest->requested_amount,
                'requested_term' => $loanRequest->requested_term,
                'loan_purpose' => $loanRequest->loan_purpose,
                'availment_status' => $loanRequest->availment_status,
                'recommended_amount' => $loanRequest->recommended_amount,
                'recommended_term' => $loanRequest->recommended_term,
                'recommended_interest_rate' => $loanRequest->recommended_interest_rate,
                'recommended_payment_frequency' => $loanRequest->recommended_payment_frequency,
                'recommendation_remarks' => $loanRequest->recommendation_remarks,
            ],
            'applicant' => $loanRequest->people
                ->firstWhere('role', \App\LoanRequestPersonRole::Applicant)?->toArray() ?? [],
            'co_maker_1' => $loanRequest->people
                ->firstWhere('role', \App\LoanRequestPersonRole::CoMakerOne)?->toArray() ?? [],
            'co_maker_2' => $loanRequest->people
                ->firstWhere('role', \App\LoanRequestPersonRole::CoMakerTwo)?->toArray() ?? [],
            'processing' => $this->dataService->loadFlatValues($loanRequest),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function recordSnapshotChanges(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $before,
        array $after,
        string $reason,
        string $informationSource,
        string $path = '',
    ): array {
        $changedFields = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $currentPath = $path === '' ? (string) $key : $path.'.'.$key;
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if (is_array($beforeValue) && is_array($afterValue)) {
                $changedFields = array_merge(
                    $changedFields,
                    $this->recordSnapshotChanges(
                        $loanRequest,
                        $actor,
                        $beforeValue,
                        $afterValue,
                        $reason,
                        $informationSource,
                        $currentPath,
                    ),
                );

                continue;
            }

            if ($beforeValue === $afterValue) {
                continue;
            }

            LoanRequestDataChange::query()->create([
                'loan_request_id' => $loanRequest->id,
                'actor_user_id' => $actor->user_id,
                'field_key' => $currentPath,
                'before_value_json' => ['value' => $beforeValue],
                'after_value_json' => ['value' => $afterValue],
                'reason' => $reason,
                'information_source' => $informationSource,
                'metadata_json' => [
                    'scope' => 'snapshot',
                ],
            ]);

            $changedFields[] = $currentPath;
        }

        return array_values(array_unique($changedFields));
    }

    /**
     * @param  list<string>  $changedFields
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(
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
        LoanRequestChange::query()->create([
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'before_json' => $before,
            'after_json' => $after,
            'changed_fields_json' => $changedFields,
            'metadata_json' => $metadata !== [] ? $metadata : null,
        ]);
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
    }

    private function workflowVersionValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->workflow_version instanceof LoanRequestWorkflowVersion
            ? $loanRequest->workflow_version->value
            : (string) ($loanRequest->workflow_version ?? LoanRequestWorkflowVersion::LegacyV1->value);
    }

    private function sameNumericValue(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $this->sameTextValue($left, $right);
        }

        if (! is_numeric((string) $left) || ! is_numeric((string) $right)) {
            return $this->sameTextValue($left, $right);
        }

        return abs((float) $left - (float) $right) < 0.0001;
    }

    private function sameIntegerValue(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $this->sameTextValue($left, $right);
        }

        if (! is_numeric((string) $left) || ! is_numeric((string) $right)) {
            return $this->sameTextValue($left, $right);
        }

        return (int) round((float) $left) === (int) round((float) $right);
    }

    private function sameTextValue(mixed $left, mixed $right): bool
    {
        $normalizedLeft = $left === null ? null : trim((string) $left);
        $normalizedRight = $right === null ? null : trim((string) $right);

        return $normalizedLeft === $normalizedRight;
    }
}
