<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestPdfService;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoanRequestController extends Controller
{
    public function show(
        Request $request,
        LoanRequestDecisionService $decisionService,
        LoanRequestPayloadSerializer $serializer,
        LoanRequestService $loanRequestService,
        int $loanRequest,
    ): Response {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        if ($this->isDraft($loanRequestRecord)) {
            abort(404);
        }

        $loanRequestRecord->loadMissing('people', 'reviewedBy', 'cancelledBy');
        $actor = $request->user();
        $decision = [
            'canDecide' => false,
            'isOwnRequest' => false,
        ];

        if ($actor instanceof AppUser) {
            $decision = [
                'canDecide' => $decisionService->canDecide(
                    $loanRequestRecord,
                    $actor,
                ),
                'isOwnRequest' => $decisionService->isOwnRequest(
                    $loanRequestRecord,
                    $actor,
                ),
            ];
        }

        $correctionReportSource = $this->resolveCorrectionReportSource(
            $loanRequestRecord,
        );
        $payload = $this->sanitizePayload([
            ...$serializer->serializeDetail($loanRequestRecord),
            'decision' => $decision,
            'loanTypes' => $loanRequestService->getLoanTypes()->values()->all(),
            'correctionReports' => $serializer->serializeCorrectionReports(
                $correctionReportSource,
            ),
            'openCorrectionReportCancellationReason' => $this
                ->resolveOpenCorrectionCancellationReason(
                    $correctionReportSource,
                ),
            'openCorrectionOnLoad' => $request->boolean('openCorrection'),
        ]);

        return Inertia::render('admin/loan-request-show', $payload);
    }

    public function pdf(
        Request $request,
        int $loanRequest,
        LoanRequestPdfService $pdfService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        if (! $this->canViewPdf($loanRequestRecord)) {
            abort(404);
        }

        return $pdfService->render(
            $loanRequestRecord,
            $request->boolean('download'),
        );
    }

    public function print(
        int $loanRequest,
        LoanRequestPdfService $pdfService,
    ): View {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        if (! $this->canViewPdf($loanRequestRecord)) {
            abort(404);
        }

        return $pdfService->renderPrintView($loanRequestRecord);
    }

    private function findLoanRequest(int $loanRequestId): ?LoanRequest
    {
        return LoanRequest::query()
            ->whereKey($loanRequestId)
            ->first();
    }

    private function isDraft(LoanRequest $loanRequest): bool
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return $status === LoanRequestStatus::Draft->value;
    }

    private function canViewPdf(LoanRequest $loanRequest): bool
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if ($status === LoanRequestStatus::Submitted->value) {
            $status = LoanRequestStatus::UnderReview->value;
        }

        return in_array($status, [
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::Approved->value,
            LoanRequestStatus::Declined->value,
            LoanRequestStatus::Cancelled->value,
        ], true);
    }

    private function resolveCorrectionReportSource(
        LoanRequest $loanRequest,
    ): LoanRequest {
        if ($loanRequest->correctionReports()->exists()) {
            return $loanRequest;
        }

        $loanRequest->loadMissing('correctedFrom');

        return $loanRequest->correctedFrom ?? $loanRequest;
    }

    private function resolveOpenCorrectionCancellationReason(
        LoanRequest $loanRequest,
    ): ?string {
        $latestOpenReport = $loanRequest->correctionReports()
            ->where('status', LoanRequestCorrectionReport::STATUS_OPEN)
            ->latest('id')
            ->first();

        if ($latestOpenReport === null) {
            return null;
        }

        return sprintf(
            'Member reported incorrect details: %s. Correct information: %s.',
            $latestOpenReport->issue_description,
            $latestOpenReport->correct_information,
        );
    }

    private function sanitizePayload(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizePayload($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        return $value;
    }

    private function sanitizeString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding(
                $value,
                'UTF-8',
                'UTF-8,ISO-8859-1,Windows-1252',
            );

            if (is_string($converted) && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($converted === false) {
            return '';
        }

        return $converted;
    }
}
