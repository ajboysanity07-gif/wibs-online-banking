<?php

namespace App\Jobs;

use App\Models\LoanRequestNotificationEvent;
use App\Services\Sms\SemaphoreSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLoanWorkflowSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $notificationEventId,
        public bool $isReminder = false,
    ) {}

    public function handle(SemaphoreSmsService $smsService): void
    {
        $event = LoanRequestNotificationEvent::query()
            ->with('loanRequest')
            ->find($this->notificationEventId);

        if (! $event instanceof LoanRequestNotificationEvent) {
            return;
        }

        $recipient = trim((string) $event->recipient);

        if ($recipient === '') {
            $event->update(['result' => 'skipped']);

            return;
        }

        $metadata = is_array($event->metadata_json) ? $event->metadata_json : [];
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

        $sent = $smsService->send($recipient, $smsMessage);

        if (! $sent) {
            Log::warning('Loan workflow SMS failed to send.', [
                'notification_event_id' => $event->id,
                'event_type' => $event->event_type,
            ]);
        }

        $event->fill([
            'result' => $sent ? 'sent' : 'failed',
            'sent_at' => $event->sent_at ?? now(),
            'reminder_sent_at' => $this->isReminder ? now() : $event->reminder_sent_at,
        ])->save();
    }
}
