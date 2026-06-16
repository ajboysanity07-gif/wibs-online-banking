<?php

use App\LoanWorkflowPreflightStage;
use App\Services\LoanRequests\LoanWorkflowProductionSupportService;

it('defers strict smoke checks during the pre-migration deployment check', function (): void {
    $supportService = Mockery::mock(LoanWorkflowProductionSupportService::class);
    $supportService
        ->shouldReceive('preflight')
        ->once()
        ->with(LoanWorkflowPreflightStage::PreMigration)
        ->andReturn([
            'blocking' => [],
            'warnings' => [],
            'deferred' => [],
            'ok' => [],
        ]);
    $supportService->shouldNotReceive('smokeTest');

    app()->instance(LoanWorkflowProductionSupportService::class, $supportService);

    $this->artisan('loan-workflow:deployment-check', [
        '--stage' => 'pre-migration',
    ])
        ->expectsOutputToContain('Strict smoke checks are deferred until post-migration')
        ->assertSuccessful();
});

it('runs strict smoke checks during the post-migration deployment check', function (): void {
    $supportService = Mockery::mock(LoanWorkflowProductionSupportService::class);
    $supportService
        ->shouldReceive('preflight')
        ->once()
        ->with(LoanWorkflowPreflightStage::PostMigration)
        ->andReturn([
            'blocking' => [],
            'warnings' => [],
            'deferred' => [],
            'ok' => [],
        ]);
    $supportService
        ->shouldReceive('smokeTest')
        ->once()
        ->andReturn([
            'checks' => [
                [
                    'name' => 'database_connection',
                    'status' => 'pass',
                    'summary' => 'Database connectivity was verified.',
                ],
            ],
        ]);

    app()->instance(LoanWorkflowProductionSupportService::class, $supportService);

    $this->artisan('loan-workflow:deployment-check', [
        '--stage' => 'post-migration',
    ])->assertSuccessful();
});
