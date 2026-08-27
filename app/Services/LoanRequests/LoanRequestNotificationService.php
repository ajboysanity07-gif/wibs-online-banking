<?php

namespace App\Services\LoanRequests;

use App\Jobs\SendLoanWorkflowSmsJob;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestNotificationEvent;
use App\Notifications\LoanRequestWorkflowStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class LoanRequestNotificationService
{
    public const EVENT_AWAITING_MEMBER_CORRECTION = 'awaiting_member_correction';

    public const EVENT_AWAITING_MEMBER_INFORMATION = 'awaiting_member_information';

    public const EVENT_AWAITING_MEMBER_ACCEPTANCE = 'awaiting_member_acceptance';

    public const EVENT_PAYOUT_DETAILS_UPDATED_BY_STAFF = 'payout_details_updated_by_staff';

    public const EVENT_REJECTED_DURING_PROCESSING = 'rejected_during_processing';

    public const EVENT_DECLINED_BY_MANAGER = 'declined_by_manager';

    public const EVENT_APPROVED_FOR_WIBS = 'approved_for_wibs_processing';

    public const EVENT_FOR_WIBS_ENCODING = 'for_wibs_encoding';

    public const EVENT_WIBS_LOAN_CREATED = 'wibs_loan_created';

    public const EVENT_RELEASE_SCHEDULED = 'release_scheduled';

    public const EVENT_RELEASED = 'released';

    public const EVENT_CANCELLED = 'cancelled';

    public const EVENT_REOPENED = 'reopened';

    /**
     * @param  array{title:string, message:string, reason?:string|null}  $content
     */
    public function notifyMember(
        LoanRequest $loanRequest,
        string $eventType,
        array $content,
        ?AppUser $actor = null,
    ): void {
        $loanRequest->loadMissing('user');

        $member = $loanRequest->user;

        if (! $member instanceof AppUser || ! $member->hasMemberAccess()) {
            return;
        }

        $actionUrl = URL::temporarySignedRoute(
            'loan-requests.action',
            now()->addHours(max(
                1,
                (int) config('loan_workflow.notifications.action_link_ttl_hours', 168),
            )),
            [
                'reference' => $loanRequest->reference,
            ],
        );

        $payload = [
            'event_type' => $eventType,
            'title' => $content['title'],
            'message' => $content['message'],
            'reason' => $content['reason'] ?? null,
            'status' => $loanRequest->status instanceof \App\LoanRequestStatus
                ? $loanRequest->status->value
                : (string) $loanRequest->status,
            'action_url' => $actionUrl,
        ];

        $channelEvents = DB::transaction(function () use (
            $loanRequest,
            $eventType,
            $member,
            $payload,
        ): array {
            LoanRequest::query()
                ->whereKey($loanRequest->id)
                ->lockForUpdate()
                ->first();

            $events = [];

            $databaseEvent = $this->createChannelEvent(
                $loanRequest,
                $eventType,
                'database',
                (string) $member->user_id,
                'queued',
                null,
                $member->user_id,
                $payload,
            );

            if ($databaseEvent->wasRecentlyCreated) {
                $events[] = $databaseEvent;
            }

            if (is_string($member->email) && trim($member->email) !== '') {
                $emailEvent = $this->createChannelEvent(
                    $loanRequest,
                    $eventType,
                    'email',
                    trim($member->email),
                    'queued',
                    null,
                    $member->user_id,
                    $payload,
                );

                if ($emailEvent->wasRecentlyCreated) {
                    $events[] = $emailEvent;
                }
            }

            return $events;
        });

        foreach ($channelEvents as $event) {
            if (! $event instanceof LoanRequestNotificationEvent) {
                continue;
            }

            $member->notify(new LoanRequestWorkflowStatusNotification(
                $loanRequest,
                $actor,
                $payload,
                [$event->channel],
                $event->id,
            ));
        }

        $this->maybeQueueSms($loanRequest, $eventType, $content, $member);
    }

    public function sendReminderIfDue(LoanRequest $loanRequest, string $eventType): bool
    {
        $loanRequest->loadMissing('user');

        $member = $loanRequest->user;

        if (! $member instanceof AppUser) {
            return false;
        }

        $event = LoanRequestNotificationEvent::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('event_type', $eventType)
            ->where('channel', 'sms')
            ->where('recipient_user_id', $member->user_id)
            ->first();

        if (! $event instanceof LoanRequestNotificationEvent) {
            return false;
        }

        if (
            $event->sent_at === null
            || in_array($event->result, ['failed', 'skipped'], true)
        ) {
            return false;
        }

        if (
            (int) ($event->reminder_attempts ?? 0) >= max(
                0,
                (int) config('loan_workflow.notifications.max_reminders', 1),
            )
        ) {
            return false;
        }

        if ($event->sent_at->diffInDays(now()) < max(
            1,
            (int) config('loan_workflow.notifications.reminder_after_days', 3),
        )) {
            return false;
        }

        SendLoanWorkflowSmsJob::dispatch(
            $event->id,
            true,
        )->afterCommit();

        return true;
    }

    private function maybeQueueSms(
        LoanRequest $loanRequest,
        string $eventType,
        array $content,
        AppUser $member,
    ): void {
        $phoneNumber = is_string($member->phoneno)
            ? trim($member->phoneno)
            : '';

        if ($phoneNumber === '') {
            return;
        }

        $event = LoanRequestNotificationEvent::query()->firstOrCreate(
            [
                'loan_request_id' => $loanRequest->id,
                'event_type' => $eventType,
                'channel' => 'sms',
                'recipient' => $phoneNumber,
            ],
            [
                'recipient_user_id' => $member->user_id,
                'result' => 'queued',
                'queued_at' => now(),
                'metadata_json' => $content,
            ],
        );

        if (! $event->wasRecentlyCreated) {
            return;
        }

        SendLoanWorkflowSmsJob::dispatch($event->id)->afterCommit();
    }

    private function createChannelEvent(
        LoanRequest $loanRequest,
        string $eventType,
        string $channel,
        string $recipient,
        string $result,
        ?\DateTimeInterface $sentAt = null,
        ?int $recipientUserId = null,
        ?array $metadata = null,
    ): LoanRequestNotificationEvent {
        return LoanRequestNotificationEvent::query()->firstOrCreate(
            [
                'loan_request_id' => $loanRequest->id,
                'event_type' => $eventType,
                'channel' => $channel,
                'recipient' => $recipient,
            ],
            [
                'recipient_user_id' => $recipientUserId,
                'result' => $result,
                'queued_at' => now(),
                'attempt_count' => 0,
                'retry_count' => 0,
                'reminder_attempts' => 0,
                'sent_at' => $sentAt,
                'metadata_json' => $metadata,
            ],
        );
    }
}
