<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoanRequestApproveRequest;
use App\Http\Requests\Admin\LoanRequestDeclineRequest;
use App\Jobs\SendLoanDecisionSmsJob;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestDecisionService;
use Illuminate\Http\JsonResponse;

class LoanRequestDecisionController extends Controller
{
    public function approve(
        LoanRequestApproveRequest $request,
        LoanRequest $loanRequest,
        LoanRequestDecisionService $service,
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
                'loanRequest' => $this->serializeLoanRequest($updated),
            ],
        ]);
    }

    public function decline(
        LoanRequestDeclineRequest $request,
        LoanRequest $loanRequest,
        LoanRequestDecisionService $service,
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
                'loanRequest' => $this->serializeLoanRequest($updated),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLoanRequest(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing('reviewedBy');

        return [
            'id' => $loanRequest->id,
            'status' => $this->normalizeStatus($loanRequest),
            'typecode' => $loanRequest->typecode,
            'loan_type_label_snapshot' => $loanRequest->loan_type_label_snapshot,
            'requested_amount' => $loanRequest->requested_amount,
            'requested_term' => $loanRequest->requested_term,
            'loan_purpose' => $loanRequest->loan_purpose,
            'availment_status' => $loanRequest->availment_status,
            'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
            'reviewed_by' => $loanRequest->reviewedBy
                ? [
                    'user_id' => $loanRequest->reviewedBy->user_id,
                    'name' => $loanRequest->reviewedBy->name,
                ]
                : null,
            'reviewed_at' => $loanRequest->reviewed_at?->toDateTimeString(),
            'approved_amount' => $loanRequest->approved_amount,
            'approved_term' => $loanRequest->approved_term,
            'decision_notes' => $loanRequest->decision_notes,
            'acctno' => $loanRequest->acctno,
        ];
    }

    private function normalizeStatus(LoanRequest $loanRequest): string
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if ($status === LoanRequestStatus::Submitted->value) {
            return LoanRequestStatus::UnderReview->value;
        }

        return $status;
    }
}
