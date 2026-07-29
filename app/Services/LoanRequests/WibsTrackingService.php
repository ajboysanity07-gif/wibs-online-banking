<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use Carbon\Carbon;
use InvalidArgumentException;

class WibsTrackingService
{
    public function __construct(
        private LoanRequestNotificationService $notificationService,
        private LoanRequestDocumentWorkflowService $documentWorkflowService,
    ) {}

    public function markForEncoding(LoanRequest $loanRequest, AppUser $actor): void
    {
        $this->assertStatus($loanRequest, LoanRequestStatus::ConvertedToLoan);

        $loanRequest->update([
            'status' => LoanRequestStatus::ForWibsEncoding,
            'wibs_encoded_at' => now(),
        ]);

        LoanRequestChange::create([
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => LoanRequestChange::ACTION_MARK_FOR_WIBS_ENCODING,
            'from_status' => LoanRequestStatus::ConvertedToLoan->value,
            'to_status' => LoanRequestStatus::ForWibsEncoding->value,
            'reason' => '',
            'before_json' => [],
            'after_json' => [],
        ]);

        $this->notificationService->notifyMember(
            $loanRequest,
            LoanRequestNotificationService::EVENT_FOR_WIBS_ENCODING,
            [
                'title' => 'Your loan is being processed in WIBS.',
                'message' => 'Your loan request has been forwarded for WIBS encoding and will be processed shortly.',
            ],
            $actor,
        );
    }

    public function recordWibsReference(
        LoanRequest $loanRequest,
        AppUser $actor,
        string $wibsLoanReference,
    ): void {
        $this->assertStatus($loanRequest, LoanRequestStatus::ForWibsEncoding);

        $loanRequest->update([
            'status' => LoanRequestStatus::WibsLoanCreated,
            'wibs_loan_reference' => $wibsLoanReference,
        ]);

        LoanRequestChange::create([
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => LoanRequestChange::ACTION_RECORD_WIBS_REFERENCE,
            'from_status' => LoanRequestStatus::ForWibsEncoding->value,
            'to_status' => LoanRequestStatus::WibsLoanCreated->value,
            'reason' => '',
            'before_json' => [],
            'after_json' => [],
        ]);

        $this->notificationService->notifyMember(
            $loanRequest,
            LoanRequestNotificationService::EVENT_WIBS_LOAN_CREATED,
            [
                'title' => 'Your loan has been created in WIBS.',
                'message' => 'Your loan has been encoded in WIBS. The release date will be scheduled next.',
            ],
            $actor,
        );
    }

    public function scheduleRelease(
        LoanRequest $loanRequest,
        AppUser $actor,
        Carbon $releaseDate,
    ): void {
        $this->assertStatus($loanRequest, LoanRequestStatus::WibsLoanCreated);

        $loanRequest->update([
            'status' => LoanRequestStatus::ReleaseScheduled,
            'wibs_release_date' => $releaseDate->toDateString(),
        ]);

        $this->documentWorkflowService->markAffectedDocumentsStale(
            $loanRequest,
            ['wibs_release_date'],
        );

        LoanRequestChange::create([
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => LoanRequestChange::ACTION_SCHEDULE_WIBS_RELEASE,
            'from_status' => LoanRequestStatus::WibsLoanCreated->value,
            'to_status' => LoanRequestStatus::ReleaseScheduled->value,
            'reason' => '',
            'before_json' => [],
            'after_json' => [],
        ]);

        $this->notificationService->notifyMember(
            $loanRequest,
            LoanRequestNotificationService::EVENT_RELEASE_SCHEDULED,
            [
                'title' => 'Your loan release has been scheduled.',
                'message' => 'Your loan is scheduled for release on '.$releaseDate->toFormattedDateString().'.',
            ],
            $actor,
        );
    }

    public function confirmRelease(LoanRequest $loanRequest, AppUser $actor): void
    {
        $this->assertStatus($loanRequest, LoanRequestStatus::ReleaseScheduled);

        $loanRequest->update([
            'status' => LoanRequestStatus::Released,
            'wibs_released_at' => now(),
        ]);

        LoanRequestChange::create([
            'loan_request_id' => $loanRequest->id,
            'changed_by' => $actor->user_id,
            'action' => LoanRequestChange::ACTION_CONFIRM_WIBS_RELEASE,
            'from_status' => LoanRequestStatus::ReleaseScheduled->value,
            'to_status' => LoanRequestStatus::Released->value,
            'reason' => '',
            'before_json' => [],
            'after_json' => [],
        ]);

        $this->notificationService->notifyMember(
            $loanRequest,
            LoanRequestNotificationService::EVENT_RELEASED,
            [
                'title' => 'Your loan has been released.',
                'message' => 'Congratulations! Your loan has been released. Please coordinate with your branch for the next steps.',
            ],
            $actor,
        );
    }

    private function assertStatus(LoanRequest $loanRequest, LoanRequestStatus $expected): void
    {
        $current = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status
            : LoanRequestStatus::tryFrom((string) $loanRequest->status);

        if ($current !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'Expected loan request status "%s" but got "%s".',
                $expected->value,
                $current?->value ?? (string) $loanRequest->status,
            ));
        }
    }
}
