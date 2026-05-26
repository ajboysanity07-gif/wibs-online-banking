<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestSignatureLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoanRequestSignatureLinkController extends Controller
{
    public function store(
        LoanRequest $loanRequest,
        string $role,
        LoanRequestSignatureLinkService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $resolvedRole = LoanRequestPersonRole::tryFrom($role);

        if (! in_array($resolvedRole, [
            LoanRequestPersonRole::CoMakerOne,
            LoanRequestPersonRole::CoMakerTwo,
        ], true)) {
            abort(404);
        }

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if ($status !== LoanRequestStatus::PendingCoMakerSignatures->value) {
            throw ValidationException::withMessages([
                'status' => 'Signature links can only be generated while the request is waiting for co-maker signatures.',
            ]);
        }

        $generated = $service->generateForRole($loanRequest, $resolvedRole);
        $signatureStates = $service->getSignatureStates($loanRequest);

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest($loanRequest),
                'coMakerOneSignature' => $signatureStates['coMakerOneSignature'],
                'coMakerTwoSignature' => $signatureStates['coMakerTwoSignature'],
                'signingLink' => [
                    'role' => $resolvedRole->value,
                    'loan_request_person_id' => $generated['link']->loan_request_person_id,
                    'status' => LoanRequestSignatureLinkService::STATE_LINK_ACTIVE,
                    'signing_url' => $generated['signing_url'],
                    'url' => $generated['signing_url'],
                    'expires_at' => $generated['link']->expires_at?->toDateTimeString(),
                ],
                'signing_url' => $generated['signing_url'],
                'expires_at' => $generated['link']->expires_at?->toDateTimeString(),
                'status' => LoanRequestSignatureLinkService::STATE_LINK_ACTIVE,
                'role' => $resolvedRole->value,
                'loan_request_person_id' => $generated['link']->loan_request_person_id,
            ],
        ]);
    }
}
