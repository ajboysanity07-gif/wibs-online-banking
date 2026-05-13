<?php

namespace App\Notifications;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\NotificationPayload;

class LoanRequestCancelledNotification extends AbstractDatabaseNotification
{
    public function __construct(
        LoanRequest $loanRequest,
        ?AppUser $actor = null,
    ) {
        parent::__construct();

        $loanRequest->loadMissing('user', 'cancelledBy');

        $reference = $loanRequest->reference;
        $member = $loanRequest->user;
        $actor ??= $loanRequest->cancelledBy;

        $this->payload = array_merge(
            [
                'type' => 'loan_request_cancelled',
                'loan_request_id' => $loanRequest->id,
                'reference' => $reference,
                'status' => LoanRequestStatus::Cancelled->value,
                'title' => 'Loan request cancelled',
                'message' => sprintf(
                    'Your approved loan request %s was cancelled. An admin may create a corrected request after reviewing the cancellation reason.',
                    $reference,
                ),
                'entity_type' => 'loan_request',
                'entity_id' => $loanRequest->id,
                'cancellation_reason' => $loanRequest->cancellation_reason,
                'cancelled_at' => $loanRequest->cancelled_at?->toDateTimeString(),
                'updated_at' => $loanRequest->updated_at?->toDateTimeString(),
                'changed_fields' => NotificationPayload::changedFields([
                    'status',
                    'cancellation_reason',
                    'cancelled_at',
                ]),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
