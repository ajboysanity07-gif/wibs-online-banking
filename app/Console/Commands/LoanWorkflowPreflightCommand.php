<?php

namespace App\Console\Commands;

use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Console\Command;

class LoanWorkflowPreflightCommand extends Command
{
    protected $signature = 'loan-workflow:preflight';

    protected $description = 'Run non-destructive production readiness checks for the loan workflow.';

    public function handle(
        LoanWorkflowProductionSupportService $supportService,
    ): int {
        $report = $supportService->preflight();

        $this->components->info('Loan workflow preflight');
        $this->renderIssues('Blocking', $report['blocking'] ?? []);
        $this->renderIssues('Warnings', $report['warnings'] ?? []);
        $this->renderIssues('OK', $report['ok'] ?? []);

        return ($report['blocking'] ?? []) !== []
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function renderIssues(string $label, array $issues): void
    {
        $this->newLine();
        $this->line(sprintf('%s checks', $label));

        if ($issues === []) {
            $this->line('  None');

            return;
        }

        $this->table(
            ['Code', 'Summary', 'Count', 'References'],
            array_map(
                static fn (array $issue): array => [
                    $issue['code'] ?? '-',
                    $issue['summary'] ?? '-',
                    (string) ($issue['count'] ?? 0),
                    implode(', ', $issue['references'] ?? []),
                ],
                $issues,
            ),
        );
    }
}
