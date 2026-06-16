<?php

namespace App\Console\Commands;

use App\LoanWorkflowPreflightStage;
use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Console\Command;

class LoanWorkflowDeploymentCheckCommand extends Command
{
    protected $signature = 'loan-workflow:deployment-check
        {--stage=pre-migration : Run preflight in pre-migration or post-migration mode}
        {--skip-smoke : Skip the non-destructive smoke-test checks}';

    protected $description = 'Run safe staged loan workflow deployment checks without applying migrations, repairs, or seed data.';

    public function handle(
        LoanWorkflowProductionSupportService $supportService,
    ): int {
        $stage = LoanWorkflowPreflightStage::parse(
            is_string($this->option('stage'))
                ? $this->option('stage')
                : null,
        );

        if (! $stage instanceof LoanWorkflowPreflightStage) {
            $this->error('The --stage option must be pre-migration or post-migration.');

            return self::FAILURE;
        }

        $report = $supportService->preflight($stage);

        $this->components->info(sprintf(
            'Loan workflow deployment check (%s)',
            $stage->label(),
        ));
        $this->renderIssues('Blocking', $report['blocking'] ?? []);
        $this->renderIssues('Warnings', $report['warnings'] ?? []);
        $this->renderIssues('Deferred', $report['deferred'] ?? []);
        $this->renderIssues('OK', $report['ok'] ?? []);

        $smokeFailed = false;
        $skipSmoke = (bool) $this->option('skip-smoke');

        if ($stage->isPreMigration() && ! $skipSmoke) {
            $this->newLine();
            $this->line(
                'Strict smoke checks are deferred until post-migration because the workflow tables and seeded permissions may not exist yet.',
            );
        } elseif (! $skipSmoke) {
            $smoke = $supportService->smokeTest();
            $this->newLine();
            $this->line('Smoke checks');
            $this->table(
                ['Check', 'Status', 'Summary'],
                array_map(
                    static fn (array $check): array => [
                        $check['name'] ?? '-',
                        $check['status'] ?? 'fail',
                        $check['summary'] ?? '-',
                    ],
                    $smoke['checks'] ?? [],
                ),
            );

            $smokeFailed = collect($smoke['checks'] ?? [])
                ->contains(
                    static fn (array $check): bool => ($check['status'] ?? 'fail') === 'fail',
                );
        }

        return ($report['blocking'] ?? []) !== [] || $smokeFailed
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
