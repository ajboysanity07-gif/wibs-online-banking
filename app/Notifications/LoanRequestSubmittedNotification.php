<?php

namespace App\Notifications;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Support\NotificationPayload;

class LoanRequestSubmittedNotification extends AbstractDatabaseNotification
{
    public function __construct(LoanRequest $loanRequest)
    {
        parent::__construct();

        $loanRequest->loadMissing('user');

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
        $member = $loanRequest->user;
        $memberName = $loanRequest->user?->name;
        $reference = $loanRequest->reference;

        $this->payload = array_merge(
            [
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
                'entity_type' => 'loan_request',
                'entity_id' => $loanRequest->id,
                'loan_type_code' => $loanRequest->typecode,
                'loan_type_label' => $loanRequest->loan_type_label_snapshot,
                'requested_amount' => $loanRequest->requested_amount,
                'requested_term' => $loanRequest->requested_term,
                'submitted_at' => $loanRequest->submitted_at?->toDateTimeString(),
                'decision_notes' => null,
                'reviewed_at' => null,
                'updated_at' => $loanRequest->updated_at?->toDateTimeString(),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($member),
        );
    }
}
