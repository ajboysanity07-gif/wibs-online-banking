<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\LoanRequestCancelRequest;
use App\Http\Requests\Client\LoanRequestDraftRequest;
use App\Http\Requests\Client\LoanRequestResolveActionRequest;
use App\Http\Requests\Client\LoanRequestStoreRequest;
use App\Http\Requests\Client\SaveDraftRequest;
use App\LoanCivilStatus;
use App\LoanDocumentPackageJobStatus;
use App\LoanPaydayOption;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Services\LoanRequests\ApprovedLoanDocumentPackageJobService;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDataService;
use App\Services\LoanRequests\LoanRequestDecisionService;
use App\Services\LoanRequests\LoanRequestPayloadSerializer;
use App\Services\LoanRequests\LoanRequestPdfService;
use App\Services\LoanRequests\LoanRequestProcessingService;
use App\Services\LoanRequests\LoanRequestService;
use App\Support\LocationComposer;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoanRequestController extends Controller
{
    public function create(
        Request $request,
        LoanRequestService $service,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('userProfile', 'adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $payload = $this->sanitizePayload($service->getFormData($user));

        return Inertia::render('client/loan-request', $payload);
    }

    public function store(
        LoanRequestStoreRequest $request,
        LoanRequestService $service,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $loanRequest = $service->submit($user, $request->validated());

        return redirect()->route('client.loan-requests.show', $loanRequest);
    }

    public function draft(
        LoanRequestDraftRequest $request,
        LoanRequestService $service,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $loanRequest = $service->saveDraft($user, $request->validated());

        if ($request->expectsJson()) {
            return response()->json($service->serializeLoanRequest($loanRequest));
        }

        return redirect()->route('client.loan-requests.create');
    }

    public function index(
        Request $request,
        LoanRequestService $service,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $loanRequestsPayload = null;
        $loanRequestsError = null;

        try {
            $loanRequestsPayload = [
                'items' => $service->getMemberRequestSummaries(
                    $user,
                    10,
                ),
            ];
        } catch (\Throwable $exception) {
            report($exception);
            $loanRequestsError = 'Unable to load loan requests.';
        }

        $payload = $this->sanitizePayload([
            'loanRequests' => $loanRequestsPayload,
            'loanRequestsError' => $loanRequestsError,
        ]);

        return Inertia::render('client/loan-requests', $payload);
    }

    public function show(
        Request $request,
        LoanRequestPayloadSerializer $serializer,
        LoanRequestDataService $dataService,
        int $loanRequest,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            'show',
        );

        if ($loanRequestRecord === null) {
            abort(404);
        }

        if ($this->isEditableStatus($loanRequestRecord)) {
            return redirect()->route('client.loan-requests.create');
        }

        $loanRequestRecord->loadMissing(
            'people',
            'assignedOfficer.adminProfile',
            'reviewedBy.adminProfile',
            'rejectedBy.adminProfile',
            'approvedBy.adminProfile',
            'declinedBy.adminProfile',
            'cancelledBy',
            'correctedFrom',
            'correctedRequests',
        );
        $correctedRequest = $this->resolveCorrectedRequest($loanRequestRecord);
        $detail = $serializer->serializeDetail($loanRequestRecord);
        $detail['loanRequest']['status'] = $this->normalizeStatus($loanRequestRecord);
        $detail['loanRequest']['corrected_request_status'] = $correctedRequest !== null
            ? $this->normalizeStatus($correctedRequest)
            : null;

        $payload = $this->sanitizePayload([
            ...$detail,
            'auditTrail' => $serializer->serializeMemberAuditTrail(
                $loanRequestRecord,
            ),
            'dataSections' => $dataService->serializeSections($loanRequestRecord),
            'dataSectionDefinitions' => $dataService->sectionDefinitions(),
            'memberAction' => [
                'type' => $loanRequestRecord->member_action_type,
                'message' => $loanRequestRecord->member_action_message,
                'fields' => $loanRequestRecord->member_action_fields_json,
                'requested_at' => $loanRequestRecord->member_action_requested_at?->toDateTimeString(),
                'resolved_at' => $loanRequestRecord->member_action_resolved_at?->toDateTimeString(),
            ],
            'hasOpenCorrectionReport' => $loanRequestRecord
                ->correctionReports()
                ->where('status', LoanRequestCorrectionReport::STATUS_OPEN)
                ->exists(),
        ]);

        return Inertia::render('client/loan-request-show', $payload);
    }

    public function cancel(
        LoanRequestCancelRequest $request,
        int $loanRequest,
        LoanRequestDecisionService $service,
        LoanRequestPayloadSerializer $serializer,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return response()->json([
                'message' => 'Only members can cancel loan requests from this page.',
            ], 403);
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            'cancel',
        );

        if ($loanRequestRecord === null) {
            abort(404);
        }

        $payload = $request->validated();
        $updated = $service->cancelByMember(
            $loanRequestRecord,
            $user,
            $payload['cancellation_reason'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest($updated),
                'auditTrail' => $serializer->serializeMemberAuditTrail($updated),
            ],
        ]);
    }

    public function resolveAction(
        LoanRequestResolveActionRequest $request,
        int $loanRequest,
        LoanRequestProcessingService $processingService,
        LoanRequestPayloadSerializer $serializer,
        LoanRequestDataService $dataService,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            return redirect()->route('login');
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            'resolve-action',
        );

        if ($loanRequestRecord === null) {
            abort(404);
        }

        $status = $loanRequestRecord->status instanceof LoanRequestStatus
            ? $loanRequestRecord->status->value
            : (string) $loanRequestRecord->status;

        $updated = match ($status) {
            LoanRequestStatus::AwaitingMemberInformation->value => $processingService->resolveMemberInformation(
                $loanRequestRecord,
                $user,
                $request->validated(),
            ),
            LoanRequestStatus::AwaitingMemberAcceptance->value => $processingService->respondToTerms(
                $loanRequestRecord,
                $user,
                $request->validated('decision') === 'accept',
                $request->validated('reason'),
            ),
            default => throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                403,
                'This loan request does not currently have a pending member action.',
            ),
        };

        return response()->json([
            'ok' => true,
            'data' => [
                'loanRequest' => $serializer->serializeLoanRequest($updated),
                'auditTrail' => $serializer->serializeMemberAuditTrail($updated),
                'dataSections' => $dataService->serializeSections($updated),
                'dataSectionDefinitions' => $dataService->sectionDefinitions(),
            ],
        ]);
    }

    public function saveDraft(
        SaveDraftRequest $request,
        LoanRequest $loanRequest,
        LoanRequestService $service,
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            abort(403);
        }

        $service->saveDraft($user, $request->validated());

        return response()->json(null, HttpResponse::HTTP_NO_CONTENT);
    }

    public function createCorrectedCopy(
        Request $request,
        int $loanRequest,
        LoanRequestService $service,
    ): RedirectResponse {
        abort(403, 'Members can no longer create corrected requests.');
    }

    public function pdf(
        Request $request,
        int $loanRequest,
        LoanRequestPdfService $pdfService,
    ): HttpResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            'pdf',
        );

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
        Request $request,
        int $loanRequest,
        LoanRequestPdfService $pdfService,
    ): View|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            'print',
        );

        if ($loanRequestRecord === null) {
            abort(404);
        }

        if (! $this->canViewPdf($loanRequestRecord)) {
            abort(404);
        }

        return $pdfService->renderPrintView($loanRequestRecord);
    }

    public function approvedDocuments(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            'approved-documents',
        );

        if ($loanRequestRecord === null || ! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->packageZip($loanRequestRecord);
    }

    public function approvedDocumentsDispatch(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentPackageJobService $packageJobService,
    ): JsonResponse {
        $user = $this->authorizeApprovedDocumentsPackageJob($request);
        $loanRequestRecord = $this->approvedDocumentsPackageJobLoanRequest($user, $loanRequest);

        $packageJob = $packageJobService->dispatch($loanRequestRecord, $user);

        return response()->json([
            'id' => $packageJob->id,
            'status' => $packageJob->status->value,
        ]);
    }

    public function approvedDocumentsStatus(
        Request $request,
        int $loanRequest,
        int $packageJob,
        ApprovedLoanDocumentPackageJobService $packageJobService,
    ): JsonResponse {
        $user = $this->authorizeApprovedDocumentsPackageJob($request);
        $loanRequestRecord = $this->approvedDocumentsPackageJobLoanRequest($user, $loanRequest);
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
        Request $request,
        int $loanRequest,
        int $packageJob,
        ApprovedLoanDocumentPackageJobService $packageJobService,
    ): HttpResponse {
        $user = $this->authorizeApprovedDocumentsPackageJob($request);
        $loanRequestRecord = $this->approvedDocumentsPackageJobLoanRequest($user, $loanRequest);
        $job = $packageJobService->findForLoanRequest($loanRequestRecord, $packageJob);

        if ($job === null) {
            abort(404);
        }

        return $packageJobService->downloadResponse($job);
    }

    private function authorizeApprovedDocumentsPackageJob(Request $request): AppUser
    {
        $user = $request->user();

        abort_unless($user instanceof AppUser, 403);

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            abort(403);
        }

        return $user;
    }

    private function approvedDocumentsPackageJobLoanRequest(
        AppUser $user,
        int $loanRequestId,
    ): LoanRequest {
        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequestId,
            'approved-documents-package-job',
        );

        if ($loanRequestRecord === null || ! $this->hasApprovedDocumentsStatus($loanRequestRecord)) {
            abort(404);
        }

        return $loanRequestRecord;
    }

    public function applicationFormDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            'application-form-document',
        );

        if ($loanRequestRecord === null || ! $this->canViewPdf($loanRequestRecord)) {
            abort(404);
        }

        return $documentService->applicationForm($loanRequestRecord);
    }

    public function grepalifeDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'grepalife-document',
        );

        return $documentService->grepalife($loanRequestRecord);
    }

    public function loanSecurityAgreementDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'loan-security-agreement-document',
        );

        return $documentService->loanSecurityAgreement($loanRequestRecord);
    }

    public function planOfPaymentDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'plan-of-payment-document',
        );

        return $documentService->planOfPayment($loanRequestRecord);
    }

    public function loanInformationDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'loan-information-document',
        );

        return $documentService->loanInformation($loanRequestRecord);
    }

    public function disclosureStatementDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'disclosure-statement-document',
        );

        return $documentService->disclosureStatement($loanRequestRecord);
    }

    public function promissoryNoteDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'promissory-note-document',
        );

        return $documentService->promissoryNote($loanRequestRecord);
    }

    public function undertakingBarangayDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'undertaking-barangay-document',
        );

        return $documentService->undertakingBarangay($loanRequestRecord);
    }

    public function affidavitUndertakingDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'affidavit-undertaking-document',
        );

        return $documentService->affidavitUndertaking($loanRequestRecord);
    }

    public function generaliDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'generali-document',
        );

        return $documentService->generali($loanRequestRecord);
    }

    public function authorityToDeductDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'authority-to-deduct-document',
        );

        return $documentService->authorityToDeduct($loanRequestRecord);
    }

    public function depedSalaryDeductionWaiverDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'deped-salary-deduction-waiver-document',
        );

        return $documentService->depedSalaryDeductionWaiver($loanRequestRecord);
    }

    public function pensionDeductionWaiverDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'pension-deduction-waiver-document',
        );

        return $documentService->pensionDeductionWaiver($loanRequestRecord);
    }

    public function authorizationDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'authorization-document',
        );

        return $documentService->authorization($loanRequestRecord);
    }

    public function generaliApplicationFormDocument(
        Request $request,
        int $loanRequest,
        ApprovedLoanDocumentService $documentService,
    ): HttpResponse {
        $loanRequestRecord = $this->resolveApprovedDocumentLoanRequest(
            $request,
            $loanRequest,
            'generali-application-form-document',
        );

        return $documentService->generaliApplicationForm($loanRequestRecord);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): array {
        $person = $loanRequest->people
            ->first(fn ($item) => $item->role === $role);

        if ($person === null) {
            return [];
        }

        return $this->hydrateStructuredPersonFields($person->toArray());
    }

    private function findLoanRequestForUser(
        AppUser $user,
        int $loanRequestId,
        string $context,
    ): ?LoanRequest {
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
            'context' => $context,
            'loan_request_id' => $loanRequestId,
            'auth_user_id' => $user->user_id,
            'auth_acctno' => $user->acctno,
            'record_user_id' => $existing?->user_id,
            'record_acctno' => $existing?->acctno,
            'record_status' => $status,
        ]);

        return null;
    }

    private function normalizeStatus(LoanRequest $loanRequest): string
    {
        return LoanRequestStatus::memberVisibleValue($loanRequest->status)
            ?? (string) $loanRequest->status;
    }

    private function isEditableStatus(LoanRequest $loanRequest): bool
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

    /**
     * Truth-in-lending–style disclosures must reach the member before they accept the
     * loan's terms, not only afterward -- so these two contexts unlock one workflow step
     * earlier than the rest of the approved-document package.
     *
     * @var list<string>
     */
    private const PRE_ACCEPTANCE_DISCLOSURE_CONTEXTS = [
        'loan-information-document',
        'disclosure-statement-document',
    ];

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

    private function resolveApprovedDocumentLoanRequest(
        Request $request,
        int $loanRequest,
        string $context,
    ): LoanRequest {
        $user = $request->user();

        if ($user === null) {
            abort(404);
        }

        $user->loadMissing('adminProfile');

        if ($user->isAdminOnly()) {
            abort(404);
        }

        $loanRequestRecord = $this->findLoanRequestForUser(
            $user,
            $loanRequest,
            $context,
        );

        $hasRequiredStatus = in_array($context, self::PRE_ACCEPTANCE_DISCLOSURE_CONTEXTS, true)
            ? $loanRequestRecord !== null && $this->hasPreAcceptanceDisclosureStatus($loanRequestRecord)
            : $loanRequestRecord !== null && $this->hasApprovedDocumentsStatus($loanRequestRecord);

        if (! $hasRequiredStatus) {
            abort(404);
        }

        return $loanRequestRecord;
    }

    private function resolveCorrectedRequest(
        LoanRequest $loanRequest,
    ): ?LoanRequest {
        if (! $loanRequest->relationLoaded('correctedRequests')) {
            return null;
        }

        /** @var LoanRequest|null $correctedRequest */
        $correctedRequest = $loanRequest->correctedRequests
            ->sortByDesc('id')
            ->first();

        return $correctedRequest;
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

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function hydrateStructuredPersonFields(array $person): array
    {
        $birthdate = $this->normalizeDateForInput($person['birthdate'] ?? null);
        $housingStatus = $this->normalizeHousingStatusValue(
            $person['housing_status'] ?? null,
        );
        $civilStatus = $this->normalizeCivilStatusValue(
            $person['civil_status'] ?? null,
        );
        $payday = $this->normalizePaydayValue($person['payday'] ?? null);

        $birthplaceCity = $this->normalizeOptionalString(
            $person['birthplace_city'] ?? null,
        );
        $birthplaceProvince = $this->normalizeOptionalString(
            $person['birthplace_province'] ?? null,
        );
        $legacyBirthplace = $this->normalizeOptionalString(
            $person['birthplace'] ?? null,
        );

        if ($birthplaceCity === null && $birthplaceProvince === null && $legacyBirthplace !== null) {
            $parsed = LocationComposer::parseLegacyBirthplace($legacyBirthplace);
            $birthplaceCity = $parsed['city'];
            $birthplaceProvince = $parsed['province'];
        }

        $birthplace = LocationComposer::composeBirthplace(
            $birthplaceCity,
            $birthplaceProvince,
        );
        $birthplace = $birthplace !== '' ? $birthplace : $legacyBirthplace;

        $address1 = $this->normalizeOptionalString($person['address1'] ?? null);
        $addressBarangay = $this->normalizeOptionalString($person['address_barangay'] ?? null);
        $address2 = $this->normalizeOptionalString($person['address2'] ?? null);
        $address3 = $this->normalizeOptionalString($person['address3'] ?? null);
        $legacyAddress = $this->normalizeOptionalString($person['address'] ?? null);

        if ($address1 === null && $address2 === null && $address3 === null && $legacyAddress !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacyAddress);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $address = LocationComposer::compose($address1, $address2, $address3, $addressBarangay);
        $address = $address !== '' ? $address : $legacyAddress;

        $employerAddress1 = $this->normalizeOptionalString(
            $person['employer_business_address1'] ?? null,
        );
        $employerAddressBarangay = $this->normalizeOptionalString(
            $person['employer_business_address_barangay'] ?? null,
        );
        $employerAddress2 = $this->normalizeOptionalString(
            $person['employer_business_address2'] ?? null,
        );
        $employerAddress3 = $this->normalizeOptionalString(
            $person['employer_business_address3'] ?? null,
        );
        $legacyEmployerAddress = $this->normalizeOptionalString(
            $person['employer_business_address'] ?? null,
        );

        if (
            $employerAddress1 === null
            && $employerAddress2 === null
            && $employerAddress3 === null
            && $legacyEmployerAddress !== null
        ) {
            $parsed = LocationComposer::parseLegacyAddress(
                $legacyEmployerAddress,
            );
            $employerAddress1 = $parsed['address1'];
            $employerAddress2 = $parsed['address2'];
            $employerAddress3 = $parsed['address3'];
        }

        $employerBusinessAddress = LocationComposer::compose(
            $employerAddress1,
            $employerAddress2,
            $employerAddress3,
            $employerAddressBarangay,
        );
        $employerBusinessAddress = $employerBusinessAddress !== ''
            ? $employerBusinessAddress
            : $legacyEmployerAddress;

        return array_merge($person, [
            'birthdate' => $birthdate,
            'spouse_birthdate' => $this->normalizeDateForInput($person['spouse_birthdate'] ?? null),
            'birthplace' => $birthplace,
            'birthplace_city' => $birthplaceCity,
            'birthplace_province' => $birthplaceProvince,
            'address' => $address,
            'address1' => $address1,
            'address_barangay' => $addressBarangay,
            'address2' => $address2,
            'address3' => $address3,
            'employer_business_address' => $employerBusinessAddress,
            'employer_business_address1' => $employerAddress1,
            'employer_business_address_barangay' => $employerAddressBarangay,
            'employer_business_address2' => $employerAddress2,
            'employer_business_address3' => $employerAddress3,
            'housing_status' => $housingStatus,
            'civil_status' => $civilStatus,
            'payday' => $payday,
        ]);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeDateForInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $candidate = substr($trimmed, 0, 10);

        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $candidate) === 1
            ? $candidate
            : null;
    }

    private function normalizeHousingStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        if (in_array($upper, ['OWNED', 'OWN', 'OWNER'], true)) {
            return 'OWNED';
        }

        if (in_array($upper, ['RENT', 'RENTAL', 'RENTED', 'RENTING'], true)) {
            return 'RENT';
        }

        return null;
    }

    private function normalizeCivilStatusValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $upper = strtoupper($trimmed);

        $resolved = match ($upper) {
            'SINGLE' => 'Single',
            'MARRIED' => 'Married',
            'SEPARATED' => 'Separated',
            'WIDOWED' => 'Widowed',
            'ANNULLED' => null,
            default => $trimmed,
        };

        if ($resolved === null) {
            return null;
        }

        return in_array($resolved, LoanCivilStatus::values(), true)
            ? $resolved
            : null;
    }

    private function normalizePaydayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array($trimmed, LoanPaydayOption::values(), true)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);
        $compact = preg_replace('/[^0-9A-Z]/', '', $upper) ?? '';

        // Backward-compat: map legacy values to the new canonical set.
        return match (true) {
            $upper === 'WEEKLY' => 'Weekly',
            $upper === 'MONTHLY' => 'Monthly',
            $compact === 'BIWEEKLY' => 'Weekly',
            $compact === '15' => 'Quincenal',
            $compact === '30' => 'Quincenal',
            str_contains($upper, '15') && str_contains($upper, '30') => 'Quincenal',
            $compact === 'SEMIMONTHLY' => 'Quincenal',
            str_contains($upper, 'LUMP') => 'Due date',
            default => null,
        };
    }
}
