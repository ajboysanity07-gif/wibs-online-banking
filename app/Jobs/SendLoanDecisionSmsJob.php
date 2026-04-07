<?php

namespace App\Jobs;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\Sms\SemaphoreSmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLoanDecisionSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $loanRequestId) {}

    /**
     * Execute the job.
     */
    public function handle(SemaphoreSmsService $smsService): void
    {
        $loanRequest = LoanRequest::query()
            ->with('user')
            ->find($this->loanRequestId);

        if ($loanRequest === null) {
            Log::warning('Loan request not found for SMS notification.', [
                'loan_request_id' => $this->loanRequestId,
            ]);

            return;
        }

        $status = $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;

        if (! in_array($status, [
            LoanRequestStatus::Approved->value,
            LoanRequestStatus::Declined->value,
        ], true)) {
            Log::info('Loan request SMS skipped for non-decision status.', [
                'loan_request_id' => $loanRequest->id,
                'status' => $status,
            ]);

            return;
        }

        $phoneNumber = $loanRequest->user?->phoneno;

        if (! is_string($phoneNumber) || trim($phoneNumber) === '') {
            Log::info('Loan request SMS skipped due to missing phone number.', [
                'loan_request_id' => $loanRequest->id,
            ]);

            return;
        }

        $message = $this->buildMessage($loanRequest, $status);

        if ($message === null) {
            return;
        }

        $sent = $smsService->send($phoneNumber, $message);

        if (! $sent) {
            Log::warning('Loan request SMS failed to send.', [
                'loan_request_id' => $loanRequest->id,
            ]);
        }
    }

    private function buildMessage(LoanRequest $loanRequest, string $status): ?string
    {
        if ($status === LoanRequestStatus::Approved->value) {
            $approvedAmount = $loanRequest->approved_amount;
            $approvedTerm = $loanRequest->approved_term;
            $amountText = $approvedAmount !== null
                ? sprintf('PHP %s', number_format((float) $approvedAmount, 2))
                : 'your approved amount';
            $termText = $approvedTerm !== null
                ? sprintf('%s months', $approvedTerm)
                : 'the approved term';

            return sprintf(
                'WIBS: Your loan request #%s was approved for %s over %s. Our team will contact you for next steps.',
                $loanRequest->id,
                $amountText,
                $termText,
            );
        }

        if ($status === LoanRequestStatus::Declined->value) {
            return sprintf(
                'WIBS: Your loan request #%s was declined. Please contact the coop for questions.',
                $loanRequest->id,
            );
        }

        return null;
    }
}
