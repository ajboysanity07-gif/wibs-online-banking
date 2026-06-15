<?php

namespace App\Services\LoanRequests;

use App\Jobs\SendLoanWorkflowSmsJob;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestNotificationEvent;
use App\Notifications\LoanRequestWorkflowStatusNotification;
use Illuminate\Database\Eloquent\Model;

class LoanRequestNotificationService
{
    public const EVENT_AWAITING_MEMBER_CORRECTION = 'awaiting_member_correction';

    public const EVENT_AWAITING_MEMBER_INFORMATION = 'awaiting_member_information';

    public const EVENT_AWAITING_MEMBER_ACCEPTANCE = 'awaiting_member_acceptance';

    public const EVENT_REJECTED_DURING_PROCESSING = 'rejected_during_processing';

    public const EVENT_DECLINED_BY_MANAGER = 'declined_by_manager';

    public const EVENT_APPROVED_FOR_WIBS = 'approved_for_wibs_processing';

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

        $actionUrl = route('loan-requests.action', [
            'reference' => $loanRequest->reference,
        ], false);

        if (! $this->shouldSendPortalNotification($loanRequest, $eventType, $member)) {
            $this->maybeQueueSms($loanRequest, $eventType, $content, $member);

            return;
        }

        $this->createChannelEvent(
            $loanRequest,
            $eventType,
            'database',
            (string) $member->user_id,
            'sent',
            now(),
            $member->user_id,
        );

        if (is_string($member->email) && trim($member->email) !== '') {
            $this->createChannelEvent(
                $loanRequest,
                $eventType,
                'email',
                trim($member->email),
                'sent',
                now(),
                $member->user_id,
            );
        }

        $member->notify(
            new LoanRequestWorkflowStatusNotification(
                $loanRequest,
                $actor,
                [
                    'event_type' => $eventType,
                    'title' => $content['title'],
                    'message' => $content['message'],
                    'reason' => $content['reason'] ?? null,
                    'status' => $loanRequest->status instanceof \App\LoanRequestStatus
                        ? $loanRequest->status->value
                        : (string) $loanRequest->status,
                    'action_url' => $actionUrl,
                ],
            ),
        );

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

        if ($event->reminder_sent_at !== null || $event->sent_at === null) {
            return false;
        }

        if ($event->sent_at->diffInDays(now()) < 3) {
            return false;
        }

        SendLoanWorkflowSmsJob::dispatch(
            $event->id,
            true,
        )->afterCommit();

        return true;
    }

    private function shouldSendPortalNotification(
        LoanRequest $loanRequest,
        string $eventType,
        AppUser $member,
    ): bool {
        return ! LoanRequestNotificationEvent::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('event_type', $eventType)
            ->where('channel', 'database')
            ->where('recipient_user_id', $member->user_id)
            ->exists();
    }

    /**
     * @param  array{title:string, message:string, reason?:string|null}  $content
     */
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
        \DateTimeInterface $sentAt,
        ?int $recipientUserId = null,
    ): Model {
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
                'sent_at' => $sentAt,
            ],
        );
    }
}
