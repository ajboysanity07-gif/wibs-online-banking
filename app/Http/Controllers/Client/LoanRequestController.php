<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\LoanRequestDraftRequest;
use App\Http\Requests\Client\LoanRequestStoreRequest;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestPdfService;
use App\Services\LoanRequests\LoanRequestService;
use App\Support\LocationComposer;
use Illuminate\Contracts\View\View;
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
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $service->saveDraft($user, $request->validated());

        return redirect()->route('client.loan-requests.create');
    }

    public function show(
        Request $request,
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

        if ($this->isDraft($loanRequestRecord)) {
            return redirect()->route('client.loan-requests.create');
        }

        $loanRequestRecord->loadMissing('people', 'reviewedBy', 'cancelledBy');

        $payload = $this->sanitizePayload([
            'loanRequest' => [
                'id' => $loanRequestRecord->id,
                'reference' => $loanRequestRecord->reference,
                'status' => $this->normalizeStatus($loanRequestRecord),
                'typecode' => $loanRequestRecord->typecode,
                'loan_type_label_snapshot' => $loanRequestRecord->loan_type_label_snapshot,
                'requested_amount' => $loanRequestRecord->requested_amount,
                'requested_term' => $loanRequestRecord->requested_term,
                'loan_purpose' => $loanRequestRecord->loan_purpose,
                'availment_status' => $loanRequestRecord->availment_status,
                'submitted_at' => $loanRequestRecord->submitted_at?->toDateTimeString(),
                'reviewed_by' => $loanRequestRecord->reviewedBy
                    ? [
                        'user_id' => $loanRequestRecord->reviewedBy->user_id,
                        'name' => $loanRequestRecord->reviewedBy->name,
                    ]
                    : null,
                'reviewed_at' => $loanRequestRecord->reviewed_at?->toDateTimeString(),
                'approved_amount' => $loanRequestRecord->approved_amount,
                'approved_term' => $loanRequestRecord->approved_term,
                'decision_notes' => $loanRequestRecord->decision_notes,
                'cancelled_by' => $loanRequestRecord->cancelledBy
                    ? [
                        'user_id' => $loanRequestRecord->cancelledBy->user_id,
                        'name' => $loanRequestRecord->cancelledBy->name,
                    ]
                    : null,
                'cancelled_at' => $loanRequestRecord->cancelled_at?->toDateTimeString(),
                'cancellation_reason' => $loanRequestRecord->cancellation_reason,
                'acctno' => $loanRequestRecord->acctno,
            ],
            'applicant' => $this->serializePerson(
                $loanRequestRecord,
                LoanRequestPersonRole::Applicant,
            ),
            'coMakerOne' => $this->serializePerson(
                $loanRequestRecord,
                LoanRequestPersonRole::CoMakerOne,
            ),
            'coMakerTwo' => $this->serializePerson(
                $loanRequestRecord,
                LoanRequestPersonRole::CoMakerTwo,
            ),
        ]);

        return Inertia::render('client/loan-request-show', $payload);
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
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if ($status === LoanRequestStatus::Submitted->value) {
            return LoanRequestStatus::UnderReview->value;
        }

        return $status;
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
        $address2 = $this->normalizeOptionalString($person['address2'] ?? null);
        $address3 = $this->normalizeOptionalString($person['address3'] ?? null);
        $legacyAddress = $this->normalizeOptionalString($person['address'] ?? null);

        if ($address1 === null && $address2 === null && $address3 === null && $legacyAddress !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacyAddress);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $address = LocationComposer::compose($address1, $address2, $address3);
        $address = $address !== '' ? $address : $legacyAddress;

        $employerAddress1 = $this->normalizeOptionalString(
            $person['employer_business_address1'] ?? null,
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
        );
        $employerBusinessAddress = $employerBusinessAddress !== ''
            ? $employerBusinessAddress
            : $legacyEmployerAddress;

        return array_merge($person, [
            'birthplace' => $birthplace,
            'birthplace_city' => $birthplaceCity,
            'birthplace_province' => $birthplaceProvince,
            'address' => $address,
            'address1' => $address1,
            'address2' => $address2,
            'address3' => $address3,
            'employer_business_address' => $employerBusinessAddress,
            'employer_business_address1' => $employerAddress1,
            'employer_business_address2' => $employerAddress2,
            'employer_business_address3' => $employerAddress3,
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
}
