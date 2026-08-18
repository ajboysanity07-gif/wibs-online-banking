<?php

namespace App\Services\LoanRequests;

use App\Jobs\GenerateApprovedLoanDocumentPackageJob;
use App\LoanDocumentPackageJobStatus;
use App\Models\AppUser;
use App\Models\LoanDocumentPackageJob;
use App\Models\LoanRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApprovedLoanDocumentPackageJobService
{
    /**
     * Reuses an already-queued/processing job for the same loan request
     * instead of piling on duplicate work when a user double-clicks
     * "Download All" or re-opens the page while one is still running.
     */
    public function dispatch(LoanRequest $loanRequest, AppUser $requestedBy): LoanDocumentPackageJob
    {
        $existing = LoanDocumentPackageJob::query()
            ->where('loan_request_id', $loanRequest->id)
            ->whereIn('status', LoanDocumentPackageJobStatus::active())
            ->latest('id')
            ->first();

        if ($existing instanceof LoanDocumentPackageJob) {
            return $existing;
        }

        $packageJob = LoanDocumentPackageJob::create([
            'loan_request_id' => $loanRequest->id,
            'status' => LoanDocumentPackageJobStatus::Queued,
            'requested_by' => $requestedBy->user_id,
        ]);

        GenerateApprovedLoanDocumentPackageJob::dispatch($packageJob->id);

        return $packageJob;
    }

    /**
     * Scoped to the given loan request so a package job id from a different
     * loan request can never be looked up through this path.
     */
    public function findForLoanRequest(LoanRequest $loanRequest, int $packageJobId): ?LoanDocumentPackageJob
    {
        return LoanDocumentPackageJob::query()
            ->where('loan_request_id', $loanRequest->id)
            ->find($packageJobId);
    }

    public function downloadResponse(LoanDocumentPackageJob $packageJob): BinaryFileResponse
    {
        abort_unless(
            $packageJob->status === LoanDocumentPackageJobStatus::Completed
                && $packageJob->zip_path !== null,
            404,
        );

        $disk = Storage::disk($packageJob->zip_disk ?? 'local');

        abort_unless($disk->exists($packageJob->zip_path), 404);

        return response()->download(
            $disk->path($packageJob->zip_path),
            $packageJob->zip_filename ?? 'documents.zip',
            ['Content-Type' => 'application/zip'],
        );
    }
}
