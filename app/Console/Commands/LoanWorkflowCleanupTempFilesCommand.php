<?php

namespace App\Console\Commands;

use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Console\Command;

class LoanWorkflowCleanupTempFilesCommand extends Command
{
    protected $signature = 'loan-workflow:cleanup-temp-files
        {--dry-run : Report candidate temporary files without deleting them}';

    protected $description = 'Clean abandoned temporary loan workflow generation files.';

    public function handle(
        LoanWorkflowProductionSupportService $supportService,
    ): int {
        $apply = ! (bool) $this->option('dry-run');
        $result = $supportService->cleanupTemporaryFiles($apply);

        $this->info(sprintf(
            '%s temporary workflow files. Candidates: %d. Deleted: %d.',
            $apply ? 'Cleaned' : 'Scanned',
            (int) ($result['candidates'] ?? 0),
            (int) ($result['deleted'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
