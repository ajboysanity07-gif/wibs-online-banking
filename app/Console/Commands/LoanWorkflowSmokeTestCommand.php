<?php

namespace App\Console\Commands;

use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Console\Command;

class LoanWorkflowSmokeTestCommand extends Command
{
    protected $signature = 'loan-workflow:smoke-test';

    protected $description = 'Run non-destructive smoke tests for loan workflow production dependencies.';

    public function handle(
        LoanWorkflowProductionSupportService $supportService,
    ): int {
        $result = $supportService->smokeTest();

        $this->table(
            ['Check', 'Status', 'Summary'],
            array_map(
                static fn (array $check): array => [
                    $check['name'] ?? '-',
                    $check['status'] ?? 'fail',
                    $check['summary'] ?? '-',
                ],
                $result['checks'] ?? [],
            ),
        );

        $failed = collect($result['checks'] ?? [])
            ->contains(
                static fn (array $check): bool => ($check['status'] ?? 'fail') === 'fail',
            );

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
