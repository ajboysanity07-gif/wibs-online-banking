<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateLoanRequestPaymentMethodRequest;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class LoanRequestPaymentMethodController extends Controller
{
    public function update(
        UpdateLoanRequestPaymentMethodRequest $request,
        int $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            return redirect()->route('login');
        }

        $loanRequestRecord = $this->findLoanRequestForUser($user, $loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        $updated = $processingService->updatePaymentMethodByMember(
            $loanRequestRecord,
            $user,
            $request->validated(),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest($updated),
            ],
        ]);
    }

    private function findLoanRequestForUser(AppUser $user, int $loanRequestId): ?LoanRequest
    {
        $loanRequest = LoanRequest::query()
            ->whereKey($loanRequestId)
            ->where('user_id', $user->user_id)
            ->first();

        if ($loanRequest !== null) {
            return $loanRequest;
        }

        $existing = LoanRequest::query()
            ->select(['id', 'user_id', 'acctno', 'status'])
            ->whereKey($loanRequestId)
            ->first();

        $status = null;

        if ($existing !== null) {
            $status = $existing->status instanceof LoanRequestStatus
                ? $existing->status->value
                : (string) $existing->status;
        }

        Log::warning('Loan request ownership mismatch or missing record.', [
            'context' => 'update-payment-method',
            'loan_request_id' => $loanRequestId,
            'auth_user_id' => $user->user_id,
            'auth_acctno' => $user->acctno,
            'record_user_id' => $existing?->user_id,
            'record_acctno' => $existing?->acctno,
            'record_status' => $status,
        ]);

        return null;
    }
}
