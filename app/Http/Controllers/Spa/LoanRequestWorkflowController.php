<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\LoanRequestAssignmentUpdateRequest;
use App\Http\Requests\Workflow\LoanRequestClaimRequest;
use App\Http\Requests\Workflow\LoanRequestRecommendApprovalRequest;
use App\Http\Requests\Workflow\LoanRequestRejectRequest;
use App\Http\Requests\Workflow\LoanRequestRequestRevisionRequest;
use App\Http\Requests\Workflow\LoanRequestReturnToQueueRequest;
use App\Http\Requests\Workflow\LoanRequestStartReviewRequest;
use App\Http\Requests\Workflow\LoanRequestWorkflowApproveRequest;
use App\Http\Requests\Workflow\LoanRequestWorkflowDeclineRequest;
use App\Jobs\SendLoanDecisionSmsJob;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestAssignmentService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestWorkflowService;
use Illuminate\Http\JsonResponse;

class LoanRequestWorkflowController extends Controller
{
    public function claim(
        LoanRequestClaimRequest $request,
        LoanRequest $loanRequest,
        LoanRequestAssignmentService $assignmentService,
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
        );
    }

    public function updateAssignment(
        LoanRequestAssignmentUpdateRequest $request,
        LoanRequest $loanRequest,
        LoanRequestAssignmentService $assignmentService,
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
        );
    }

    public function returnToQueue(
        LoanRequestReturnToQueueRequest $request,
        LoanRequest $loanRequest,
        LoanRequestAssignmentService $assignmentService,
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
        );
    }

    public function startReview(
        LoanRequestStartReviewRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
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
        );
    }

    public function requestRevision(
        LoanRequestRequestRevisionRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
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
        );
    }

    public function reject(
        LoanRequestRejectRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
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
        );
    }

    public function recommendApproval(
        LoanRequestRecommendApprovalRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
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
        );
    }

    public function approve(
        LoanRequestWorkflowApproveRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->approve(
            $loanRequest,
            $actor,
            $request->validated(),
        );

        SendLoanDecisionSmsJob::dispatch($updated->id)->afterCommit();

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
        );
    }

    public function decline(
        LoanRequestWorkflowDeclineRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->decline(
            $loanRequest,
            $actor,
            $request->validated('decline_reason'),
        );

        SendLoanDecisionSmsJob::dispatch($updated->id)->afterCommit();

        return $this->response(
            $updated,
            $actor,
            $serializer,
            $assignmentService,
        );
    }

    private function response(
        LoanRequest $loanRequest,
        AppUser $actor,
        LoanRequestPayloadSerializer $serializer,
        LoanRequestAssignmentService $assignmentService,
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
                'correctionReports' => $serializer->serializeCorrectionReports(
                    $loanRequest,
                ),
                'eligibleOfficers' => $assignmentService->canManageAssignments(
                    $actor,
                )
                    ? $assignmentService->eligibleOfficerOptions($loanRequest)
                    : [],
                ...$extra,
            ],
        ]);
    }
}
