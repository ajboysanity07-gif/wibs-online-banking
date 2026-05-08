<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoanRequestApproveRequest;
use App\Http\Requests\Admin\LoanRequestDeclineRequest;
use App\Jobs\SendLoanDecisionSmsJob;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
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
}
