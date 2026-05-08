<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoanRequestCorrectionRequest;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestCorrectionService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use Illuminate\Http\JsonResponse;

class LoanRequestCorrectionController extends Controller
{
    public function __invoke(
        LoanRequestCorrectionRequest $request,
        LoanRequest $loanRequest,
        LoanRequestCorrectionService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updated = $service->correct(
            $loanRequest,
            $actor,
            $request->validated(),
        );

        return response()->json([
            'ok' => true,
            'data' => $serializer->serializeDetail($updated),
        ]);
    }
}
