<?php

namespace App\Console\Commands;

use App\Models\LoanRequest;
use App\Services\LoanRequests\LoanRequestDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillHealthSmokingStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan-requests:backfill-health-fields
        {--fix : Apply the health_smoking_status backfill (default is dry run)}
        {--limit= : Limit the number of loan requests to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill health_smoking_status from retired boolean fields, and report loan requests needing manual review of the item 2e diabetes/kidney/liver/urinary split.';

    public function __construct(private readonly LoanRequestDataService $dataService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('loan_requests') || ! Schema::hasTable('loan_request_data_entries')) {
            $this->error('loan_requests or loan_request_data_entries table not found.');

            return self::FAILURE;
        }

        $fix = (bool) $this->option('fix');
        $limit = $this->normalizeLimit($this->option('limit'));

        $this->line($fix
            ? 'Fix mode enabled. Writing health_smoking_status where missing.'
            : 'Dry run. No writes will be applied.');

        $query = LoanRequest::query()
            ->with('dataEntries')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $checked = 0;
        $backfilled = 0;
        $alreadySet = 0;
        $reviewRows = [];

        $query->chunkById(200, function ($requests) use ($fix, &$checked, &$backfilled, &$alreadySet, &$reviewRows): void {
            foreach ($requests as $loanRequest) {
                $checked++;
                $flatValues = $this->dataService->loadFlatValues($loanRequest);

                if (array_key_exists('health_smoking_status', $flatValues) && $flatValues['health_smoking_status'] !== null) {
                    $alreadySet++;
                } else {
                    $glapiSmoker = $flatValues['gl_health_q11_smoker'] ?? null;
                    $legacySmoker = $flatValues['health_smoker'] ?? null;

                    $status = match (true) {
                        $glapiSmoker === true => 'heavy',
                        $legacySmoker === true => 'light',
                        default => 'none',
                    };

                    $sourceFieldKey = match (true) {
                        $glapiSmoker === true => 'gl_health_q11_smoker',
                        $legacySmoker === true => 'health_smoker',
                        default => null,
                    };

                    $confirmedByMember = $sourceFieldKey !== null
                        && $this->wasConfirmedByMember($loanRequest, $sourceFieldKey);

                    $this->line(sprintf(
                        'loan_request=%d gl_health_q11_smoker=%s health_smoker=%s -> health_smoking_status=%s (confirmed=%s)',
                        $loanRequest->id,
                        $this->formatBool($glapiSmoker),
                        $this->formatBool($legacySmoker),
                        $status,
                        $confirmedByMember ? 'true' : 'false',
                    ));

                    if ($fix) {
                        $this->dataService->backfillField(
                            $loanRequest,
                            'health_smoking_status',
                            $status,
                            confirmedByMember: $confirmedByMember,
                        );
                        $backfilled++;
                    }
                }

                $oldDiabetesRenal = $flatValues['gl_health_q02e_diabetes_renal'] ?? null;

                if ($oldDiabetesRenal === true) {
                    $reviewRows[] = [
                        'id' => $loanRequest->id,
                        'details' => $flatValues['gl_health_q02e_diabetes_renal_details'] ?? null,
                    ];
                }
            }
        });

        $this->newLine();
        $this->line(sprintf('Checked: %d', $checked));
        $this->line(sprintf('Already set: %d', $alreadySet));

        if ($fix) {
            $this->line(sprintf('Backfilled: %d', $backfilled));
        }

        if ($reviewRows !== []) {
            $this->newLine();
            $this->warn(sprintf(
                '%d loan request(s) need manual review for the item 2e diabetes/kidney/liver/urinary split (old boolean was true, condition(s) cannot be inferred automatically):',
                count($reviewRows),
            ));

            foreach ($reviewRows as $row) {
                $this->line(sprintf(
                    '  loan_request=%d details="%s"',
                    $row['id'],
                    $row['details'] ?? '(none)',
                ));
            }

            $this->line('Use the admin correction dialog to set gl_health_q02e_diabetes / _kidney / _liver / _urinary for each row above.');
        } else {
            $this->line('No item 2e rows require manual review.');
        }

        return self::SUCCESS;
    }

    private function wasConfirmedByMember(LoanRequest $loanRequest, string $fieldKey): bool
    {
        return $loanRequest->dataEntries
            ->contains(
                static fn ($entry): bool => $entry->field_key === $fieldKey && $entry->confirmed_by_member,
            );
    }

    private function formatBool(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        return $value ? 'true' : 'false';
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
