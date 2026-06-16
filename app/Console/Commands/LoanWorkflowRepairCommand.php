<?php

namespace App\Console\Commands;

use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Console\Command;
use RuntimeException;

class LoanWorkflowRepairCommand extends Command
{
    protected $signature = 'loan-workflow:repair
        {--apply : Apply deterministic repairs}
        {--chunk=200 : Chunk size for repair scans}
        {--actor-user-id= : Staff user id to attribute assignment-release repairs to}';

    protected $description = 'Dry-run or apply deterministic loan workflow production repairs.';

    public function handle(
        LoanWorkflowProductionSupportService $supportService,
    ): int {
        $apply = (bool) $this->option('apply');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $actorUserId = $this->option('actor-user-id');

        $this->line($apply
            ? 'Apply mode enabled.'
            : 'Dry run only. No data will be modified.');

        try {
            $result = $supportService->repair(
                $apply,
                $chunkSize,
                is_numeric($actorUserId) ? (int) $actorUserId : null,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Repair', 'Count', 'References'],
            array_map(
                static fn (array $repair): array => [
                    $repair['type'] ?? '-',
                    (string) ($repair['count'] ?? 0),
                    implode(', ', $repair['references'] ?? []),
                ],
                $result['repairs'] ?? [],
            ),
        );

        if (($result['warnings'] ?? []) !== []) {
            $this->newLine();
            $this->table(
                ['Warning', 'Count', 'References'],
                array_map(
                    static fn (array $warning): array => [
                        $warning['summary'] ?? '-',
                        (string) ($warning['count'] ?? 0),
                        implode(', ', $warning['references'] ?? []),
                    ],
                    $result['warnings'],
                ),
            );
        }

        return self::SUCCESS;
    }
}
