<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\LoanRequestGenerateSignatureLinkRequest;
use App\LoanRequestPersonRole;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LoanRequestSignatureLinkController extends Controller
{
    public function store(
        LoanRequestGenerateSignatureLinkRequest $request,
        string $role,
        LoanRequestService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $resolvedRole = LoanRequestPersonRole::tryFrom($role);

        if (! in_array($resolvedRole, [
            LoanRequestPersonRole::CoMakerOne,
            LoanRequestPersonRole::CoMakerTwo,
        ], true)) {
            abort(404);
        }

        $result = $service->generateCoMakerSignatureLink(
            $user,
            $resolvedRole,
            $request->validated(),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest(
                    $result['loanRequest'],
                ),
                'coMakerOneSignature' => $result['coMakerOneSignature'],
                'coMakerTwoSignature' => $result['coMakerTwoSignature'],
                'signingLink' => $result['signingLink'],
                'signing_url' => $result['signingLink']['signing_url'],
                'expires_at' => $result['signingLink']['expires_at'],
            ],
        ]);
    }
}
