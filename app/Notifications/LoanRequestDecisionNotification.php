<?php

namespace App\Notifications;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\NotificationPayload;

class LoanRequestDecisionNotification extends AbstractDatabaseNotification
{
    public function __construct(
        LoanRequest $loanRequest,
        ?AppUser $actor = null,
    ) {
        parent::__construct();

        $loanRequest->loadMissing('user', 'reviewedBy');

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
        $reference = $loanRequest->reference;
        $member = $loanRequest->user;
        $actor ??= $loanRequest->reviewedBy;

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

        $this->payload = array_merge(
            [
                'type' => 'loan_request_decision',
                'loan_request_id' => $loanRequest->id,
                'reference' => $reference,
                'status' => $status,
                'title' => $title,
                'message' => $message,
                'entity_type' => 'loan_request',
                'entity_id' => $loanRequest->id,
                'decision_notes' => $loanRequest->decision_notes,
                'reviewed_at' => $loanRequest->reviewed_at?->toDateTimeString(),
                'updated_at' => $loanRequest->updated_at?->toDateTimeString(),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
