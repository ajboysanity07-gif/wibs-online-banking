<?php

namespace App\Console\Commands;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\Wmaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillZipCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan-requests:backfill-zip-codes
        {--fix : Apply the address_zip backfill from the member wmaster zone_number (default is dry run)}
        {--limit= : Limit the number of applicant rows to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill applicant address_zip from the member wmaster zone_number. Office (employer) ZIP cannot be backfilled for legacy rows because wmaster has no employer ZIP column.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('loan_request_people') || ! Schema::hasTable('wmaster')) {
            $this->error('loan_request_people or wmaster table not found.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('loan_request_people', 'address_zip')) {
            $this->error('loan_request_people has no address_zip column.');

            return self::FAILURE;
        }

        $fix = (bool) $this->option('fix');
        $limit = $this->normalizeLimit($this->option('limit'));

        $this->line($fix
            ? 'Fix mode enabled. Writing applicant address_zip where missing.'
            : 'Dry run. No writes will be applied.');

        $query = LoanRequestPerson::query()
            ->where('role', LoanRequestPersonRole::Applicant->value)
            ->where(fn ($q) => $q->whereNull('address_zip')->orWhere('address_zip', ''))
            ->with(['loanRequest.user.wmaster'])
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $checked = 0;
        $backfilled = 0;
        $alreadySet = 0;
        $noWmaster = 0;
        $noZoneNumber = 0;

        $query->chunkById(200, function ($people) use ($fix, &$checked, &$backfilled, &$alreadySet, &$noWmaster, &$noZoneNumber): void {
            foreach ($people as $person) {
                $checked++;

                $loanRequest = $person->loanRequest;

                if (! $loanRequest instanceof LoanRequest) {
                    $noWmaster++;

                    continue;
                }

                $memberRecord = $this->resolveWmaster($loanRequest);

                if (! $memberRecord instanceof Wmaster) {
                    $noWmaster++;
                    $this->line(sprintf(
                        'applicant=%d loan_request=%d no wmaster record',
                        $person->id,
                        $loanRequest->id,
                    ));

                    continue;
                }

                $zoneNumber = trim((string) $memberRecord->zone_number);

                if ($zoneNumber === '') {
                    $noZoneNumber++;
                    $this->line(sprintf(
                        'applicant=%d loan_request=%d wmaster=%s has no zone_number',
                        $person->id,
                        $loanRequest->id,
                        $memberRecord->getKey(),
                    ));

                    continue;
                }

                if (trim((string) $person->address_zip) !== '') {
                    $alreadySet++;

                    continue;
                }

                $this->line(sprintf(
                    'applicant=%d loan_request=%d wmaster=%s -> address_zip=%s',
                    $person->id,
                    $loanRequest->id,
                    $memberRecord->getKey(),
                    $zoneNumber,
                ));

                if ($fix) {
                    $person->address_zip = $zoneNumber;
                    $person->save();
                    $backfilled++;
                }
            }
        });

        $this->newLine();
        $this->line(sprintf('Checked: %d', $checked));
        $this->line(sprintf('Already set: %d', $alreadySet));
        $this->line(sprintf('No wmaster record: %d', $noWmaster));
        $this->line(sprintf('No zone_number: %d', $noZoneNumber));

        if ($fix) {
            $this->line(sprintf('Backfilled: %d', $backfilled));
        }

        $this->line('Office ZIP (employer) cannot be backfilled: no source column exists for legacy rows.');

        return self::SUCCESS;
    }

    private function resolveWmaster(LoanRequest $loanRequest): ?Wmaster
    {
        $loanRequest->loadMissing('user.wmaster');

        $user = $loanRequest->user;

        if ($user !== null && $user->wmaster instanceof Wmaster) {
            return $user->wmaster;
        }

        $acctno = trim((string) ($loanRequest->acctno ?? $user?->acctno ?? ''));

        if ($acctno === '') {
            return null;
        }

        return Wmaster::query()->where('acctno', $acctno)->first();
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
