<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LoanDocumentPackageJobStatus;
use App\LoanRequestDocumentKey;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\DocumentAccessLog;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Models\Permission;
use App\Services\LoanRequests\ApprovedLoanDocumentPackageJobService;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanManagerWitnessResolver;
use App\Services\LoanRequests\LoanRequestAssignmentService;
use App\Services\LoanRequests\LoanRequestCycleStateService;
use App\Services\LoanRequests\LoanRequestDataService;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestDocumentStorage;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestPdfService;
use App\Services\LoanRequests\LoanRequestService;
use App\Support\DocumentFilename;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
    public function show(
        Request $request,
        LoanRequestAssignmentService $assignmentService,
        LoanRequestDataService $dataService,
        LoanRequestDecisionService $decisionService,
        LoanRequestPayloadSerializer $serializer,
        LoanRequestService $loanRequestService,
        LoanRequestDocumentWorkflowService $documentWorkflowService,
        LoanManagerWitnessResolver $loanManagerWitnessResolver,
        LoanRequestCycleStateService $cycleStateService,
        int $loanRequest,
    ): Response {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);

        if ($this->isDraft($loanRequestRecord)) {
            abort(404);
        }

        $loanRequestRecord->loadMissing('people', 'reviewedBy', 'cancelledBy');
        $actor = $request->user();
        $decision = [
            'canDecide' => false,
            'canCancel' => false,
            'isOwnRequest' => false,
            'blockedMessage' => null,
            'approverName' => null,
        ];

        if ($actor instanceof AppUser) {
            $actor->loadMissing('adminProfile', 'roles.permissions');

            $decision = [
                'canDecide' => $decisionService->canDecide(
                    $loanRequestRecord,
                    $actor,
                ),
                'canCancel' => $decisionService->canCancel(
                    $loanRequestRecord,
                    $actor,
                ),
                'isOwnRequest' => $decisionService->isOwnRequest(
                    $loanRequestRecord,
                    $actor,
                ),
                'blockedMessage' => $decisionService->approvalBlockedMessage(
                    $loanRequestRecord,
                    $actor,
                ),
                'approverName' => $actor->adminProfile?->fullname ?? $actor->name,
            ];
        }

        $correctionReportSource = $this->resolveCorrectionReportSource(
            $loanRequestRecord,
        );
        $detail = $serializer->serializeDetail($loanRequestRecord);

        $payload = $this->sanitizePayload([
            ...$detail,
            'loanRequest' => [
                ...$detail['loanRequest'],
                ...$assignmentService->capabilitiesFor(
                    $loanRequestRecord,
                    $actor instanceof AppUser ? $actor : null,
                ),
            ],
            'eligibleOfficers' => $actor instanceof AppUser
                && $assignmentService->canManageAssignments($actor)
                ? $assignmentService->eligibleOfficerOptions($loanRequestRecord)
                : [],
            'loanManagers' => $loanManagerWitnessResolver->options(),
            'auditTrail' => $serializer->serializeAuditTrail($loanRequestRecord),
            'decision' => $decision,
            'workflowPermissions' => $this->resolveWorkflowPermissions($actor),
            'loanTypes' => $loanRequestService->getLoanTypes()->values()->all(),
            'dataSections' => $dataService->serializeSections($loanRequestRecord),
            'dataSectionDefinitions' => $dataService->sectionDefinitions(),
            'cycleState' => $cycleStateService->resolveState($loanRequestRecord),
            'documentChecklist' => $documentWorkflowService->serializeChecklist(
                $loanRequestRecord,
            ),
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

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->canViewPdf($loanRequestRecord)) {
            abort(404);
        }

        DocumentAccessLog::record(
            (int) $request->user()?->user_id,
            (int) $loanRequestRecord->id,
            'loan_request_pdf',
            $request->boolean('download') ? DocumentAccessLog::ACTION_DOWNLOAD : DocumentAccessLog::ACTION_VIEW,
        );

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

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->canViewPdf($loanRequestRecord)) {
            abort(404);
        }

        return $pdfService->renderPrintView($loanRequestRecord);
    }

    public function approvedDocuments(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->packageZip($loanRequestRecord);
    }

    public function approvedDocumentsDispatch(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentPackageJobService $packageJobService,
    ): JsonResponse {
        $loanRequestRecord = $this->approvedDocumentsPackageJobLoanRequest($loanRequest);
        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        $packageJob = $packageJobService->dispatch($loanRequestRecord, $actor);

        return response()->json([
            'id' => $packageJob->id,
            'status' => $packageJob->status->value,
        ]);
    }

    public function approvedDocumentsStatus(
        int $loanRequest,
        int $packageJob,
        ApprovedLoanDocumentPackageJobService $packageJobService,
    ): JsonResponse {
        $loanRequestRecord = $this->approvedDocumentsPackageJobLoanRequest($loanRequest);
        $job = $packageJobService->findForLoanRequest($loanRequestRecord, $packageJob);

        if ($job === null) {
            abort(404);
        }

        return response()->json([
            'id' => $job->id,
            'status' => $job->status->value,
            'error_message' => $job->status === LoanDocumentPackageJobStatus::Failed
                ? $job->error_message
                : null,
        ]);
    }

    public function approvedDocumentsDownload(
        int $loanRequest,
        int $packageJob,
        ApprovedLoanDocumentPackageJobService $packageJobService,
    ): HttpResponse {
        $loanRequestRecord = $this->approvedDocumentsPackageJobLoanRequest($loanRequest);
        $job = $packageJobService->findForLoanRequest($loanRequestRecord, $packageJob);

        if ($job === null) {
            abort(404);
        }

        return $packageJobService->downloadResponse($job);
    }

    private function approvedDocumentsPackageJobLoanRequest(int $loanRequest): LoanRequest
    {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $loanRequestRecord;
    }

    public function applicationFormDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->canViewPdf($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->applicationForm($loanRequestRecord);
    }

    public function grepalifeDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->grepalife($loanRequestRecord);
    }

    public function loanSecurityAgreementDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->loanSecurityAgreement($loanRequestRecord);
    }

    public function planOfPaymentDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->planOfPayment($loanRequestRecord);
    }

    public function loanInformationDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasPreAcceptanceDisclosureStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->loanInformation($loanRequestRecord);
    }

    public function disclosureStatementDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasPreAcceptanceDisclosureStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->disclosureStatement($loanRequestRecord);
    }

    public function promissoryNoteDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->promissoryNote($loanRequestRecord);
    }

    public function undertakingBarangayDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->undertakingBarangay($loanRequestRecord);
    }

    public function affidavitUndertakingDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->affidavitUndertaking($loanRequestRecord);
    }

    public function generaliDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->generali($loanRequestRecord);
    }

    public function authorityToDeductDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->authorityToDeduct($loanRequestRecord);
    }

    public function depedSalaryDeductionWaiverDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->depedSalaryDeductionWaiver($loanRequestRecord);
    }

    public function pensionDeductionWaiverDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->pensionDeductionWaiver($loanRequestRecord);
    }

    public function generaliApplicationFormDocument(
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        if (! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->generaliApplicationForm($loanRequestRecord);
    }

    public function generatedDocument(
        Request $request,
        int $loanRequest,
        LoanRequestDocumentKey $documentKey,
        LoanRequestDocumentStorage $documentStorage,
    ): HttpResponse {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        Gate::authorize('view', $loanRequestRecord);
        $this->authorizeAdminDocumentAccess($loanRequestRecord);

        $document = $loanRequestRecord->documents()
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
        $isWorkbook = $this->isWorkbookDocument($documentKey);
        $downloadName = basename(
            $document->generated_filename ?: DocumentFilename::build(
                $loanRequestRecord->reference,
                $documentKey->value,
                $isWorkbook ? 'xlsx' : 'pdf',
            ),
        );

        if (
            $isWorkbook
            && ($request->boolean('preview') || $request->boolean('print'))
        ) {
            return $this->renderWorkbookDocument(
                $absolutePath,
                $downloadName,
                $request->boolean('print'),
            );
        }

        $action = $request->boolean('download')
            ? DocumentAccessLog::ACTION_DOWNLOAD
            : DocumentAccessLog::ACTION_VIEW;

        DocumentAccessLog::record(
            (int) $request->user()?->user_id,
            (int) $loanRequestRecord->id,
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

    private function findLoanRequest(int $loanRequestId): ?LoanRequest
    {
        return LoanRequest::query()
            ->whereKey($loanRequestId)
            ->first();
    }

    private function authorizeAdminDocumentAccess(LoanRequest $loanRequest): void
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

    /**
     * @return list<string>
     */
    private function resolveWorkflowPermissions(?AppUser $actor): array
    {
        if (! $actor instanceof AppUser) {
            return [];
        }

        return $actor->roles
            ->flatMap(static fn ($role) => $role->permissions->pluck('name'))
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
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
