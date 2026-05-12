<?php

namespace App\Services\LoanRequests;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Notifications\LoanRequestCorrectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanRequestCorrectionService
{
    public function __construct(
        private LoanRequestDecisionService $decisionService,
        private LoanRequestService $loanRequestService,
        private LoanRequestPayloadSerializer $serializer,
    ) {}

    /**
     * @param  array{
     *     change_reason: string,
     *     typecode: string,
     *     requested_amount: string|float|int,
     *     requested_term: int|string,
     *     loan_purpose: string,
     *     availment_status: string,
     *     applicant: array<string, mixed>,
     *     co_maker_1: array<string, mixed>,
     *     co_maker_2: array<string, mixed>
     * }  $payload
     */
    public function correct(
        LoanRequest $loanRequest,
        AppUser $actor,
        array $payload,
    ): LoanRequest {
        $updated = DB::transaction(function () use ($loanRequest, $actor, $payload): LoanRequest {
            $lockedLoanRequest = LoanRequest::query()
                ->whereKey($loanRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureCorrectable($lockedLoanRequest, $actor);

            $lockedLoanRequest->load('people', 'reviewedBy');
            $before = $this->serializer->serializeDetail($lockedLoanRequest);

            $this->loanRequestService->fillSubmittedDetails(
                $lockedLoanRequest,
                $payload,
            );
            $lockedLoanRequest->save();

            $this->loanRequestService->upsertPeopleSnapshots(
                $lockedLoanRequest,
                $payload,
            );

            $updated = $lockedLoanRequest->refresh();
            $updated->load('people', 'reviewedBy');
            $after = $this->serializer->serializeDetail($updated);

            LoanRequestChange::query()->create([
                'loan_request_id' => $updated->id,
                'changed_by' => $actor->user_id,
                'reason' => $payload['change_reason'],
                'before_json' => $before,
                'after_json' => $after,
            ]);

            return $updated;
        });

        $this->notifyMemberOfCorrection($updated, $actor);

        return $updated;
    }

    private function notifyMemberOfCorrection(LoanRequest $loanRequest, AppUser $actor): void
    {
        $loanRequest->loadMissing('user');

        $member = $loanRequest->user;

        if ($member === null || ! $member->hasMemberAccess()) {
            return;
        }

        $member->notify(new LoanRequestCorrectedNotification($loanRequest, $actor));
    }

    private function ensureCorrectable(LoanRequest $loanRequest, AppUser $actor): void
    {
        if (! $this->isUnderReview($loanRequest)) {
            throw ValidationException::withMessages([
                'status' => 'Only under review requests can be corrected.',
            ]);
        }

        if ($this->decisionService->isOwnRequest($loanRequest, $actor)) {
            throw ValidationException::withMessages([
                'correction' => 'You cannot correct your own loan request.',
            ]);
        }
    }

    private function isUnderReview(LoanRequest $loanRequest): bool
    {
        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        return $status === LoanRequestStatus::UnderReview->value;
    }
}
