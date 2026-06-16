<?php

namespace App\Jobs;

use App\Models\LoanRequestNotificationEvent;
use App\Services\Sms\SemaphoreSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendLoanWorkflowSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public int $notificationEventId,
        public bool $isReminder = false,
    ) {
        $this->afterCommit();
        $this->onQueue((string) config(
            'loan_workflow.notifications.sms_queue',
            'loan-workflow-notifications',
        ));
        $this->tries = max(
            1,
            (int) config('loan_workflow.notifications.tries', 5),
        );
        $this->timeout = max(
            1,
            (int) config('loan_workflow.notifications.timeout', 120),
        );
    }

    public function handle(SemaphoreSmsService $smsService): void
    {
        $event = DB::transaction(function () use (
            $smsService,
        ): ?LoanRequestNotificationEvent {
            $event = LoanRequestNotificationEvent::query()
                ->with('loanRequest')
                ->lockForUpdate()
                ->find($this->notificationEventId);

            if (! $event instanceof LoanRequestNotificationEvent) {
                return null;
            }

            if ($event->result === 'sent') {
                return null;
            }

            $maxReminders = max(
                0,
                (int) config('loan_workflow.notifications.max_reminders', 1),
            );

            if (
                $this->isReminder
                && (int) ($event->reminder_attempts ?? 0) >= $maxReminders
            ) {
                return null;
            }

            $recipient = trim((string) $event->recipient);

            if ($recipient === '') {
                $event->fill([
                    'result' => 'skipped',
                    'last_attempt_at' => now(),
                    'failed_at' => now(),
                    'provider_error' => 'SMS recipient is missing.',
                ])->save();

                return null;
            }

            $metadata = is_array($event->metadata_json)
                ? $event->metadata_json
                : [];
            $reference = $event->loanRequest?->reference ?? 'loan request';
            $message = trim((string) ($metadata['message'] ?? ''));
            $reason = trim((string) ($metadata['reason'] ?? ''));
            $smsMessage = $message !== ''
                ? $message
                : sprintf('Your loan request %s has an update.', $reference);

            if ($reason !== '') {
                $smsMessage .= ' Reason: '.$reason;
            }

            if ($this->isReminder) {
                $smsMessage .= ' Reminder: please sign in to complete the required action.';
            }

            $event->fill([
                'result' => 'sending',
                'last_attempt_at' => now(),
                'attempt_count' => (int) ($event->attempt_count ?? 0) + 1,
                'retry_count' => max(0, $this->attempts() - 1),
            ])->save();

            $delivery = $smsService->sendDetailed($recipient, $smsMessage);

            if ($delivery['status'] === 'sent') {
                $event->fill([
                    'result' => 'sent',
                    'sent_at' => $event->sent_at ?? now(),
                    'failed_at' => null,
                    'provider_reference' => $delivery['provider_reference'],
                    'provider_error' => null,
                    'reminder_attempts' => $this->isReminder
                        ? (int) ($event->reminder_attempts ?? 0) + 1
                        : (int) ($event->reminder_attempts ?? 0),
                    'reminder_sent_at' => $this->isReminder
                        ? now()
                        : $event->reminder_sent_at,
                ])->save();

                return $event;
            }

            if ($delivery['status'] === 'temporary_failure') {
                $event->fill([
                    'result' => 'queued',
                    'provider_error' => $delivery['provider_error'],
                ])->save();

                return $event;
            }

            $event->fill([
                'result' => $delivery['status'] === 'skipped'
                    ? 'skipped'
                    : 'failed',
                'failed_at' => now(),
                'provider_error' => $delivery['provider_error'],
            ])->save();

            return $event;
        });

        if (! $event instanceof LoanRequestNotificationEvent) {
            return;
        }

        if ($event->result === 'queued') {
            Log::warning('Loan workflow SMS send will be retried.', [
                'notification_event_id' => $event->id,
                'event_type' => $event->event_type,
            ]);

            $backoff = $this->backoff();
            $index = min(max($this->attempts() - 1, 0), count($backoff) - 1);
            $this->release($backoff[$index] ?? 60);
        }
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return config('loan_workflow.notifications.backoff_seconds', [
            60,
            300,
            900,
        ]);
    }
}
