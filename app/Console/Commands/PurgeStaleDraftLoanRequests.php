<?php

namespace App\Console\Commands;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PurgeStaleDraftLoanRequests extends Command
{
    protected $signature = 'loan-requests:purge-stale-drafts
                            {--days=30 : Purge drafts not updated within this many days}
                            {--dry-run : Preview what would be purged without deleting anything}';

    protected $description = 'Hard-delete draft loan requests that have not been touched in a while';

    public function handle(LoanRequestService $service): int
    {
        if (! Schema::hasTable('loan_requests')) {
            $this->error('Required tables are missing. Run migrations first.');

            return self::FAILURE;
        }

        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = CarbonImmutable::now()->subDays($days);

        $this->info(sprintf(
            '%s draft loan requests not updated since %s (%d days)…',
            $dryRun ? '[DRY RUN] Would purge' : 'Purging',
            $cutoff->toDateTimeString(),
            $days,
        ));

        $purged = 0;

        LoanRequest::query()
            ->where('status', LoanRequestStatus::Draft->value)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($drafts) use ($service, $dryRun, &$purged): void {
                foreach ($drafts as $draft) {
                    $this->line(sprintf(
                        '  %s draft #%d (last updated %s)',
                        $dryRun ? '[would purge]' : '[purged]',
                        $draft->id,
                        $draft->updated_at?->toDateTimeString(),
                    ));

                    if (! $dryRun) {
                        $service->discardDraft($draft);
                    }

                    $purged++;
                }
            });

        $this->info(sprintf(
            '%s complete: %d draft(s) %s.',
            $dryRun ? 'Dry run' : 'Purge',
            $purged,
            $dryRun ? 'would be purged' : 'purged',
        ));

        return self::SUCCESS;
    }
}
