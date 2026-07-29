<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\LoanRequestDocumentKey;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\DocumentAccessLog;
use App\Models\LoanRequest;
use App\Models\Permission;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestAssignmentService;
use App\Services\LoanRequests\LoanRequestDataService;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestDocumentStorage;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestPdfService;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as HtmlWriter;
use RuntimeException;
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
                'wibs_loan_reference' => $loanRequest->wibs_loan_reference,
            ],
            'auditTrail' => $serializer->serializeAuditTrail($loanRequest),
            'notificationHistory' => $serializer->serializeNotificationHistory(
                $loanRequest,
            ),
            'workflowHealth' => $serializer->serializeWorkflowHealth(
                $loanRequest,
            ),
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
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->canViewPdf($loanRequest)) {
            abort(404);
        }

        DocumentAccessLog::record(
            (int) $request->user()?->user_id,
            (int) $loanRequest->id,
            'loan_request_pdf',
            $request->boolean('download') ? DocumentAccessLog::ACTION_DOWNLOAD : DocumentAccessLog::ACTION_VIEW,
        );

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
        $this->authorizeStaffDocumentAccess($loanRequest);

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
        $this->authorizeStaffDocumentAccess($loanRequest);

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
        $this->authorizeStaffDocumentAccess($loanRequest);

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
        $this->authorizeStaffDocumentAccess($loanRequest);

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
        $this->authorizeStaffDocumentAccess($loanRequest);

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
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->planOfPayment($loanRequest);
    }

    public function loanInformationDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasPreAcceptanceDisclosureStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->loanInformation($loanRequest);
    }

    public function disclosureStatementDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasPreAcceptanceDisclosureStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->disclosureStatement($loanRequest);
    }

    public function promissoryNoteDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->promissoryNote($loanRequest);
    }

    public function undertakingBarangayDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

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
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->affidavitUndertaking($loanRequest);
    }

    public function generaliDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->generali($loanRequest);
    }

    public function authorityToDeductDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->authorityToDeduct($loanRequest);
    }

    public function depedSalaryDeductionWaiverDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->depedSalaryDeductionWaiver($loanRequest);
    }

    public function pensionDeductionWaiverDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->pensionDeductionWaiver($loanRequest);
    }

    public function generaliApplicationFormDocument(
        LoanRequest $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

        if (! $this->hasApprovedDocumentsStatus($loanRequest)) {
            abort(404);
        }

        return $documentService->generaliApplicationForm($loanRequest);
    }

    public function generatedDocument(
        Request $request,
        LoanRequest $loanRequest,
        LoanRequestDocumentKey $documentKey,
        LoanRequestDocumentStorage $documentStorage,
    ): HttpResponse {
        Gate::authorize('view', $loanRequest);
        $this->authorizeStaffDocumentAccess($loanRequest);

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

        $disk = $document->generated_disk ?: $documentStorage->documentDisk();

        try {
            $absolutePath = $documentStorage->absolutePath(
                $document->generated_path,
                $disk,
            );
        } catch (RuntimeException) {
            abort(404);
        }

        abort_unless(is_file($absolutePath), 404);

        $headers = $document->generated_mime_type !== null
            ? ['Content-Type' => $document->generated_mime_type]
            : [];
        // Regenerating a document keeps the same URL but changes the file
        // content, so the response must never be cached -- otherwise staff
        // can be shown a stale, already-regenerated document.
        $headers['Cache-Control'] = 'no-store, must-revalidate';
        $downloadName = basename(
            $document->generated_filename ?: $documentKey->label(),
        );

        if (
            $this->isWorkbookDocument($documentKey)
            && ($request->boolean('preview') || $request->boolean('print'))
        ) {
            return $this->renderWorkbookDocument(
                $absolutePath,
                $downloadName !== '' ? $downloadName : $documentKey->label().'.xlsx',
                $request->boolean('print'),
            );
        }

        $action = $request->boolean('download')
            ? DocumentAccessLog::ACTION_DOWNLOAD
            : DocumentAccessLog::ACTION_VIEW;

        DocumentAccessLog::record(
            (int) $request->user()?->user_id,
            (int) $loanRequest->id,
            $documentKey->value,
            $action,
        );

        if ($action === DocumentAccessLog::ACTION_DOWNLOAD) {
            return response()->download(
                $absolutePath,
                $downloadName !== '' ? $downloadName : null,
                $headers,
            );
        }

        return response()->file(
            $absolutePath,
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

    /**
     * Truth-in-lending–style disclosures (loan_information, disclosure_statement) must
     * reach the member before they accept the loan's terms, not only afterward -- so
     * these two documents unlock one workflow step earlier than the rest of the package.
     */
    private function hasPreAcceptanceDisclosureStatus(LoanRequest $loanRequest): bool
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return in_array($status, [
            LoanRequestStatus::AwaitingMemberAcceptance->value,
            LoanRequestStatus::Approved->value,
            LoanRequestStatus::ConvertedToLoan->value,
        ], true);
    }

    private function authorizeStaffDocumentAccess(LoanRequest $loanRequest): void
    {
        $actor = request()->user();

        abort_unless($actor instanceof AppUser, 403);

        if (
            $loanRequest->assigned_officer_id === $actor->user_id
            || $actor->hasPermission(Permission::LOAN_MANAGE_ASSIGNMENT)
            || $actor->hasPermission(Permission::LOAN_APPROVE)
            || $actor->hasPermission(Permission::LOAN_DECLINE)
            || $actor->isSuperadmin()
        ) {
            return;
        }

        abort(403);
    }

    private function isWorkbookDocument(LoanRequestDocumentKey $documentKey): bool
    {
        return in_array(
            $documentKey,
            LoanRequestDocumentKey::workbookDocuments(),
            true,
        );
    }

    private function renderWorkbookDocument(
        string $absolutePath,
        string $filename,
        bool $autoPrint,
    ): HttpResponse {
        $spreadsheet = IOFactory::load($absolutePath);
        $writer = IOFactory::createWriter($spreadsheet, 'Html');

        if ($writer instanceof HtmlWriter) {
            $writer
                ->setSheetIndex(0)
                ->setEmbedImages(true)
                ->setUseInlineCss(true)
                ->setPreCalculateFormulas(true);
        }

        ob_start();
        $writer->save('php://output');
        $html = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response(
            $this->decorateWorkbookPreviewHtml($html, $filename, $autoPrint),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    private function decorateWorkbookPreviewHtml(
        string $html,
        string $title,
        bool $autoPrint,
    ): string {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $headMarkup = <<<HTML
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>{$safeTitle}</title>
<style>
body {
    margin: 0;
    padding: 24px;
    background: #f5f7fb;
    color: #0f172a;
}
table.sheet {
    width: min(100%, 1100px);
    margin: 0 auto 24px;
    background: #ffffff;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
}
.navigation {
    width: min(100%, 1100px);
    margin: 0 auto 16px;
    padding: 0;
}
.navigation li {
    list-style: none;
}
@media print {
    body {
        padding: 0;
        background: #ffffff;
    }
    table.sheet {
        width: 100%;
        margin: 0;
        box-shadow: none;
    }
}
</style>
HTML;

        $bodyMarkup = $autoPrint
            ? <<<'HTML'
<script>
(() => {
    let printed = false;

    const triggerPrint = () => {
        if (printed) {
            return;
        }

        printed = true;
        window.print();
    };

    window.addEventListener('load', () => {
        setTimeout(triggerPrint, 100);
    });
})();
</script>
HTML
            : '';

        $html = preg_replace('/<\/head>/i', $headMarkup.'</head>', $html, 1) ?? $html;

        if ($bodyMarkup !== '') {
            $html = preg_replace('/<\/body>/i', $bodyMarkup.'</body>', $html, 1) ?? $html;
        }

        return $html;
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
