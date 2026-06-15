<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\LoanRequestDocumentKey;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestAssignmentService;
use App\Services\LoanRequests\LoanRequestDataService;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestPdfService;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoanRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('staff/loan-requests');
    }

    public function show(
        Request $request,
        LoanRequest $loanRequest,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDecisionService $decisionService,
        LoanRequestDataService $dataService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanRequestPayloadSerializer $serializer,
        LoanWorkflowWorkspaceService $workspaceService,
    ): Response {
        if ($this->isDraft($loanRequest)) {
            abort(404);
        }

        Gate::authorize('view', $loanRequest);

        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $detail = $serializer->serializeDetail($loanRequest);

        $payload = $this->sanitizePayload([
            ...$detail,
            'loanRequest' => [
                ...$detail['loanRequest'],
                ...$assignmentService->capabilitiesFor($loanRequest, $actor),
            ],
            'auditTrail' => $serializer->serializeAuditTrail($loanRequest),
            'workflowPermissions' => $workspaceService->workflowPermissions($actor),
            'workflowContext' => [
                'isOwnRequest' => $decisionService->isOwnRequest(
                    $loanRequest,
                    $actor,
                ),
            ],
            'eligibleOfficers' => $assignmentService->canManageAssignments(
                $actor,
            )
                ? $assignmentService->eligibleOfficerOptions($loanRequest)
                : [],
            'dataSections' => $dataService->serializeSections($loanRequest),
            'dataSectionDefinitions' => $dataService->sectionDefinitions(),
            'documentChecklist' => $documentWorkflowService->serializeChecklist(
                $loanRequest,
            ),
            'memberAction' => [
                'type' => $loanRequest->member_action_type,
                'message' => $loanRequest->member_action_message,
                'fields' => $loanRequest->member_action_fields_json,
                'requested_at' => $loanRequest->member_action_requested_at?->toDateTimeString(),
                'resolved_at' => $loanRequest->member_action_resolved_at?->toDateTimeString(),
            ],
        ]);

        return Inertia::render('staff/loan-request-show', $payload);
    }

    public function pdf(
        Request $request,
        LoanRequest $loanRequest,
        LoanRequestPdfService $pdfService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->canViewPdf($loanRequest)) {
            abort(404);
        }

        return $pdfService->render(
            $loanRequest,
            $request->boolean('download'),
        );
    }

    public function print(
        LoanRequest $loanRequest,
        LoanRequestPdfService $pdfService,
    ): View {
        Gate::authorize('view', $loanRequest);

        if (! $this->canViewPdf($loanRequest)) {
            abort(404);
        }

        return $pdfService->renderPrintView($loanRequest);
    }

    public function approvedDocuments(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->packageZip($loanRequest);
    }

    public function applicationFormDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->canViewPdf($loanRequest)) {
            abort(404);
        }

        return $documentService->applicationForm($loanRequest);
    }

    public function grepalifeDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->grepalife($loanRequest);
    }

    public function loanSecurityAgreementDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->loanSecurityAgreement($loanRequest);
    }

    public function planOfPaymentDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->planOfPayment($loanRequest);
    }

    public function undertakingBarangayDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->undertakingBarangay($loanRequest);
    }

    public function affidavitUndertakingDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->affidavitUndertaking($loanRequest);
    }

    public function authorizationDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $this->authorize('view', $loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->authorization($loanRequest);
    }

    public function generatedDocument(
        Request $request,
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);

        $document = $loanRequest->documents()
            ->where('document_key', $documentKey->value)
            ->first();

        if (
            $document === null
            || ! $document->is_applicable
            || $document->generated_path === null
            || $document->generated_path === ''
        ) {
            abort(404);
        }

        $disk = $document->generated_disk ?: 'local';

        abort_unless(Storage::disk($disk)->exists($document->generated_path), 404);

        $headers = $document->generated_mime_type !== null
            ? ['Content-Type' => $document->generated_mime_type]
            : [];

        if ($request->boolean('download')) {
            return Storage::disk($disk)->download(
                $document->generated_path,
                $document->generated_filename,
                $headers,
            );
        }

        return Storage::disk($disk)->response(
            $document->generated_path,
            $document->generated_filename,
            $headers,
        );
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
        $status = LoanRequestStatus::normalizeValue($loanRequest->status)
            ?? (string) $loanRequest->status;

        return in_array($status, [
            LoanRequestStatus::PendingReview->value,
            LoanRequestStatus::UnderReview->value,
            LoanRequestStatus::Approved->value,
            LoanRequestStatus::ConvertedToLoan->value,
            LoanRequestStatus::Declined->value,
            LoanRequestStatus::Cancelled->value,
        ], true);
    }

    private function hasApprovedDocumentsStatus(LoanRequest $loanRequest): bool
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return in_array($status, [
            LoanRequestStatus::Approved->value,
            LoanRequestStatus::ConvertedToLoan->value,
        ], true);
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
