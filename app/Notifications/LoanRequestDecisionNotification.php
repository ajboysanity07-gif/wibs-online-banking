<?php

namespace App\Notifications;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LoanRequestDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var array{
     *     type: string,
     *     loan_request_id: int,
     *     reference: string,
     *     status: string,
     *     title: string,
     *     message: string,
     *     decision_notes: string|null,
     *     reviewed_at: string|null
     * }
     */
    private array $payload;

    public function __construct(LoanRequest $loanRequest)
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
        $reference = $loanRequest->reference;
        [$title, $message] = match ($status) {
            LoanRequestStatus::Approved->value => [
                'Loan request approved',
                sprintf('Your loan request %s was approved.', $reference),
            ],
            LoanRequestStatus::Declined->value => [
                'Loan request declined',
                sprintf('Your loan request %s was declined.', $reference),
            ],
            default => [
                'Loan request updated',
                sprintf('Your loan request %s was updated.', $reference),
            ],
        };

        $this->payload = [
            'type' => 'loan_request_decision',
            'loan_request_id' => $loanRequest->id,
            'reference' => $reference,
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'decision_notes' => $loanRequest->decision_notes,
            'reviewed_at' => $loanRequest->reviewed_at?->toDateTimeString(),
        ];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{
     *     type: string,
     *     loan_request_id: int,
     *     reference: string,
     *     status: string,
     *     title: string,
     *     message: string,
     *     decision_notes: string|null,
     *     reviewed_at: string|null
     * }
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * @return array{
     *     type: string,
     *     loan_request_id: int,
     *     reference: string,
     *     status: string,
     *     title: string,
     *     message: string,
     *     decision_notes: string|null,
     *     reviewed_at: string|null
     * }
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
