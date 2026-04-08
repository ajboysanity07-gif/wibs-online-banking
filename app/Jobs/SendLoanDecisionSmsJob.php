<?php

namespace App\Jobs;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\OrganizationSettingsService;
use App\Services\Sms\SemaphoreSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendLoanDecisionSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $loanRequestId) {}

    /**
     * Execute the job.
     */
    public function handle(
        SemaphoreSmsService $smsService,
        OrganizationSettingsService $organizationSettings,
    ): void {
        $loanRequest = LoanRequest::query()
            ->with('user')
            ->find($this->loanRequestId);

        if ($loanRequest === null) {
            Log::warning('Loan request not found for SMS notification.', [
                'loan_request_id' => $this->loanRequestId,
            ]);

            return;
        }

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if (! in_array($status, [
            LoanRequestStatus::Approved->value,
            LoanRequestStatus::Declined->value,
        ], true)) {
            Log::info('Loan request SMS skipped for non-decision status.', [
                'loan_request_id' => $loanRequest->id,
                'status' => $status,
            ]);

            return;
        }

        $phoneNumber = $loanRequest->user?->phoneno;

        if (! is_string($phoneNumber) || trim($phoneNumber) === '') {
            Log::info('Loan request SMS skipped due to missing phone number.', [
                'loan_request_id' => $loanRequest->id,
            ]);

            return;
        }

        $message = $this->buildMessage(
            $loanRequest,
            $status,
            $organizationSettings->branding(),
        );

        if ($message === null) {
            return;
        }

        $sent = $smsService->send($phoneNumber, $message);

        if (! $sent) {
            Log::warning('Loan request SMS failed to send.', [
                'loan_request_id' => $loanRequest->id,
            ]);
        }
    }

    /**
     * @param  array{companyName: string, portalLabel: string}  $branding
     */
    private function buildMessage(
        LoanRequest $loanRequest,
        string $status,
        array $branding,
    ): ?string {
        $prefix = $this->resolveMessagePrefix($branding);
        $officeName = $this->resolveOfficeName($branding);
        $reference = $loanRequest->reference;

        if ($status === LoanRequestStatus::Approved->value) {
            $approvedAmount = $loanRequest->approved_amount;
            $approvedTerm = $loanRequest->approved_term;
            $amountText = $this->formatCurrency($approvedAmount);
            $termText = $approvedTerm !== null ? (int) $approvedTerm : 0;

            return sprintf(
                '%s: Your loan request (%s) has been APPROVED for %s payable over %s months. Please visit the %s office to finalize your loan.',
                $prefix,
                $reference,
                $amountText,
                $termText,
                $officeName,
            );
        }

        if ($status === LoanRequestStatus::Declined->value) {
            return sprintf(
                '%s: Your loan request (%s) has been DECLINED. For questions or clarification, please contact the %s office.',
                $prefix,
                $reference,
                $officeName,
            );
        }

        return null;
    }

    /**
     * @param  array{companyName: string, portalLabel: string}  $branding
     */
    private function resolveMessagePrefix(array $branding): string
    {
        $companyName = $this->normalizeText($branding['companyName'] ?? null);
        $portalLabel = $this->normalizeText($branding['portalLabel'] ?? null);

        if ($portalLabel !== null && $companyName !== null) {
            if (Str::contains(Str::lower($portalLabel), Str::lower($companyName))) {
                return $portalLabel;
            }

            return trim(sprintf('%s %s', $companyName, $portalLabel));
        }

        return $portalLabel ?? $companyName ?? '';
    }

    /**
     * @param  array{companyName: string, portalLabel: string}  $branding
     */
    private function resolveOfficeName(array $branding): string
    {
        $companyName = $this->normalizeText($branding['companyName'] ?? null);

        if ($companyName !== null) {
            return $companyName;
        }

        $portalLabel = $this->normalizeText($branding['portalLabel'] ?? null);

        return $portalLabel ?? 'coop';
    }

    private function formatCurrency(mixed $value): string
    {
        $numericValue = is_numeric($value) ? (float) $value : 0.0;

        return sprintf('Php. %s', number_format($numericValue, 2));
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
