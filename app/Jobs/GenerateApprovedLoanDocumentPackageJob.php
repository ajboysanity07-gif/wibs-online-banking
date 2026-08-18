<?php

namespace App\Jobs;

use App\LoanDocumentPackageJobStatus;
use App\Models\LoanDocumentPackageJob;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateApprovedLoanDocumentPackageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $packageJobId)
    {
        $this->afterCommit();
    }

    public function handle(ApprovedLoanDocumentService $documentService): void
    {
        $packageJob = LoanDocumentPackageJob::find($this->packageJobId);

        if (! $packageJob instanceof LoanDocumentPackageJob) {
            return;
        }

        $packageJob->fill([
            'status' => LoanDocumentPackageJobStatus::Processing,
            'started_at' => now(),
        ])->save();

        $loanRequest = $packageJob->loanRequest;

        if ($loanRequest === null) {
            $packageJob->fill([
                'status' => LoanDocumentPackageJobStatus::Failed,
                'error_message' => 'The loan request no longer exists.',
                'completed_at' => now(),
            ])->save();

            return;
        }

        $built = $documentService->buildZipArchive($loanRequest);

        $storedPath = sprintf(
            'loan-document-packages/%d/%s',
            $packageJob->id,
            $built['filename'],
        );

        Storage::disk('local')->put($storedPath, File::get($built['path']));
        File::deleteDirectory($built['workingDirectory']);

        $packageJob->fill([
            'status' => LoanDocumentPackageJobStatus::Completed,
            'zip_disk' => 'local',
            'zip_path' => $storedPath,
            'zip_filename' => $built['filename'],
            'completed_at' => now(),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        LoanDocumentPackageJob::where('id', $this->packageJobId)->update([
            'status' => LoanDocumentPackageJobStatus::Failed,
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
