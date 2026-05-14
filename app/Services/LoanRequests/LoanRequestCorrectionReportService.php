<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use App\Notifications\LoanRequestCorrectionReportedNotification;
use App\Services\Notifications\NotificationRecipientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LoanRequestCorrectionReportService
{
    public function __construct(
        private NotificationRecipientService $notificationRecipients,
    ) {}

    /**
     * @param  array{
     *     issue_description: string,
     *     correct_information: string,
     *     supporting_note?: string|null
     * }  $payload
     */
    public function createForMember(
        LoanRequest $loanRequest,
        AppUser $member,
        array $payload,
    ): LoanRequestCorrectionReport {
        $report = DB::transaction(function () use ($loanRequest, $member, $payload): LoanRequestCorrectionReport {
            $lockedLoanRequest = LoanRequest::query()
                ->whereKey($loanRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureMemberCanReport($lockedLoanRequest, $member);

            $existingOpenReport = LoanRequestCorrectionReport::query()
                ->where('loan_request_id', $lockedLoanRequest->id)
                ->where('user_id', $member->user_id)
                ->where('status', LoanRequestCorrectionReport::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($existingOpenReport !== null) {
                throw ValidationException::withMessages([
                    'report' => 'You already have an open correction report for this request.',
                ]);
            }

            return LoanRequestCorrectionReport::query()->create([
                'loan_request_id' => $lockedLoanRequest->id,
                'user_id' => $member->user_id,
                'issue_description' => trim($payload['issue_description']),
                'correct_information' => trim($payload['correct_information']),
                'supporting_note' => $this->normalizeNullableText(
                    $payload['supporting_note'] ?? null,
                ),
                'status' => LoanRequestCorrectionReport::STATUS_OPEN,
            ]);
        });

        $report->loadMissing('loanRequest', 'user');
        $this->notifyAdminsOfNewReport($report);

        return $report;
    }

    public function dismiss(
        LoanRequest $loanRequest,
        LoanRequestCorrectionReport $report,
        AppUser $actor,
        ?string $adminNotes = null,
    ): LoanRequestCorrectionReport {
        $dismissedReport = DB::transaction(function () use ($loanRequest, $report, $actor, $adminNotes): LoanRequestCorrectionReport {
            $lockedReport = LoanRequestCorrectionReport::query()
                ->whereKey($report->id)
                ->where('loan_request_id', $loanRequest->id)
                ->lockForUpdate()
                ->first();

            if ($lockedReport === null) {
                throw ValidationException::withMessages([
                    'report' => 'The correction report does not belong to this loan request.',
                ]);
            }

            if ($lockedReport->status !== LoanRequestCorrectionReport::STATUS_OPEN) {
                throw ValidationException::withMessages([
                    'status' => 'Only open correction reports can be dismissed.',
                ]);
            }

            $lockedReport->fill([
                'status' => LoanRequestCorrectionReport::STATUS_DISMISSED,
                'dismissed_by' => $actor->user_id,
                'dismissed_at' => now(),
                'admin_notes' => $this->normalizeNullableText($adminNotes),
            ]);
            $lockedReport->save();

            return $lockedReport;
        });

        return $dismissedReport->loadMissing('user', 'dismissedBy', 'resolvedBy');
    }

    public function latestOpenReport(
        LoanRequest $loanRequest,
    ): ?LoanRequestCorrectionReport {
        return LoanRequestCorrectionReport::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('status', LoanRequestCorrectionReport::STATUS_OPEN)
            ->orderByDesc('id')
            ->first();
    }

    public function resolveOpenReports(
        LoanRequest $loanRequest,
        AppUser $actor,
        ?string $adminNotes = null,
    ): void {
        LoanRequestCorrectionReport::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('status', LoanRequestCorrectionReport::STATUS_OPEN)
            ->update([
                'status' => LoanRequestCorrectionReport::STATUS_RESOLVED,
                'resolved_by' => $actor->user_id,
                'resolved_at' => now(),
                'admin_notes' => $this->normalizeNullableText($adminNotes),
            ]);
    }

    private function ensureMemberCanReport(
        LoanRequest $loanRequest,
        AppUser $member,
    ): void {
        if ((int) $loanRequest->user_id !== (int) $member->user_id) {
            throw ValidationException::withMessages([
                'loan_request' => 'You can only report your own loan request.',
            ]);
        }

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if ($status !== LoanRequestStatus::Approved->value) {
            throw ValidationException::withMessages([
                'status' => 'Only approved loan requests can be reported for correction.',
            ]);
        }
    }

    private function notifyAdminsOfNewReport(
        LoanRequestCorrectionReport $report,
    ): void {
        $admins = $this->notificationRecipients->adminsAndSuperadmins();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send(
            $admins,
            new LoanRequestCorrectionReportedNotification($report),
        );
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }
}
