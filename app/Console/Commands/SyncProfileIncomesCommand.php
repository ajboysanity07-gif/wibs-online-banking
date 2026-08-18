<?php

namespace App\Console\Commands;

use App\LoanRequestPersonRole;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Console\Command;

class SyncProfileIncomesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan-requests:sync-profile-incomes
        {--fix : Apply the income sync (default is dry run)}
        {--limit= : Limit the number of members to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync member profile gross monthly income onto active loan-request applicant snapshots so GNTHP figures stay current.';

    public function __construct(private readonly LoanRequestService $loanRequestService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $limit = $this->normalizeLimit($this->option('limit'));

        $this->line($fix
            ? 'Fix mode enabled. Writing income syncs.'
            : 'Dry run. No writes will be applied.');

        $members = AppUser::query()
            ->whereHas('memberApplicationProfile', fn ($query) => $query->whereNotNull('gross_monthly_income'))
            ->with('memberApplicationProfile')
            ->orderBy('user_id');

        if ($limit !== null) {
            $members->limit($limit);
        }

        $checkedMembers = 0;
        $mismatchedRows = [];
        $updated = 0;

        foreach ($members->cursor() as $member) {
            $checkedMembers++;

            $profileIncome = $member->memberApplicationProfile?->gross_monthly_income;

            if ($profileIncome === null) {
                continue;
            }

            $activeRequests = LoanRequest::query()
                ->where(fn ($query) => $query
                    ->where('user_id', $member->user_id)
                    ->when(
                        trim((string) $member->acctno) !== '',
                        fn ($query) => $query->orWhere('acctno', $member->acctno),
                    ))
                ->whereNotIn('status', LoanRequestService::INCOME_SYNC_EXCLUDED_STATUSES)
                ->with('people')
                ->get();

            $memberHasMismatch = false;

            foreach ($activeRequests as $loanRequest) {
                $applicant = $loanRequest->people
                    ->firstWhere('role', LoanRequestPersonRole::Applicant);

                if ($applicant === null) {
                    continue;
                }

                $snapshotIncome = $applicant->gross_monthly_income;

                if (
                    $snapshotIncome !== null
                    && abs((float) $snapshotIncome - (float) $profileIncome) < 0.005
                ) {
                    continue;
                }

                $memberHasMismatch = true;

                $mismatchedRows[] = [
                    'member' => $member->user_id,
                    'loan_request' => $loanRequest->id,
                    'snapshot_income' => (string) ($snapshotIncome ?? 'null'),
                    'profile_income' => (string) $profileIncome,
                ];

                $this->line(sprintf(
                    'member=%d loan_request=%d snapshot_income=%s profile_income=%s',
                    $member->user_id,
                    $loanRequest->id,
                    $snapshotIncome ?? 'null',
                    $profileIncome,
                ));
            }

            if ($fix && $memberHasMismatch) {
                $updated += $this->loanRequestService->syncApplicantIncomeFromProfile(
                    $member,
                    (float) $profileIncome,
                );
            }
        }

        $this->newLine();
        $this->line(sprintf('Members scanned: %d', $checkedMembers));

        if ($fix) {
            $this->line(sprintf('Applicant snapshots updated: %d', $updated));
        } else {
            $this->line(sprintf('Mismatched applicant snapshots found: %d', count($mismatchedRows)));
            $this->line('Run with --fix to apply.');
        }

        return self::SUCCESS;
    }

    private function normalizeLimit(mixed $limit): ?int
    {
        if ($limit === null || $limit === '') {
            return null;
        }

        if (! is_numeric($limit)) {
            return null;
        }

        $value = (int) $limit;

        return $value > 0 ? $value : null;
    }
}
