<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\LoanRequestConvertToLoanRequest;
use App\Http\Requests\Workflow\LoanRequestRecommendApprovalRequest;
use App\Http\Requests\Workflow\LoanRequestRejectRequest;
use App\Http\Requests\Workflow\LoanRequestRequestRevisionRequest;
use App\Http\Requests\Workflow\LoanRequestStartReviewRequest;
use App\Http\Requests\Workflow\LoanRequestWorkflowApproveRequest;
use App\Http\Requests\Workflow\LoanRequestWorkflowDeclineRequest;
use App\Jobs\SendLoanDecisionSmsJob;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestWorkflowService;
use Illuminate\Http\JsonResponse;

class LoanRequestWorkflowController extends Controller
{
    public function startReview(
        LoanRequestStartReviewRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->startReview(
            $loanRequest,
            $actor,
            $request->validated('remarks'),
        );

        return $this->response($updated, $serializer);
    }

    public function requestRevision(
        LoanRequestRequestRevisionRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->requestRevision(
            $loanRequest,
            $actor,
            $request->validated('remarks'),
        );

        return $this->response($updated, $serializer);
    }

    public function reject(
        LoanRequestRejectRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->reject(
            $loanRequest,
            $actor,
            $request->validated('rejection_reason'),
        );

        return $this->response($updated, $serializer);
    }

    public function recommendApproval(
        LoanRequestRecommendApprovalRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->recommendApproval(
            $loanRequest,
            $actor,
            $request->validated('review_remarks'),
        );

        return $this->response($updated, $serializer);
    }

    public function approve(
        LoanRequestWorkflowApproveRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
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

        return $this->response($updated, $serializer);
    }

    public function decline(
        LoanRequestWorkflowDeclineRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
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

        return $this->response($updated, $serializer);
    }

    public function convertToLoan(
        LoanRequestConvertToLoanRequest $request,
        LoanRequest $loanRequest,
        LoanRequestWorkflowService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $result = $service->convertToLoan(
            $loanRequest,
            $actor,
            $request->validated('remarks'),
        );

        return $this->response(
            $result['loanRequest'],
            $serializer,
            [
                'loan' => $result['loan'],
            ],
        );
    }

    private function response(
        LoanRequest $loanRequest,
        LoanRequestPayloadSerializer $serializer,
        array $extra = [],
    ): JsonResponse {
        return response()->json([
            'ok' => true,
            'data' => [
                ...$serializer->serializeDetail($loanRequest),
                'correctionReports' => $serializer->serializeCorrectionReports(
                    $loanRequest,
                ),
                ...$extra,
            ],
        ]);
    }
}
