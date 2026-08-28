<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\LoanRequestAssignmentUpdateRequest;
use App\Http\Requests\Workflow\LoanRequestBulkClaimRequest;
use App\Http\Requests\Workflow\LoanRequestClaimRequest;
use App\Http\Requests\Workflow\LoanRequestGenerateDocumentsRequest;
use App\Http\Requests\Workflow\LoanRequestProcessingUpdateRequest;
use App\Http\Requests\Workflow\LoanRequestRecommendApprovalRequest;
use App\Http\Requests\Workflow\LoanRequestRecommendationPreviewRequest;
use App\Http\Requests\Workflow\LoanRequestRejectDuringProcessingRequest;
use App\Http\Requests\Workflow\LoanRequestRejectRequest;
use App\Http\Requests\Workflow\LoanRequestReopenRequest;
use App\Http\Requests\Workflow\LoanRequestRequestMemberActionRequest;
use App\Http\Requests\Workflow\LoanRequestRequestRevisionRequest;
use App\Http\Requests\Workflow\LoanRequestReturnForProcessingRequest;
use App\Http\Requests\Workflow\LoanRequestReturnToQueueRequest;
use App\Http\Requests\Workflow\LoanRequestStartReviewRequest;
use App\Http\Requests\Workflow\LoanRequestUpgradeWorkflowRequest;
use App\Http\Requests\Workflow\LoanRequestWorkflowApproveRequest;
use App\Http\Requests\Workflow\LoanRequestWorkflowDeclineRequest;
use App\LoanRequestDocumentKey;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestAssignmentService;
use App\Services\LoanRequests\LoanRequestCycleStateService;
use App\Services\LoanRequests\LoanRequestDataService;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestProcessingService;
use App\Services\LoanRequests\LoanRequestWorkflowService;
use Illuminate\Http\JsonResponse;

class LoanRequestWorkflowController extends Controller
{
    public function claim(
        LoanRequestClaimRequest $request,
        LoanRequest $loanRequest,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $assignmentService->claim($loanRequest, $actor);

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function bulkClaim(
        LoanRequestBulkClaimRequest $request,
        LoanRequestAssignmentService $assignmentService,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $result = $assignmentService->bulkClaim(
            $request->validated('loan_request_ids'),
            $actor,
        );

        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }

    public function updateAssignment(
        LoanRequestAssignmentUpdateRequest $request,
        LoanRequest $loanRequest,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $targetOfficer = AppUser::query()->findOrFail(
            $request->validated('officer_user_id'),
        );

        $updated = $request->validated('action') === 'assign'
            ? $assignmentService->assign(
                $loanRequest,
                $targetOfficer,
                $actor,
                $request->validated('reason'),
            )
            : $assignmentService->reassign(
                $loanRequest,
                $targetOfficer,
                $actor,
                $request->validated('reason'),
            );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function returnToQueue(
        LoanRequestReturnToQueueRequest $request,
        LoanRequest $loanRequest,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $assignmentService->returnToQueue(
            $loanRequest,
            $actor,
            $request->validated('reason'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function startReview(
        LoanRequestStartReviewRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->startReview(
            $loanRequest,
            $actor,
            $request->validated('remarks'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function requestRevision(
        LoanRequestRequestRevisionRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->requestRevision(
            $loanRequest,
            $actor,
            $request->validated('remarks'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function reject(
        LoanRequestRejectRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->reject(
            $loanRequest,
            $actor,
            $request->validated('rejection_reason'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function recommendApproval(
        LoanRequestRecommendApprovalRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->recommendApproval(
            $loanRequest,
            $actor,
            $request->validated('review_remarks'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function approve(
        LoanRequestWorkflowApproveRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->approveByManager(
            $loanRequest,
            $actor,
            $request->validated(),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function decline(
        LoanRequestWorkflowDeclineRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->declineByManager(
            $loanRequest,
            $actor,
            $request->validated('decline_category'),
            $request->validated('decline_reason'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function updateProcessingDetails(
        LoanRequestProcessingUpdateRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->updateProcessingDetails(
            $loanRequest,
            $actor,
            $request->validated(),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function previewProcessingDetails(
        LoanRequestRecommendationPreviewRequest $request,
        LoanRequest $loanRequest,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
    ): JsonResponse {
        abort_unless($request->user() instanceof AppUser, 403);

        return response()->json([
            'data' => $documentWorkflowService->previewRecommendationFigures(
                $loanRequest,
                $request->validated(),
            ),
        ]);
    }

    public function requestMemberAction(
        LoanRequestRequestMemberActionRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->requestMemberAction(
            $loanRequest,
            $actor,
            $request->validated(),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function rejectDuringProcessing(
        LoanRequestRejectDuringProcessingRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->rejectDuringProcessing(
            $loanRequest,
            $actor,
            $request->resolvedRejectionCategory(),
            $request->validated('member_visible_reason'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function generateDocuments(
        LoanRequestGenerateDocumentsRequest $request,
        LoanRequest $loanRequest,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $documentKey = $request->validated('document_key');
        $results = $documentKey !== null
            ? [
                [
                    'key' => LoanRequestDocumentKey::from($documentKey)->value,
                    'status' => $documentWorkflowService
                        ->generateDocument(
                            $loanRequest,
                            LoanRequestDocumentKey::from($documentKey),
                            $actor,
                        )
                        ->readiness_status?->value,
                    'message' => null,
                ],
            ]
            : $documentWorkflowService->generateAll($loanRequest, $actor);

        return $this->response(
            $loanRequest->refresh(),
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
            [
                'documentResults' => $results,
            ],
        );
    }

    public function returnForProcessing(
        LoanRequestReturnForProcessingRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->returnForProcessing(
            $loanRequest,
            $actor,
            $request->validated('reason'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function reopen(
        LoanRequestReopenRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->reopenRejectedRequest(
            $loanRequest,
            $actor,
            $request->validated('reason'),
            (bool) $request->validated('retain_assignment', false),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    public function upgradeWorkflow(
        LoanRequestUpgradeWorkflowRequest $request,
        LoanRequest $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $processingService->upgradeWorkflowVersion(
            $loanRequest,
            $actor,
            $request->validated('reason'),
        );

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
            $documentWorkflowService,
            $dataService,
        );
    }

    private function response(
        LoanRequest $loanRequest,
        AppUser $actor,
        LoanRequestPayloadSerializer $serializer,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestDataService $dataService,
        array $extra = [],
    ): JsonResponse {
        $detail = $serializer->serializeDetail($loanRequest);

        return response()->json([
            'ok' => true,
            'data' => [
                ...$detail,
                'loanRequest' => [
                    ...$detail['loanRequest'],
                    ...$assignmentService->capabilitiesFor(
                        $loanRequest,
                        $actor,
                    ),
                ],
                'auditTrail' => $serializer->serializeAuditTrail($loanRequest),
                'notificationHistory' => $serializer->serializeNotificationHistory(
                    $loanRequest,
                ),
                'workflowHealth' => $serializer->serializeWorkflowHealth(
                    $loanRequest,
                ),
                'correctionReports' => $serializer->serializeCorrectionReports(
                    $loanRequest,
                ),
                'eligibleOfficers' => $assignmentService->canManageAssignments(
                    $actor,
                )
                    ? $assignmentService->eligibleOfficerOptions($loanRequest)
                    : [],
                'dataSections' => $dataService->serializeSections($loanRequest),
                'dataSectionDefinitions' => $dataService->sectionDefinitions(),
                'cycleState' => app(LoanRequestCycleStateService::class)->resolveState($loanRequest),
                'documentChecklist' => $documentWorkflowService->serializeChecklist(
                    $loanRequest,
                ),
                ...$extra,
            ],
        ]);
    }
}
