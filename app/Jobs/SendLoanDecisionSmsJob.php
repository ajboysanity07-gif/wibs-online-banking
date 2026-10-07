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

        $branding = $organizationSettings->branding();
        $enabledKey = $status === LoanRequestStatus::Approved->value ? 'approved' : 'declined';

        if (! ($branding['loanSmsEnabled'][$enabledKey] ?? true)) {
            Log::info('Loan request SMS skipped because the message is switched off.', [
                'loan_request_id' => $loanRequest->id,
            ]);

            return;
        }

        $wait = $this->secondsUntilWindowOpens(
            (string) ($branding['smsSendWindow'] ?? 'any'),
            (string) ($branding['timezone'] ?? 'Asia/Manila'),
        );

        // Sync dispatch has no queue job to release, so it sends immediately.
        if ($wait > 0 && $this->job !== null) {
            $this->release($wait);

            return;
        }

        $templates = $organizationSettings->loanSmsTemplates();
        $message = $this->buildMessage(
            $loanRequest,
            $status,
            $templates,
            $branding,
            $organizationSettings,
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
     * Seconds until the configured send window opens; 0 when open or unrestricted.
     */
    private function secondsUntilWindowOpens(string $window, string $timezone): int
    {
        [$startHour, $endHour, $days] = match ($window) {
            '7am-8pm-daily' => [7, 20, [0, 1, 2, 3, 4, 5, 6]],
            '8am-6pm-mon-sat' => [8, 18, [1, 2, 3, 4, 5, 6]],
            default => [null, null, null],
        };

        if ($startHour === null) {
            return 0;
        }

        $now = now($timezone);

        if (in_array($now->dayOfWeek, $days, true) && $now->hour >= $startHour && $now->hour < $endHour) {
            return 0;
        }

        $next = $now->copy()->setTime($startHour, 0);

        while ($next->lessThanOrEqualTo($now) || ! in_array($next->dayOfWeek, $days, true)) {
            $next->addDay()->setTime($startHour, 0);
        }

        return max(1, (int) $now->diffInSeconds($next));
    }

    private function buildMessage(
        LoanRequest $loanRequest,
        string $status,
        array $templates,
        array $branding,
        OrganizationSettingsService $organizationSettings,
    ): ?string {
        $template = match ($status) {
            LoanRequestStatus::Approved->value => $templates['approved'] ?? null,
            LoanRequestStatus::Declined->value => $templates['declined'] ?? null,
            default => null,
        };

        if ($template === null) {
            return null;
        }

        $companyName = $this->normalizeText($branding['companyName'] ?? null) ?? '';
        $portalLabel = $this->normalizeText($branding['portalLabel'] ?? null) ?? '';
        $messagePrefix = $organizationSettings->resolveMessagePrefix(
            $companyName,
            $portalLabel,
        );
        $portalLabelForMessage = $organizationSettings->resolvePortalLabelForMessage(
            $companyName,
            $portalLabel,
        );
        $officeName = $organizationSettings->resolveOfficeName(
            $companyName,
            $portalLabel,
        );

        return $this->renderTemplate($template, [
            '{company_name}' => $companyName,
            '{portal_label}' => $portalLabelForMessage,
            '{message_prefix}' => $messagePrefix,
            '{office_name}' => $officeName,
            '{loan_reference}' => $loanRequest->reference,
            '{approved_amount}' => $this->formatCurrency(
                $loanRequest->approved_amount,
            ),
            '{approved_term}' => $loanRequest->approved_term !== null
                ? (string) (int) $loanRequest->approved_term
                : '',
        ]);
    }

    private function formatCurrency(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $numericValue = is_numeric($value) ? (float) $value : 0.0;

        return sprintf('Php. %s', number_format($numericValue, 2));
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function renderTemplate(string $template, array $replacements): string
    {
        $rendered = strtr($template, $replacements);

        $collapsed = preg_replace('/\\s{2,}/', ' ', trim($rendered));

        return $collapsed ?? trim($rendered);
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
