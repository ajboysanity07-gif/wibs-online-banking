<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanRequestWorkflowStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{
     *     event_type:string,
     *     title:string,
     *     message:string,
     *     action_url:string,
     *     status:string,
     *     reason?:string|null
     * }  $payload
     */
    public function __construct(
        private LoanRequest $loanRequest,
        private ?AppUser $actor,
        private array $payload,
    ) {
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (
            $notifiable instanceof AppUser
            && is_string($notifiable->email)
            && trim($notifiable->email) !== ''
        ) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $member = $this->loanRequest->user;

        return array_merge(
            [
                'type' => 'loan_request_workflow_status',
                'loan_request_id' => $this->loanRequest->id,
                'reference' => $this->loanRequest->reference,
                'status' => $this->payload['status'],
                'event_type' => $this->payload['event_type'],
                'title' => $this->payload['title'],
                'message' => $this->payload['message'],
                'reason' => $this->payload['reason'] ?? null,
                'action_url' => $this->payload['action_url'],
                'entity_type' => 'loan_request',
                'entity_id' => $this->loanRequest->id,
                'updated_at' => $this->loanRequest->updated_at?->toDateTimeString(),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($this->actor),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->payload['title'])
            ->greeting('Loan Request Update')
            ->line($this->payload['message'])
            ->when(
                is_string($this->payload['reason'] ?? null)
                    && trim((string) $this->payload['reason']) !== '',
                fn (MailMessage $mail): MailMessage => $mail->line(
                    'Reason: '.$this->payload['reason'],
                ),
            )
            ->action('Open Loan Request', $this->payload['action_url'])
            ->line('Sign in to continue with the required action or to review the latest status.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
