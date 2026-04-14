<?php

namespace App\Notifications;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LoanRequestSubmittedNotification extends Notification implements ShouldQueue
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
     *     member_id: int|null,
     *     member_name: string|null,
     *     member_acctno: string|null,
     *     loan_type_code: string|null,
     *     loan_type_label: string|null,
     *     requested_amount: float|int|string|null,
     *     requested_term: int|string|null,
     *     submitted_at: string|null,
     *     decision_notes: string|null,
     *     reviewed_at: string|null
     * }
     */
    private array $payload;

    /**
     * Create a new notification instance.
     */
    public function __construct(LoanRequest $loanRequest)
    {
        $this->afterCommit = true;

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
        $memberName = $loanRequest->user?->name;
        $reference = $loanRequest->reference;

        $this->payload = [
            'type' => 'loan_request_submitted',
            'loan_request_id' => $loanRequest->id,
            'reference' => $reference,
            'status' => $status,
            'title' => 'Loan request submitted',
            'message' => sprintf(
                '%s submitted a loan request %s.',
                $memberName ?: 'A member',
                $reference,
            ),
            'member_id' => $loanRequest->user?->user_id,
            'member_name' => $memberName,
            'member_acctno' => $loanRequest->user?->acctno,
            'loan_type_code' => $loanRequest->typecode,
            'loan_type_label' => $loanRequest->loan_type_label_snapshot,
            'requested_amount' => $loanRequest->requested_amount,
            'requested_term' => $loanRequest->requested_term,
            'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
            'decision_notes' => null,
            'reviewed_at' => null,
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
     *     member_id: int|null,
     *     member_name: string|null,
     *     member_acctno: string|null,
     *     loan_type_code: string|null,
     *     loan_type_label: string|null,
     *     requested_amount: float|int|string|null,
     *     requested_term: int|string|null,
     *     submitted_at: string|null,
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
     *     member_id: int|null,
     *     member_name: string|null,
     *     member_acctno: string|null,
     *     loan_type_code: string|null,
     *     loan_type_label: string|null,
     *     requested_amount: float|int|string|null,
     *     requested_term: int|string|null,
     *     submitted_at: string|null,
     *     decision_notes: string|null,
     *     reviewed_at: string|null
     * }
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
