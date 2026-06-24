<?php

namespace App\Console\Commands;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArchiveOldLoanRequests extends Command
{
    protected $signature = 'loan-requests:archive
                            {--dry-run : Preview what would be archived without making changes}
                            {--years= : Override retention years from config}';

    protected $description = 'Archive loan requests older than the configured retention period';

    private const TERMINAL_STATUSES = [
        LoanRequestStatus::Released->value,
        LoanRequestStatus::WibsLoanCreated->value,
        LoanRequestStatus::Rejected->value,
        LoanRequestStatus::Declined->value,
        LoanRequestStatus::MemberDeclinedTerms->value,
        LoanRequestStatus::Cancelled->value,
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('loan_requests') || ! Schema::hasTable('archived_loan_requests')) {
            $this->error('Required tables are missing. Run migrations first.');

            return self::FAILURE;
        }

        $years = $this->option('years') !== null
            ? (int) $this->option('years')
            : (int) config('loan_workflow.retention_years', 5);

        if ($years < 1) {
            $this->error('Retention years must be at least 1.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = CarbonImmutable::now()->subYears($years)->startOfDay();

        $this->info(sprintf(
            '%s loan requests created before %s (retention: %d years)…',
            $dryRun ? '[DRY RUN] Would archive' : 'Archiving',
            $cutoff->toDateString(),
            $years,
        ));

        $archived = 0;
        $skipped = 0;

        LoanRequest::query()
            ->whereIn('status', self::TERMINAL_STATUSES)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($requests) use ($dryRun, &$archived, &$skipped): void {
                foreach ($requests as $loanRequest) {
                    if (DB::table('archived_loan_requests')->where('original_id', $loanRequest->id)->exists()) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        DB::table('archived_loan_requests')->insert([
                            'original_id' => $loanRequest->id,
                            'reference' => $loanRequest->reference,
                            'user_id' => $loanRequest->user_id,
                            'status' => $loanRequest->status instanceof LoanRequestStatus
                                ? $loanRequest->status->value
                                : (string) $loanRequest->status,
                            'original_created_at' => $loanRequest->created_at,
                            'original_submitted_at' => $loanRequest->submitted_at,
                            'original_approved_at' => $loanRequest->approved_at,
                            'original_rejected_at' => $loanRequest->rejected_at,
                            'data_json' => json_encode($loanRequest->toArray()),
                            'archived_at' => now(),
                            'archived_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $loanRequest->delete();
                    }

                    $archived++;
                    $this->line(sprintf(
                        '  %s %s (%s)',
                        $dryRun ? '[would archive]' : '[archived]',
                        $loanRequest->reference,
                        $loanRequest->created_at?->toDateString(),
                    ));
                }
            });

        $this->info(sprintf(
            '%s complete: %d archived, %d already archived (skipped).',
            $dryRun ? 'Dry run' : 'Archive',
            $archived,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
