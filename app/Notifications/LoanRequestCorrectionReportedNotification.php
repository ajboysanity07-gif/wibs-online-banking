<?php

namespace App\Notifications;

use App\Models\LoanRequestCorrectionReport;
use App\Support\NotificationPayload;

class LoanRequestCorrectionReportedNotification extends AbstractDatabaseNotification
{
    public function __construct(LoanRequestCorrectionReport $report)
    {
        parent::__construct();

        $report->loadMissing('loanRequest', 'user');

        $loanRequest = $report->loanRequest;
        $member = $report->user;
        $memberName = NotificationPayload::memberDisplayName($member) ?? 'A member';

        $this->payload = array_merge(
            [
                'type' => 'loan_request_correction_reported',
                'title' => 'Loan request correction reported',
                'message' => sprintf(
                    '%s reported incorrect details on loan request %s.',
                    $memberName,
                    $loanRequest?->reference ?? sprintf(
                        'LNREQ-%06d',
                        (int) ($report->loan_request_id ?? 0),
                    ),
                ),
                'entity_type' => 'loan_request',
                'entity_id' => $loanRequest?->id ?? $report->loan_request_id,
                'loan_request_id' => $loanRequest?->id ?? $report->loan_request_id,
                'reference' => $loanRequest?->reference,
                'report_id' => $report->id,
                'issue_description' => $report->issue_description,
                'correct_information' => $report->correct_information,
                'supporting_note' => $report->supporting_note,
                'updated_at' => $report->updated_at?->toDateTimeString(),
                'changed_fields' => NotificationPayload::changedFields([
                    'issue_description',
                    'correct_information',
                    'supporting_note',
                    'status',
                ]),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($member),
        );
    }
}
