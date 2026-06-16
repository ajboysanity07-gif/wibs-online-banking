<?php

namespace App\Console\Commands;

use App\Services\LoanRequests\LoanWorkflowPermissionSeedService;
use Illuminate\Console\Command;
use RuntimeException;

class LoanWorkflowSeedPermissionsCommand extends Command
{
    protected $signature = 'loan-workflow:seed-permissions
        {--dry-run : Report workflow role, permission, and mapping changes without committing them}';

    protected $description = 'Seed only the loan workflow roles, permissions, mappings, and legacy user-role backfills.';

    public function handle(
        LoanWorkflowPermissionSeedService $permissionSeedService,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $this->line($dryRun
            ? 'Dry run only. No workflow RBAC changes will be committed.'
            : 'Applying workflow RBAC seed changes.');

        try {
            $report = $permissionSeedService->seed($dryRun);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->renderSection('Conflicts', $report['conflicts'] ?? []);
        $this->renderSection('Created', $report['created'] ?? []);
        $this->renderSection('Updated', $report['updated'] ?? []);
        $this->renderSection('Unchanged', $report['unchanged'] ?? []);

        return ($report['conflicts'] ?? []) !== []
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function renderSection(string $label, array $issues): void
    {
        $this->line($label);

        if ($issues === []) {
            $this->line('  None');
            $this->newLine();

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
        $this->newLine();
    }
}
