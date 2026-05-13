<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoanRequestAdminCorrectedCopyRequest;
use App\Http\Requests\Admin\LoanRequestApproveRequest;
use App\Http\Requests\Admin\LoanRequestCancelRequest;
use App\Http\Requests\Admin\LoanRequestDeclineRequest;
use App\Jobs\SendLoanDecisionSmsJob;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Http\JsonResponse;

class LoanRequestDecisionController extends Controller
{
    public function approve(
        LoanRequestApproveRequest $request,
        LoanRequest $loanRequest,
        LoanRequestDecisionService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $updated = $service->approve(
            $loanRequest,
            $request->user(),
            $request->validated(),
        );

        SendLoanDecisionSmsJob::dispatch($updated->id)->afterCommit();

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest($updated),
            ],
        ]);
    }

    public function decline(
        LoanRequestDeclineRequest $request,
        LoanRequest $loanRequest,
        LoanRequestDecisionService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $payload = $request->validated();
        $updated = $service->decline(
            $loanRequest,
            $request->user(),
            $payload['decision_notes'] ?? null,
        );

        SendLoanDecisionSmsJob::dispatch($updated->id)->afterCommit();

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest($updated),
            ],
        ]);
    }

    public function cancel(
        LoanRequestCancelRequest $request,
        LoanRequest $loanRequest,
        LoanRequestDecisionService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $payload = $request->validated();
        $updated = $service->cancelApprovedRequest(
            $loanRequest,
            $request->user(),
            $payload['cancellation_reason'],
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest($updated),
            ],
        ]);
    }

    public function createAdminCorrectedCopy(
        LoanRequestAdminCorrectedCopyRequest $request,
        LoanRequest $loanRequest,
        LoanRequestService $service,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $payload = $request->validated();
        $correctedLoanRequest = $service->createAdminCorrectedCopyFromCancelledRequest(
            $loanRequest,
            $actor,
            $payload['correction_reason'],
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => [
                    'id' => $correctedLoanRequest->id,
                    'reference' => $correctedLoanRequest->reference,
                    'url' => route('admin.requests.show', $correctedLoanRequest),
                ],
            ],
        ]);
    }
}
