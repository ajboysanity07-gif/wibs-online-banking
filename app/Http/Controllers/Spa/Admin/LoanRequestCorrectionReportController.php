<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoanRequestCorrectionReportDismissRequest;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Services\LoanRequests\LoanRequestCorrectionReportService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use Illuminate\Http\JsonResponse;

class LoanRequestCorrectionReportController extends Controller
{
    public function dismiss(
        LoanRequestCorrectionReportDismissRequest $request,
        LoanRequest $loanRequest,
        LoanRequestCorrectionReport $report,
        LoanRequestCorrectionReportService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $updatedReport = $service->dismiss(
            $loanRequest,
            $report,
            $actor,
            $request->validated('admin_notes'),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'report' => $serializer->serializeCorrectionReport(
                    $updatedReport,
                ),
                'correctionReports' => $serializer->serializeCorrectionReports(
                    $loanRequest,
                ),
            ],
        ]);
    }
}
