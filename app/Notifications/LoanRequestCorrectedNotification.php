<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\NotificationPayload;

class LoanRequestCorrectedNotification extends AbstractDatabaseNotification
{
    public function __construct(
        LoanRequest $loanRequest,
        ?AppUser $actor = null,
    ) {
        parent::__construct();

        $loanRequest->loadMissing('user');

        $reference = $loanRequest->reference;
        $member = $loanRequest->user;

        $this->payload = array_merge(
            [
                'type' => 'loan_request_updated',
                'loan_request_id' => $loanRequest->id,
                'reference' => $reference,
                'status' => 'updated',
                'title' => 'Loan request updated',
                'message' => sprintf(
                    'An admin corrected details on your loan request %s. Please review the updated information.',
                    $reference,
                ),
                'entity_type' => 'loan_request',
                'entity_id' => $loanRequest->id,
                'updated_at' => $loanRequest->updated_at?->toDateTimeString(),
                'changed_fields' => NotificationPayload::changedFields([
                    'loan_details',
                    'applicant',
                    'co_maker_1',
                    'co_maker_2',
                ]),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
