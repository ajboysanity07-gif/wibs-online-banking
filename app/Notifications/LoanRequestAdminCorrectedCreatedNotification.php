<?php

namespace App\Notifications;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Support\NotificationPayload;

class LoanRequestAdminCorrectedCreatedNotification extends AbstractDatabaseNotification
{
    public function __construct(
        LoanRequest $sourceLoanRequest,
        LoanRequest $correctedLoanRequest,
        string $correctionReason,
        ?AppUser $actor = null,
    ) {
        parent::__construct();

        $sourceLoanRequest->loadMissing('user');
        $correctedLoanRequest->loadMissing('user');

        $member = $sourceLoanRequest->user;
        $actor ??= null;

        $this->payload = array_merge(
            [
                'type' => 'loan_request_corrected_created',
                'loan_request_id' => $correctedLoanRequest->id,
                'reference' => $correctedLoanRequest->reference,
                'status' => LoanRequestStatus::UnderReview->value,
                'title' => 'Corrected loan request created',
                'message' => sprintf(
                    'An admin created a corrected loan request from your cancelled request %s. Please review the updated request details.',
                    $sourceLoanRequest->reference,
                ),
                'entity_type' => 'loan_request',
                'entity_id' => $correctedLoanRequest->id,
                'old_loan_request_id' => $sourceLoanRequest->id,
                'old_reference' => $sourceLoanRequest->reference,
                'corrected_loan_request_id' => $correctedLoanRequest->id,
                'corrected_loan_request_reference' => $correctedLoanRequest->reference,
                'corrected_from_id' => $sourceLoanRequest->id,
                'correction_reason' => $correctionReason,
                'updated_at' => $correctedLoanRequest->updated_at?->toDateTimeString(),
                'changed_fields' => NotificationPayload::changedFields([
                    'corrected_from_id',
                    'loan_details',
                    'applicant',
                    'co_maker_1',
                    'co_maker_2',
                    'admin_correction_reason',
                ]),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
