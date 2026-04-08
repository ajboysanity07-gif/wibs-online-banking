<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestPdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoanRequestController extends Controller
{
    public function show(int $loanRequest): Response
    {
        $loanRequestRecord = $this->findLoanRequest($loanRequest);

        if ($loanRequestRecord === null) {
            abort(404);
        }

        if ($this->isDraft($loanRequestRecord)) {
            abort(404);
        }

        $loanRequestRecord->loadMissing('people', 'reviewedBy');

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

        return $person->toArray();
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
