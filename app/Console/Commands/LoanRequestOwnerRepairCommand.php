<?php

namespace App\Console\Commands;

use App\Models\AppUser;
use App\Models\LoanRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class LoanRequestOwnerRepairCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan-requests:repair-owners
        {--fix : Update loan_requests.user_id based on acctno}
        {--limit= : Limit the number of loan requests to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and optionally repair mismatched loan request owners.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('loan_requests')) {
            $this->error('loan_requests table not found.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('appusers')) {
            $this->error('appusers table not found.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('loan_requests', 'acctno')
            || ! Schema::hasColumn('loan_requests', 'user_id')) {
            $this->error('loan_requests.acctno or loan_requests.user_id is missing.');

            return self::FAILURE;
        }

        $fix = (bool) $this->option('fix');
        $limit = $this->normalizeLimit($this->option('limit'));

        $this->line($fix
            ? 'Repair mode enabled. Updating mismatched owners.'
            : 'Dry run. No updates will be applied.');

        $query = LoanRequest::query()
            ->select(['id', 'user_id', 'acctno'])
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $checked = 0;
        $mismatches = 0;
        $repaired = 0;
        $missingAcctno = 0;
        $missingUser = 0;

        $query->chunkById(200, function ($requests) use (
            $fix,
            &$checked,
            &$mismatches,
            &$repaired,
            &$missingAcctno,
            &$missingUser
        ): void {
            $acctnos = $requests
                ->pluck('acctno')
                ->map(fn ($acctno) => trim((string) $acctno))
                ->filter(fn (string $acctno) => $acctno !== '')
                ->unique()
                ->values();

            $usersByAcctno = AppUser::query()
                ->whereIn('acctno', $acctnos)
                ->get(['user_id', 'acctno'])
                ->keyBy('acctno');

            foreach ($requests as $request) {
                $checked++;
                $acctno = trim((string) $request->acctno);

                if ($acctno === '') {
                    $missingAcctno++;

                    continue;
                }

                $owner = $usersByAcctno->get($acctno);

                if ($owner === null) {
                    $missingUser++;
                    $this->warn(
                        sprintf('No user found for loan_request=%d acctno=%s', $request->id, $acctno),
                    );

                    continue;
                }

                $expectedId = (int) $owner->user_id;
                $currentId = $request->user_id === null ? null : (int) $request->user_id;

                if ($currentId === $expectedId) {
                    continue;
                }

                $mismatches++;

                $this->warn(
                    sprintf(
                        'Mismatch loan_request=%d acctno=%s current_user_id=%s expected_user_id=%d',
                        $request->id,
                        $acctno,
                        $currentId === null ? 'null' : (string) $currentId,
                        $expectedId,
                    ),
                );

                if ($fix) {
                    $request->forceFill(['user_id' => $expectedId])->save();
                    $repaired++;
                }
            }
        });

        $this->newLine();
        $this->line(sprintf('Checked: %d', $checked));
        $this->line(sprintf('Mismatches: %d', $mismatches));
        $this->line(sprintf('Missing acctno: %d', $missingAcctno));
        $this->line(sprintf('Missing user: %d', $missingUser));

        if ($fix) {
            $this->line(sprintf('Repaired: %d', $repaired));
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
