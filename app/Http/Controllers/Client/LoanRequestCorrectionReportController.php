<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\LoanRequestCorrectionReportStoreRequest;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestCorrectionReportService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use Illuminate\Http\JsonResponse;

class LoanRequestCorrectionReportController extends Controller
{
    public function store(
        LoanRequestCorrectionReportStoreRequest $request,
        LoanRequest $loanRequest,
        LoanRequestCorrectionReportService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $report = $service->createForMember(
            $loanRequest,
            $actor,
            $request->validated(),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'report' => $serializer->serializeCorrectionReport($report),
            ],
        ]);
    }
}
