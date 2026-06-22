<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\LoanWorkflowPreflightStage;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Support\SchemaCapabilities;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LoanWorkflowProductionSupportService
{
    public function __construct(
        private SchemaCapabilities $schemaCapabilities,
        private LoanRequestAssignmentService $assignmentService,
        private LoanRequestDocumentCatalog $documentCatalog,
        private LoanRequestDocumentStorage $documentStorage,
        private LoanRequestDocumentWorkflowService $documentWorkflowService,
        private LoanRequestNotificationService $notificationService,
    ) {}

    /**
     * @return array{
     *     blocking:list<array<string, mixed>>,
     *     warnings:list<array<string, mixed>>,
     *     deferred:list<array<string, mixed>>,
     *     ok:list<array<string, mixed>>
     * }
     */
    public function preflight(
        LoanWorkflowPreflightStage $stage = LoanWorkflowPreflightStage::PostMigration,
    ): array {
        $report = [
            'blocking' => [],
            'warnings' => [],
            'deferred' => [],
            'ok' => [],
        ];

        $databaseConnection = $this->databaseConnectionIssue();

        if ($databaseConnection !== null) {
            $report['blocking'][] = $databaseConnection;

            foreach ($this->queueConfigurationIssues($stage) as $severity => $issues) {
                $report[$severity] = array_merge($report[$severity], $issues);
            }

            foreach ($this->deliveryConfigurationIssues($stage) as $severity => $issues) {
                $report[$severity] = array_merge($report[$severity], $issues);
            }

            return $report;
        }

        $report['ok'][] = $this->issue(
            'database_connection',
            'Database connectivity was verified.',
            1,
        );

        foreach ($this->migrationIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->storageBackupConfigurationIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->postMigrationSchemaIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->workflowPermissionIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->loanRequestDataIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->roleAssignmentIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->documentIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->wibsIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->notificationIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->queueConfigurationIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->deliveryConfigurationIssues($stage) as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        return $report;
    }

    /**
     * @return array{
     *     dry_run:bool,
     *     repairs:list<array<string, mixed>>,
     *     warnings:list<array<string, mixed>>
     * }
     */
    public function repair(
        bool $apply = false,
        int $chunkSize = 200,
        ?int $actorUserId = null,
    ): array {
        $chunkSize = $chunkSize > 0 ? $chunkSize : 200;
        $repairs = [];
        $warnings = [];
        $actor = $apply && $actorUserId !== null
            ? AppUser::query()->find($actorUserId)
            : null;

        if ($apply && $actorUserId !== null && ! $actor instanceof AppUser) {
            throw new RuntimeException('The selected repair actor was not found.');
        }

        if ($this->schemaCapabilities->hasTable('loan_requests')) {
            $repairs = array_merge($repairs, $this->repairLoanRequests(
                $apply,
                $chunkSize,
            ));
        }

        if ($this->schemaCapabilities->hasTable('loan_request_documents')) {
            $repairs = array_merge($repairs, $this->repairGeneratedDocuments(
                $apply,
                $chunkSize,
            ));
        }

        if ($this->schemaCapabilities->hasTable('loan_requests')) {
            $repairs = array_merge($repairs, $this->repairDocumentChecklists(
                $apply,
                $chunkSize,
            ));
        }

        if ($this->schemaCapabilities->hasTable('loan_requests')) {
            $inactiveAssignments = $this->inactiveAssignmentCandidates();

            if ($inactiveAssignments !== [] && ! $actor instanceof AppUser) {
                $warnings[] = $this->issue(
                    'inactive_staff_assignments',
                    'Inactive staff assignments were detected but were not released because no repair actor was provided.',
                    count($inactiveAssignments),
                    array_map(
                        static fn (array $item): string => $item['reference'],
                        $inactiveAssignments,
                    ),
                );
            } elseif ($inactiveAssignments !== [] && $actor instanceof AppUser) {
                $repairs[] = $this->repairInactiveAssignments(
                    $inactiveAssignments,
                    $actor,
                    $apply,
                );
            }
        }

        return [
            'dry_run' => ! $apply,
            'repairs' => array_values(array_filter(
                $repairs,
                static fn (array $repair): bool => ($repair['count'] ?? 0) > 0,
            )),
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{
     *     checks:list<array<string, mixed>>
     * }
     */
    public function smokeTest(): array
    {
        $checks = [];

        $databaseConnectionIssue = $this->databaseConnectionIssue();
        $checks[] = $databaseConnectionIssue === null
            ? $this->smokeCheck('database_connection', 'pass', 'Database connectivity was verified.')
            : $this->smokeCheck(
                'database_connection',
                'fail',
                (string) ($databaseConnectionIssue['summary'] ?? 'Database connectivity could not be verified.'),
            );
        $checks[] = $this->smokeCheck(
            'loan_requests_table',
            $this->schemaCapabilities->hasTable('loan_requests') ? 'pass' : 'fail',
            $this->schemaCapabilities->hasTable('loan_requests')
                ? 'loan_requests table is present.'
                : 'loan_requests table is missing.',
        );
        $checks[] = $this->smokeCheck(
            'loan_request_documents_table',
            $this->schemaCapabilities->hasTable('loan_request_documents') ? 'pass' : 'fail',
            $this->schemaCapabilities->hasTable('loan_request_documents')
                ? 'loan_request_documents table is present.'
                : 'loan_request_documents table is missing.',
        );
        $checks[] = $this->smokeCheck(
            'loan_request_notification_events_table',
            $this->schemaCapabilities->hasTable('loan_request_notification_events') ? 'pass' : 'fail',
            $this->schemaCapabilities->hasTable('loan_request_notification_events')
                ? 'loan_request_notification_events table is present.'
                : 'loan_request_notification_events table is missing.',
        );
        $checks[] = $this->storageRoundTripSmokeCheck();
        $checks[] = $this->smokeCheck(
            'document_templates_present',
            $this->documentCatalog->templateAvailabilityIssues() === [] ? 'pass' : 'fail',
            $this->documentCatalog->templateAvailabilityIssues() === []
                ? 'All required workflow templates are present.'
                : 'One or more required workflow templates are missing.',
        );
        $checks[] = $this->smokeCheck(
            'workflow_action_route_registered',
            Route::has('loan-requests.action') ? 'pass' : 'fail',
            Route::has('loan-requests.action')
                ? 'The workflow action route is registered.'
                : 'The workflow action route is missing.',
        );
        $checks[] = $this->smokeCheck(
            'workflow_permissions_seeded',
            $this->workflowPermissionsSeeded() ? 'pass' : 'fail',
            $this->workflowPermissionsSeeded()
                ? 'Workflow roles, permissions, and mappings are seeded.'
                : 'Workflow roles, permissions, or mappings are missing.',
        );

        foreach ($this->queueSmokeChecks() as $check) {
            $checks[] = $check;
        }

        foreach ($this->deliverySmokeChecks() as $check) {
            $checks[] = $check;
        }

        return ['checks' => $checks];
    }

    private function storageRoundTripSmokeCheck(): array
    {
        return $this->smokeCheckCallback('private_storage_round_trip', function (): array {
            $path = sprintf(
                '%s/smoke-%s.txt',
                $this->documentStorage->temporaryDirectory(),
                now()->format('YmdHisv'),
            );
            $disk = $this->documentStorage->documentDisk();

            Storage::disk($disk)->put($path, 'ok');
            $exists = Storage::disk($disk)->exists($path);
            $contents = $exists ? Storage::disk($disk)->get($path) : null;
            Storage::disk($disk)->delete($path);

            return [
                'status' => $exists && $contents === 'ok' && ! Storage::disk($disk)->exists($path)
                    ? 'pass'
                    : 'fail',
                'summary' => $exists && $contents === 'ok' && ! Storage::disk($disk)->exists($path)
                    ? 'Private storage write, read, and delete round-trip succeeded.'
                    : 'Private storage round-trip failed.',
            ];
        });
    }

    /**
     * @return array{requested:int, queued:int}
     */
    public function sendDueReminders(): array
    {
        if (! $this->schemaCapabilities->hasTable('loan_requests')) {
            return ['requested' => 0, 'queued' => 0];
        }

        $requested = 0;
        $queued = 0;

        LoanRequest::query()
            ->whereIn('status', [
                LoanRequestStatus::NeedsRevision->value,
                LoanRequestStatus::AwaitingMemberInformation->value,
                LoanRequestStatus::AwaitingMemberAcceptance->value,
            ])
            ->orderBy('id')
            ->chunkById(100, function (Collection $requests) use (
                &$requested,
                &$queued,
            ): void {
                foreach ($requests as $loanRequest) {
                    $eventType = match ($loanRequest->status instanceof LoanRequestStatus
                        ? $loanRequest->status->value
                        : (string) $loanRequest->status) {
                        LoanRequestStatus::NeedsRevision->value => LoanRequestNotificationService::EVENT_AWAITING_MEMBER_CORRECTION,
                        LoanRequestStatus::AwaitingMemberInformation->value => LoanRequestNotificationService::EVENT_AWAITING_MEMBER_INFORMATION,
                        LoanRequestStatus::AwaitingMemberAcceptance->value => LoanRequestNotificationService::EVENT_AWAITING_MEMBER_ACCEPTANCE,
                        default => null,
                    };

                    if ($eventType === null) {
                        continue;
                    }

                    $requested++;

                    if ($this->notificationService->sendReminderIfDue(
                        $loanRequest,
                        $eventType,
                    )) {
                        $queued++;
                    }
                }
            });

        return ['requested' => $requested, 'queued' => $queued];
    }

    /**
     * @return array{candidates:int, deleted:int}
     */
    public function cleanupTemporaryFiles(bool $apply = true): array
    {
        $threshold = CarbonImmutable::now()->subHours(
            $this->documentStorage->temporaryRetentionHours(),
        );
        $candidates = $this->documentStorage->temporaryPathsOlderThan($threshold);
        $deleted = 0;

        if ($apply) {
            foreach ($candidates as $candidate) {
                if (File::delete($candidate['absolute_path'])) {
                    $deleted++;
                }
            }

            $temporaryRoot = $this->documentStorage->absolutePath(
                $this->documentStorage->temporaryDirectory(),
                $this->documentStorage->documentDisk(),
                [$this->documentStorage->temporaryDirectory()],
            );

            if (File::isDirectory($temporaryRoot)) {
                foreach (File::directories($temporaryRoot) as $directory) {
                    if (File::isEmptyDirectory($directory)) {
                        File::deleteDirectory($directory);
                    }
                }
            }
        }

        return [
            'candidates' => count($candidates),
            'deleted' => $deleted,
        ];
    }

    private function databaseConnectionIssue(): ?array
    {
        try {
            DB::connection()->getPdo();

            return null;
        } catch (Throwable $throwable) {
            return $this->issue(
                'database_connection',
                'Database connectivity could not be verified.',
                1,
                [mb_substr($throwable->getMessage(), 0, 160)],
            );
        }
    }

    /**
     * @return array{
     *     repository:list<string>,
     *     pending:list<string>,
     *     ran:list<string>,
     *     unknown:list<string>,
     *     migrations_table_exists:bool
     * }
     */
    private function migrationState(): array
    {
        $repositoryMigrations = $this->repositoryMigrationNames();

        if (! $this->schemaCapabilities->hasTable('migrations')) {
            return [
                'repository' => $repositoryMigrations,
                'pending' => $repositoryMigrations,
                'ran' => [],
                'unknown' => [],
                'migrations_table_exists' => false,
            ];
        }

        $ranMigrations = DB::table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        return [
            'repository' => $repositoryMigrations,
            'pending' => array_values(array_diff($repositoryMigrations, $ranMigrations)),
            'ran' => $ranMigrations,
            'unknown' => array_values(array_diff($ranMigrations, $repositoryMigrations)),
            'migrations_table_exists' => true,
        ];
    }

    /**
     * @return list<string>
     */
    private function repositoryMigrationNames(): array
    {
        return collect(File::files(database_path('migrations')))
            ->map(
                fn (\SplFileInfo $file): string => pathinfo(
                    $file->getFilename(),
                    PATHINFO_FILENAME,
                ),
            )
            ->sort()
            ->values()
            ->all();
    }

    private function hasExistingApplicationSchema(): bool
    {
        foreach ([
            'appusers',
            'admin_profiles',
            'loan_requests',
            'roles',
            'permissions',
        ] as $table) {
            if ($this->schemaCapabilities->hasTable($table)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function migrationIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];
        $migrationState = $this->migrationState();

        if (! $migrationState['migrations_table_exists']) {
            if ($stage->isPreMigration() && ! $this->hasExistingApplicationSchema()) {
                $issues['warnings'][] = $this->issue(
                    'migration_repository_missing',
                    'The migrations table is missing, so repository migrations are treated as pending on this schema.',
                    count($migrationState['pending']),
                    array_slice($migrationState['pending'], 0, 20),
                );
            } else {
                $issues['blocking'][] = $this->issue(
                    'unknown_migration_state',
                    'The migrations table is missing, so migration state cannot be verified safely.',
                    1,
                );
            }
        }

        if ($migrationState['unknown'] !== []) {
            $issues['blocking'][] = $this->issue(
                'unknown_repository_migrations',
                'Migration history contains entries that are not present in this repository checkout.',
                count($migrationState['unknown']),
                $migrationState['unknown'],
            );
        }

        if ($migrationState['pending'] !== []) {
            $issues[$stage->isPreMigration() ? 'warnings' : 'blocking'][] = $this->issue(
                'pending_migrations',
                $stage->isPreMigration()
                    ? 'Pending repository migrations were detected. Review them before running migrate --force.'
                    : 'Pending migrations must be applied before deployment can continue.',
                count($migrationState['pending']),
                $migrationState['pending'],
            );
        } else {
            $issues['ok'][] = $this->issue(
                'pending_migrations',
                'No pending migrations detected.',
                0,
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function storageBackupConfigurationIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];

        try {
            $documentDisk = $this->documentStorage->documentDisk();
            $documentDirectory = $this->documentStorage->documentDirectory();
            $temporaryDirectory = $this->documentStorage->temporaryDirectory();
            $diskDriver = trim((string) config(
                "filesystems.disks.{$documentDisk}.driver",
                '',
            ));

            if ($diskDriver === '') {
                $issues['blocking'][] = $this->issue(
                    'document_storage_configuration',
                    'The generated-document disk is not configured.',
                    1,
                    [$documentDisk],
                );

                return $issues;
            }

            $this->documentStorage->ensureSafeRelativePath(
                $documentDirectory.'/verification.txt',
                [$documentDirectory],
            );
            $this->documentStorage->ensureSafeRelativePath(
                $temporaryDirectory.'/verification.txt',
                [$temporaryDirectory],
            );

            $references = [
                'disk:'.$documentDisk,
                'driver:'.$diskDriver,
                'documents:'.$documentDirectory,
                'temp:'.$temporaryDirectory,
            ];

            if ($diskDriver === 'local') {
                $references[] = 'path:'.storage_path('app');
            }

            $issues['ok'][] = $this->issue(
                'document_storage_backup_scope',
                'Generated-document storage backup scope can be resolved from configuration.',
                1,
                $references,
            );
        } catch (Throwable $throwable) {
            $issues['blocking'][] = $this->issue(
                'document_storage_configuration',
                'Generated-document backup scope could not be verified from configuration.',
                1,
                [mb_substr($throwable->getMessage(), 0, 160)],
            );
        }

        if ($stage->isPreMigration()) {
            $issues['ok'][] = $this->issue(
                'backup_procedure_checkpoint',
                'Pre-migration mode verified the configuration needed to review database and private-storage backups before deployment.',
                1,
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function postMigrationSchemaIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];
        $missingArtifacts = [];

        foreach ([
            ['table', 'staff_access_controls', 'staff access controls'],
            ['table', 'user_role_changes', 'user role change audit'],
            ['table', 'loan_request_data_changes', 'loan request data change audit'],
            ['table', 'loan_request_data_entries', 'loan request data entries'],
            ['table', 'loan_request_documents', 'loan request documents'],
            ['table', 'loan_request_notification_events', 'loan request notification events'],
            ['table', 'loan_workflow_repairs', 'loan workflow repairs'],
            ['column', 'loan_requests.workflow_version', 'loan_requests.workflow_version'],
            ['column', 'loan_request_documents.document_key', 'loan_request_documents.document_key'],
        ] as [$type, $artifact, $label]) {
            $exists = $type === 'table'
                ? $this->schemaCapabilities->hasTable($artifact)
                : $this->schemaCapabilities->hasColumn(
                    explode('.', $artifact)[0],
                    explode('.', $artifact)[1],
                );

            if ($exists) {
                continue;
            }

            $missingArtifacts[] = $label;
        }

        if ($missingArtifacts === []) {
            $issues['ok'][] = $this->issue(
                'workflow_schema_ready',
                'The workflow schema required for post-migration validation is present.',
                1,
            );

            return $issues;
        }

        $issues[$stage->isPreMigration() ? 'deferred' : 'blocking'][] = $this->issue(
            'workflow_schema_artifacts',
            $stage->isPreMigration()
                ? 'Post-migration schema checks are deferred until the Phase 5-7 tables and columns are available.'
                : 'Required workflow schema artifacts are missing after migration.',
            count($missingArtifacts),
            $missingArtifacts,
        );

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function workflowPermissionIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];

        if (
            ! $this->schemaCapabilities->hasTable('roles')
            || ! $this->schemaCapabilities->hasTable('permissions')
            || ! $this->schemaCapabilities->hasTable('role_permissions')
        ) {
            $issues['blocking'][] = $this->issue(
                'workflow_permission_schema',
                'Workflow RBAC tables are missing.',
                1,
            );

            return $issues;
        }

        if ($this->workflowPermissionsSeeded($stage->isPreMigration())) {
            $issues['ok'][] = $this->issue(
                'workflow_permissions_seeded',
                'Workflow roles, permissions, and role mappings are present.',
                1,
            );

            return $issues;
        }

        $issues[$stage->isPreMigration() ? 'warnings' : 'blocking'][] = $this->issue(
            'workflow_permissions_seeded',
            $stage->isPreMigration()
                ? 'Workflow roles, permissions, or role mappings are not fully seeded yet. Run loan-workflow:seed-permissions after migration.'
                : 'Workflow roles, permissions, or role mappings are missing.',
            1,
        );

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function loanRequestDataIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];

        if (! $this->schemaCapabilities->hasTable('loan_requests')) {
            $issues['blocking'][] = $this->issue(
                'loan_requests_table',
                'The loan_requests table is missing.',
                1,
            );

            return $issues;
        }

        $knownStatuses = array_merge(
            LoanRequestStatus::requestFilterValues(),
            [LoanRequestStatus::PendingCoMakerSignatures->value],
        );
        $unknownStatuses = LoanRequest::query()
            ->whereNotNull('status')
            ->whereNotIn('status', $knownStatuses)
            ->pluck('id');

        if ($unknownStatuses->isNotEmpty()) {
            $issues['blocking'][] = $this->issue(
                'unknown_request_statuses',
                'Loan requests with unknown statuses were found.',
                $unknownStatuses->count(),
                $this->loanRequestReferences($unknownStatuses->all()),
            );
        }

        if ($this->schemaCapabilities->hasColumn('loan_requests', 'workflow_version')) {
            $missingWorkflowVersionIds = LoanRequest::query()
                ->where(function ($query): void {
                    $query
                        ->whereNull('workflow_version')
                        ->orWhere('workflow_version', '');
                })
                ->pluck('id');

            if ($missingWorkflowVersionIds->isNotEmpty()) {
                $issues['blocking'][] = $this->issue(
                    'missing_workflow_versions',
                    'Loan requests missing workflow versions were found.',
                    $missingWorkflowVersionIds->count(),
                    $this->loanRequestReferences($missingWorkflowVersionIds->all()),
                );
            }
        } else {
            $issues[$stage->isPreMigration() ? 'deferred' : 'blocking'][] = $this->issue(
                'workflow_version_validation',
                $stage->isPreMigration()
                    ? 'Workflow-version validation is deferred until the Phase 5 workflow_version column exists.'
                    : 'The workflow_version column is missing from loan_requests.',
                1,
            );
        }

        $legacyStatusIds = LoanRequest::query()
            ->where('status', LoanRequestStatus::PendingCoMakerSignatures->value)
            ->pluck('id');

        if ($legacyStatusIds->isNotEmpty()) {
            $issues[$stage->isPreMigration() ? 'warnings' : 'blocking'][] = $this->issue(
                'active_legacy_co_maker_statuses',
                $stage->isPreMigration()
                    ? 'Active pending_co_maker_signatures rows were found. They must be normalized through loan-workflow:repair after migration.'
                    : 'Legacy pending co-maker signature statuses are still active.',
                $legacyStatusIds->count(),
                $this->loanRequestReferences($legacyStatusIds->all()),
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function roleAssignmentIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];

        if ($this->schemaCapabilities->hasTable('user_roles')) {
            $invalidAssignments = DB::table('user_roles as user_roles')
                ->leftJoin('appusers', 'appusers.user_id', '=', 'user_roles.user_id')
                ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where(function ($query): void {
                    $query
                        ->whereNull('appusers.user_id')
                        ->orWhereNull('roles.id');
                })
                ->select([
                    'user_roles.user_id',
                    'user_roles.role_id',
                    'roles.name as role_name',
                ])
                ->get();

            if ($invalidAssignments->isNotEmpty()) {
                $issues['blocking'][] = $this->issue(
                    'invalid_role_assignments',
                    'Invalid user role assignments were found.',
                    $invalidAssignments->count(),
                    $invalidAssignments->map(
                        static fn (object $item): string => sprintf(
                            'user:%d role:%s',
                            (int) $item->user_id,
                            trim((string) ($item->role_name ?? 'missing')),
                        ),
                    )->all(),
                );
            }
        }

        if (! $this->schemaCapabilities->hasTable('staff_access_controls')) {
            $issues[$stage->isPreMigration() ? 'deferred' : 'blocking'][] = $this->issue(
                'inactive_staff_assignment_validation',
                $stage->isPreMigration()
                    ? 'Inactive staff-assignment validation is deferred until staff_access_controls exists.'
                    : 'The staff_access_controls table is missing.',
                1,
            );

            return $issues;
        }

        $inactiveAssignments = $this->inactiveAssignmentCandidates();

        if ($inactiveAssignments !== []) {
            $issues['blocking'][] = $this->issue(
                'inactive_staff_assignments',
                'Loan requests are still assigned to inactive or ineligible staff.',
                count($inactiveAssignments),
                array_map(
                    static fn (array $item): string => $item['reference'],
                    $inactiveAssignments,
                ),
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function documentIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];

        if (
            ! $this->schemaCapabilities->hasTable('loan_request_documents')
            || ! $this->schemaCapabilities->hasColumn('loan_request_documents', 'document_key')
        ) {
            $issues[$stage->isPreMigration() ? 'deferred' : 'blocking'][] = $this->issue(
                'document_integrity_validation',
                $stage->isPreMigration()
                    ? 'Document integrity checks are deferred until loan_request_documents and document_key are available.'
                    : 'The workflow document tables or columns required for document integrity validation are missing.',
                1,
            );

            return $issues;
        }

        if (! $this->schemaCapabilities->hasColumn('loan_requests', 'workflow_version')) {
            $issues[$stage->isPreMigration() ? 'deferred' : 'blocking'][] = $this->issue(
                'document_workflow_version_validation',
                $stage->isPreMigration()
                    ? 'Document gate checks are deferred until loan_requests.workflow_version is available.'
                    : 'The workflow_version column is required for strict document-gate validation.',
                1,
            );

            return $issues;
        }

        $duplicateDocumentKeys = DB::table('loan_request_documents')
            ->select([
                'loan_request_id',
                'document_key',
                DB::raw('COUNT(*) as duplicate_count'),
            ])
            ->whereNotNull('document_key')
            ->groupBy(['loan_request_id', 'document_key'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateDocumentKeys->isNotEmpty()) {
            $issues['blocking'][] = $this->issue(
                'duplicate_document_keys',
                'Duplicate (loan_request_id, document_key) pairs exist in loan_request_documents. The unique index added by Phase 7 will silently fail unless these are resolved before migration.',
                $duplicateDocumentKeys->count(),
                $duplicateDocumentKeys->map(fn (object $item): string => sprintf(
                    'request:%d %s (%dx)',
                    (int) $item->loan_request_id,
                    trim((string) $item->document_key),
                    (int) $item->duplicate_count,
                ))->all(),
            );
        }

        $missingFiles = [];

        LoanRequestDocument::query()
            ->with('loanRequest')
            ->whereNotNull('generated_path')
            ->where('generated_path', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function (Collection $documents) use (&$missingFiles): void {
                foreach ($documents as $document) {
                    $path = trim((string) $document->generated_path);

                    if ($path === '') {
                        continue;
                    }

                    if ($this->documentStorage->fileExists(
                        $path,
                        $document->generated_disk ?: null,
                    )) {
                        continue;
                    }

                    if ($this->documentStorage->legacyFileExists($path)) {
                        $missingFiles[] = sprintf(
                            '%s %s (legacy root)',
                            $document->loanRequest?->reference ?? 'request',
                            $document->document_key,
                        );

                        continue;
                    }

                    $missingFiles[] = sprintf(
                        '%s %s',
                        $document->loanRequest?->reference ?? 'request',
                        $document->document_key,
                    );
                }
            });

        if ($missingFiles !== []) {
            $issues['blocking'][] = $this->issue(
                'missing_generated_files',
                'Generated loan-request document files are missing from storage.',
                count($missingFiles),
                $missingFiles,
            );
        }

        $mismatchedCurrentDocuments = [];
        $missingChecklistRows = [];
        $incompleteV2DecisionRequests = [];

        LoanRequest::query()
            ->with(['documents', 'dataEntries', 'people', 'user'])
            ->where(function ($query): void {
                $query
                    ->where('workflow_version', LoanRequestWorkflowVersion::DocumentWorkflowV2->value)
                    ->orWhere(function ($nested): void {
                        $nested
                            ->whereNull('workflow_version')
                            ->orWhere('workflow_version', '');
                    });
            })
            ->orderBy('id')
            ->chunkById(50, function (Collection $loanRequests) use (
                &$mismatchedCurrentDocuments,
                &$missingChecklistRows,
                &$incompleteV2DecisionRequests,
            ): void {
                foreach ($loanRequests as $loanRequest) {
                    $inspection = $this->documentWorkflowService->inspectChecklist(
                        $loanRequest,
                    );

                    foreach ($inspection as $item) {
                        $documentKey = $item['document_key'];
                        $storedDocument = $item['document'];
                        $expectedFill = $item['fill'];

                        if (! $storedDocument instanceof LoanRequestDocument) {
                            $missingChecklistRows[] = sprintf(
                                '%s %s',
                                $loanRequest->reference,
                                $documentKey->value,
                            );

                            continue;
                        }

                        if (
                            $storedDocument->readiness_status === LoanRequestDocumentReadinessStatus::GeneratedCurrent
                            && $storedDocument->source_hash !== ($expectedFill['source_hash'] ?? null)
                        ) {
                            $mismatchedCurrentDocuments[] = sprintf(
                                '%s %s',
                                $loanRequest->reference,
                                $documentKey->value,
                            );
                        }
                    }

                    if (
                        $this->workflowVersionValue($loanRequest) === LoanRequestWorkflowVersion::DocumentWorkflowV2->value
                        && in_array(
                            $loanRequest->status instanceof LoanRequestStatus
                                ? $loanRequest->status->value
                                : (string) $loanRequest->status,
                            [
                                LoanRequestStatus::RecommendedForApproval->value,
                                LoanRequestStatus::AwaitingMemberAcceptance->value,
                                LoanRequestStatus::Approved->value,
                                LoanRequestStatus::ConvertedToLoan->value,
                            ],
                            true,
                        )
                    ) {
                        $hasIncompleteDocuments = $inspection->contains(
                            function (array $item): bool {
                                $storedDocument = $item['document'];
                                $expectedFill = $item['fill'];

                                return ($expectedFill['is_applicable'] ?? false) === true
                                    && (
                                        ! $storedDocument instanceof LoanRequestDocument
                                        || $storedDocument->readiness_status !== LoanRequestDocumentReadinessStatus::GeneratedCurrent
                                    );
                            },
                        );

                        if ($hasIncompleteDocuments) {
                            $incompleteV2DecisionRequests[] = $loanRequest->reference;
                        }
                    }
                }
            });

        if ($missingChecklistRows !== []) {
            $issues['blocking'][] = $this->issue(
                'missing_document_checklist_rows',
                'Loan requests are missing expected document checklist rows.',
                count($missingChecklistRows),
                $missingChecklistRows,
            );
        }

        if ($mismatchedCurrentDocuments !== []) {
            $issues['blocking'][] = $this->issue(
                'mismatched_current_document_hashes',
                'Some documents are marked current even though their source hashes no longer match.',
                count($mismatchedCurrentDocuments),
                $mismatchedCurrentDocuments,
            );
        }

        if ($incompleteV2DecisionRequests !== []) {
            $issues['blocking'][] = $this->issue(
                'incomplete_v2_decision_documents',
                'V2 manager-review or approved requests still have incomplete or stale documents.',
                count($incompleteV2DecisionRequests),
                $incompleteV2DecisionRequests,
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function notificationIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];

        if (! $this->schemaCapabilities->hasTable('loan_request_notification_events')) {
            $issues[$stage->isPreMigration() ? 'deferred' : 'blocking'][] = $this->issue(
                'notification_event_validation',
                $stage->isPreMigration()
                    ? 'Notification deduplication checks are deferred until loan_request_notification_events exists.'
                    : 'The loan_request_notification_events table is missing.',
                1,
            );

            return $issues;
        }

        $duplicates = DB::table('loan_request_notification_events')
            ->select([
                'loan_request_id',
                'event_type',
                'channel',
                'recipient',
                DB::raw('COUNT(*) as duplicate_count'),
            ])
            ->groupBy([
                'loan_request_id',
                'event_type',
                'channel',
                'recipient',
            ])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $issues['blocking'][] = $this->issue(
                'duplicate_notification_events',
                'Duplicate loan workflow notification events were found.',
                $duplicates->count(),
                $duplicates->map(fn (object $item): string => sprintf(
                    'request:%d %s/%s',
                    (int) $item->loan_request_id,
                    trim((string) $item->event_type),
                    trim((string) $item->channel),
                ))->all(),
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function queueConfigurationIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];
        $defaultConnection = trim((string) config('queue.default'));

        if ($defaultConnection === '' || in_array($defaultConnection, [
            'sync',
            'deferred',
            'background',
        ], true)) {
            $issues['warnings'][] = $this->issue(
                'queue_connection',
                'Queue processing is not configured for a production-safe asynchronous connection.',
                1,
                [$defaultConnection !== '' ? $defaultConnection : 'missing'],
            );
        } else {
            $issues['ok'][] = $this->issue(
                'queue_connection',
                'The active queue connection is configured for asynchronous processing.',
                1,
                [$defaultConnection],
            );
        }

        $afterCommit = (bool) config(
            sprintf('queue.connections.%s.after_commit', $defaultConnection),
            false,
        );

        if (! $afterCommit) {
            $issues['warnings'][] = $this->issue(
                'queue_after_commit',
                'The active queue connection does not enable after_commit dispatch by default.',
                1,
            );
        } else {
            $issues['ok'][] = $this->issue(
                'queue_after_commit',
                'The active queue connection enables after_commit dispatch.',
                1,
            );
        }

        $failedDriver = trim((string) config('queue.failed.driver'));
        if ($failedDriver === '' || $failedDriver === 'null') {
            $issues['warnings'][] = $this->issue(
                'failed_jobs_driver',
                'Failed jobs are not configured to be retained.',
                1,
            );
        } else {
            $issues['ok'][] = $this->issue(
                'failed_jobs_driver',
                'Failed jobs storage is configured.',
                1,
                [$failedDriver],
            );
        }

        $workflowQueue = trim((string) config(
            'loan_workflow.notifications.queue',
            '',
        ));
        $workflowNotificationQueues = array_values(array_filter(array_unique([
            trim((string) config('loan_workflow.notifications.mail_queue', '')),
            trim((string) config('loan_workflow.notifications.sms_queue', '')),
        ])));

        if ($workflowQueue === '') {
            $issues['warnings'][] = $this->issue(
                'workflow_queue',
                'The workflow queue name is not configured.',
                1,
            );
        } else {
            $issues['ok'][] = $this->issue(
                'workflow_queue',
                'The workflow queue name is configured.',
                1,
                [$workflowQueue],
            );
        }

        if ($workflowNotificationQueues === []) {
            $issues['warnings'][] = $this->issue(
                'workflow_notification_queues',
                'The workflow notification queues are not configured.',
                1,
            );
        } else {
            $issues['ok'][] = $this->issue(
                'workflow_notification_queues',
                'The workflow notification queues are configured.',
                count($workflowNotificationQueues),
                $workflowNotificationQueues,
            );
        }

        if ($this->schedulerCommandsConfigured()) {
            $issues['ok'][] = $this->issue(
                'workflow_scheduler',
                'The scheduler is configured for workflow reminders and temporary-file cleanup.',
                2,
                ['loan-workflow:send-reminders', 'loan-workflow:cleanup-temp-files'],
            );
        } else {
            $issues['warnings'][] = $this->issue(
                'workflow_scheduler',
                'The scheduler is missing the workflow reminder or temporary cleanup command.',
                2,
                ['loan-workflow:send-reminders', 'loan-workflow:cleanup-temp-files'],
            );
        }

        $issues['deferred'][] = $this->issue(
            'workflow_queue_runtime',
            'Queue-consumer runtime verification requires an external heartbeat or process inspection. Configuration was checked separately.',
            max(1, count($workflowNotificationQueues) + ($workflowQueue === '' ? 0 : 1)),
            array_values(array_filter([
                $workflowQueue !== '' ? $workflowQueue : null,
                ...$workflowNotificationQueues,
            ])),
        );
        $issues['deferred'][] = $this->issue(
            'workflow_scheduler_runtime',
            'Scheduler runtime verification requires an external heartbeat or process inspection. Command registration was checked separately.',
            2,
            ['loan-workflow:send-reminders', 'loan-workflow:cleanup-temp-files'],
        );

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,deferred:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function deliveryConfigurationIssues(
        LoanWorkflowPreflightStage $stage,
    ): array {
        $issues = ['blocking' => [], 'warnings' => [], 'deferred' => [], 'ok' => []];

        [$mailStatus, $mailSummary, $mailReferences] = $this->mailDeliveryAssessment();
        $issues[$mailStatus === 'pass' ? 'ok' : 'warnings'][] = $this->issue(
            'mail_configuration',
            $mailSummary,
            max(1, count($mailReferences)),
            $mailReferences,
        );

        [$smsStatus, $smsSummary, $smsReferences] = $this->smsDeliveryAssessment();
        $issues[$smsStatus === 'pass' ? 'ok' : 'warnings'][] = $this->issue(
            'sms_configuration',
            $smsSummary,
            max(1, count($smsReferences)),
            $smsReferences,
        );

        return $issues;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queueSmokeChecks(): array
    {
        $checks = [];
        $defaultConnection = trim((string) config('queue.default'));
        $isAsyncConnection = $defaultConnection !== ''
            && ! in_array($defaultConnection, ['sync', 'deferred', 'background'], true);

        $checks[] = $this->smokeCheck(
            'queue_connection_async',
            $isAsyncConnection ? 'pass' : 'fail',
            $isAsyncConnection
                ? 'The active queue connection is asynchronous.'
                : 'The active queue connection is not production-safe.',
        );

        $afterCommit = (bool) config(
            sprintf('queue.connections.%s.after_commit', $defaultConnection),
            false,
        );
        $checks[] = $this->smokeCheck(
            'queue_after_commit_enabled',
            $afterCommit ? 'pass' : 'fail',
            $afterCommit
                ? 'The active queue connection enables after_commit dispatch.'
                : 'The active queue connection does not enable after_commit dispatch.',
        );

        $workflowQueue = trim((string) config('loan_workflow.notifications.queue', ''));
        $checks[] = $this->smokeCheck(
            'workflow_queue_configured',
            $workflowQueue !== '' ? 'pass' : 'fail',
            $workflowQueue !== ''
                ? 'The workflow queue name is configured.'
                : 'The workflow queue name is missing.',
        );

        $workflowNotificationQueues = array_values(array_filter(array_unique([
            trim((string) config('loan_workflow.notifications.mail_queue', '')),
            trim((string) config('loan_workflow.notifications.sms_queue', '')),
        ])));
        $checks[] = $this->smokeCheck(
            'workflow_notification_queues_configured',
            $workflowNotificationQueues !== [] ? 'pass' : 'fail',
            $workflowNotificationQueues !== []
                ? 'The workflow notification queues are configured.'
                : 'The workflow notification queues are missing.',
        );

        $failedDriver = trim((string) config('queue.failed.driver'));
        $checks[] = $this->smokeCheck(
            'failed_jobs_storage_configured',
            ($failedDriver !== '' && $failedDriver !== 'null') ? 'pass' : 'fail',
            ($failedDriver !== '' && $failedDriver !== 'null')
                ? 'Failed jobs storage is configured.'
                : 'Failed jobs storage is not configured.',
        );

        $checks[] = $this->smokeCheck(
            'workflow_scheduler_configured',
            $this->schedulerCommandsConfigured() ? 'pass' : 'fail',
            $this->schedulerCommandsConfigured()
                ? 'The scheduler is configured for reminders and temporary cleanup.'
                : 'The scheduler is missing workflow reminder or cleanup commands.',
        );

        $checks[] = $this->smokeCheck(
            'workflow_queue_runtime_verification',
            'deferred',
            'Queue-consumer runtime verification requires an external heartbeat or process inspection.',
        );
        $checks[] = $this->smokeCheck(
            'workflow_scheduler_runtime_verification',
            'deferred',
            'Scheduler runtime verification requires an external heartbeat or process inspection.',
        );

        return $checks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deliverySmokeChecks(): array
    {
        [$mailStatus, $mailSummary] = $this->mailDeliveryAssessment();
        [$smsStatus, $smsSummary] = $this->smsDeliveryAssessment();

        return [
            $this->smokeCheck('mail_delivery_configuration', $mailStatus, $mailSummary),
            $this->smokeCheck('sms_delivery_configuration', $smsStatus, $smsSummary),
        ];
    }

    private function schedulerCommandsConfigured(): bool
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(static fn (object $event): string => trim((string) ($event->command ?? '')))
            ->filter()
            ->values();

        return $commands->contains(
            static fn (string $command): bool => str_contains($command, 'loan-workflow:send-reminders'),
        ) && $commands->contains(
            static fn (string $command): bool => str_contains($command, 'loan-workflow:cleanup-temp-files'),
        );
    }

    /**
     * @return array{0:'pass'|'warn'|'fail', 1:string, 2:list<string>}
     */
    private function mailDeliveryAssessment(): array
    {
        $mailDefault = trim((string) config('mail.default'));

        if ($mailDefault === '') {
            return ['fail', 'Mail delivery is not configured.', []];
        }

        if (app()->environment('production')) {
            if (in_array($mailDefault, ['array', 'log'], true)) {
                return [
                    'fail',
                    'Production mail delivery is configured to a non-live mailer.',
                    [$mailDefault],
                ];
            }

            return ['pass', 'Production mail delivery is configured.', [$mailDefault]];
        }

        if (in_array($mailDefault, ['array', 'log'], true)) {
            return [
                'pass',
                'Non-production mail delivery is safely disabled or redirected.',
                [$mailDefault],
            ];
        }

        $smtpHost = trim((string) config("mail.mailers.{$mailDefault}.host", ''));
        if (in_array($smtpHost, ['127.0.0.1', 'localhost', 'mailpit', 'mailhog'], true)) {
            return [
                'pass',
                'Non-production mail delivery is redirected to a local mail sink.',
                [$mailDefault, $smtpHost],
            ];
        }

        return [
            'fail',
            'Non-production mail delivery appears to target a live or unverified mail transport.',
            array_values(array_filter([$mailDefault, $smtpHost])),
        ];
    }

    /**
     * @return array{0:'pass'|'warn'|'fail', 1:string, 2:list<string>}
     */
    private function smsDeliveryAssessment(): array
    {
        $apiKey = trim((string) config('services.semaphore.api_key', ''));
        $baseUrl = trim((string) config('services.semaphore.base_url', ''));

        if (app()->environment('production')) {
            if ($apiKey === '' || $baseUrl === '') {
                return [
                    'fail',
                    'Production SMS delivery is not fully configured.',
                    array_values(array_filter([$baseUrl !== '' ? $baseUrl : null])),
                ];
            }

            return ['pass', 'Production SMS delivery is configured.', [$baseUrl]];
        }

        if ($apiKey === '') {
            return [
                'pass',
                'Non-production SMS delivery is safely disabled because no provider key is configured.',
                array_values(array_filter([$baseUrl !== '' ? $baseUrl : null])),
            ];
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);
        $normalizedHost = is_string($host) ? trim($host) : '';
        $isSafeHost = $normalizedHost !== ''
            && (
                in_array($normalizedHost, ['127.0.0.1', 'localhost'], true)
                || str_ends_with($normalizedHost, '.test')
            );

        if ($isSafeHost) {
            return [
                'pass',
                'Non-production SMS delivery is redirected to a non-live endpoint.',
                [$baseUrl],
            ];
        }

        return [
            'fail',
            'Non-production SMS delivery appears to target a live or unverified provider endpoint.',
            array_values(array_filter([$baseUrl])),
        ];
    }

    /**
     * @return list<array{reference:string, loan_request_id:int, user_id:int}>
     */
    private function inactiveAssignmentCandidates(): array
    {
        if (
            ! $this->schemaCapabilities->hasTable('loan_requests')
            || ! $this->schemaCapabilities->hasTable('staff_access_controls')
            || ! $this->schemaCapabilities->hasTable('roles')
            || ! $this->schemaCapabilities->hasTable('user_roles')
        ) {
            return [];
        }

        $query = LoanRequest::query()
            ->with(['assignedOfficer.roles', 'assignedOfficer.staffAccessControl'])
            ->whereNotNull('assigned_officer_id')
            ->whereIn('status', $this->assignmentService->operationalAssignmentStatuses())
            ->orderBy('id');

        return $query->get()
            ->filter(function (LoanRequest $loanRequest): bool {
                $officer = $loanRequest->assignedOfficer;

                return ! $officer instanceof AppUser
                    || ! $officer->hasRole(Role::LOAN_PROCESSOR)
                    || ! $officer->hasActiveStaffAccess();
            })
            ->map(fn (LoanRequest $loanRequest): array => [
                'reference' => $loanRequest->reference,
                'loan_request_id' => (int) $loanRequest->id,
                'user_id' => (int) ($loanRequest->assigned_officer_id ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repairLoanRequests(bool $apply, int $chunkSize): array
    {
        $workflowBackfills = [];
        $legacyNormalizations = [];

        LoanRequest::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $loanRequests) use (
                $apply,
                &$workflowBackfills,
                &$legacyNormalizations,
            ): void {
                foreach ($loanRequests as $loanRequest) {
                    $updates = [];

                    if (
                        $this->schemaCapabilities->hasColumn('loan_requests', 'workflow_version')
                        && ($loanRequest->workflow_version === null
                            || trim($this->workflowVersionValue($loanRequest)) === '')
                    ) {
                        $version = $this->determineWorkflowVersion($loanRequest);
                        $updates['workflow_version'] = $version->value;
                        $workflowBackfills[] = $loanRequest->reference;
                    }

                    if (
                        ($loanRequest->status instanceof LoanRequestStatus
                            ? $loanRequest->status->value
                            : (string) $loanRequest->status)
                        === LoanRequestStatus::PendingCoMakerSignatures->value
                    ) {
                        $updates['status'] = LoanRequestStatus::PendingReview->value;
                        $legacyNormalizations[] = $loanRequest->reference;
                    }

                    if ($updates === [] || ! $apply) {
                        continue;
                    }

                    $before = $loanRequest->only(['status', 'workflow_version']);
                    $loanRequest->forceFill($updates)->save();
                    $after = $loanRequest->fresh()->only(['status', 'workflow_version']);

                    if (array_key_exists('workflow_version', $updates)) {
                        $this->recordRepairAudit(
                            'loan_request',
                            $loanRequest->id,
                            $loanRequest->id,
                            'backfill_workflow_version',
                            ['workflow_version' => $before['workflow_version'] ?? null],
                            ['workflow_version' => $after['workflow_version'] ?? null],
                            [
                                'reference' => $loanRequest->reference,
                            ],
                        );
                    }

                    if (array_key_exists('status', $updates)) {
                        $this->recordRepairAudit(
                            'loan_request',
                            $loanRequest->id,
                            $loanRequest->id,
                            'normalize_legacy_co_maker_status',
                            ['status' => $before['status'] ?? null],
                            ['status' => $after['status'] ?? null],
                            [
                                'reference' => $loanRequest->reference,
                            ],
                        );
                    }
                }
            });

        return [
            [
                'type' => 'backfill_workflow_version',
                'count' => count($workflowBackfills),
                'references' => $workflowBackfills,
            ],
            [
                'type' => 'normalize_legacy_co_maker_status',
                'count' => count($legacyNormalizations),
                'references' => $legacyNormalizations,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repairGeneratedDocuments(bool $apply, int $chunkSize): array
    {
        $relocated = [];
        $failed = [];
        $backfilledChecksums = [];

        LoanRequestDocument::query()
            ->with('loanRequest')
            ->whereNotNull('generated_path')
            ->where('generated_path', '!=', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $documents) use (
                $apply,
                &$relocated,
                &$failed,
                &$backfilledChecksums,
            ): void {
                foreach ($documents as $document) {
                    $path = trim((string) $document->generated_path);

                    if ($path === '') {
                        continue;
                    }

                    $reference = $document->loanRequest?->reference ?? 'request';
                    $disk = $document->generated_disk ?: $this->documentStorage->documentDisk();

                    if (! $this->documentStorage->fileExists($path, $disk)) {
                        if ($this->documentStorage->legacyFileExists($path)) {
                            $relocated[] = sprintf('%s %s', $reference, $document->document_key);

                            if ($apply && $this->documentStorage->copyLegacyFileToDisk($path, $disk)) {
                                $absolutePath = $this->documentStorage->absolutePath($path, $disk);
                                $document->fill([
                                    'generated_disk' => $disk,
                                    'generated_size_bytes' => File::size($absolutePath),
                                    'generated_checksum_sha256' => $this->documentStorage->checksumForAbsolutePath($absolutePath),
                                ])->save();

                                $this->recordRepairAudit(
                                    'loan_request_document',
                                    $document->id,
                                    $document->loan_request_id,
                                    'relocate_generated_file_to_private_storage',
                                    ['generated_disk' => $document->getOriginal('generated_disk')],
                                    ['generated_disk' => $disk, 'generated_path' => $path],
                                    ['reference' => $reference],
                                );
                            }

                            continue;
                        }

                        $failed[] = sprintf('%s %s', $reference, $document->document_key);

                        if ($apply) {
                            $before = $document->only([
                                'readiness_status',
                                'failure_information_json',
                            ]);
                            $document->fill([
                                'readiness_status' => LoanRequestDocumentReadinessStatus::GenerationFailed,
                                'failure_information_json' => [
                                    'message' => 'The generated file is missing from private storage.',
                                    'blockers' => [],
                                ],
                            ])->save();

                            $this->recordRepairAudit(
                                'loan_request_document',
                                $document->id,
                                $document->loan_request_id,
                                'mark_missing_generated_file_failed',
                                $before,
                                $document->fresh()->only([
                                    'readiness_status',
                                    'failure_information_json',
                                ]),
                                ['reference' => $reference],
                            );
                        }

                        continue;
                    }

                    if (
                        $this->schemaCapabilities->hasColumn('loan_request_documents', 'generated_checksum_sha256')
                        && trim((string) $document->generated_checksum_sha256) === ''
                    ) {
                        $backfilledChecksums[] = sprintf('%s %s', $reference, $document->document_key);

                        if ($apply) {
                            $absolutePath = $this->documentStorage->absolutePath($path, $disk);
                            $document->fill([
                                'generated_size_bytes' => File::size($absolutePath),
                                'generated_checksum_sha256' => $this->documentStorage->checksumForAbsolutePath($absolutePath),
                            ])->save();

                            $this->recordRepairAudit(
                                'loan_request_document',
                                $document->id,
                                $document->loan_request_id,
                                'backfill_generated_file_checksum',
                                [],
                                [
                                    'generated_checksum_sha256' => $document->generated_checksum_sha256,
                                ],
                                ['reference' => $reference],
                            );
                        }
                    }
                }
            });

        return [
            ['type' => 'relocate_generated_file_to_private_storage', 'count' => count($relocated), 'references' => $relocated],
            ['type' => 'mark_missing_generated_file_failed', 'count' => count($failed), 'references' => $failed],
            ['type' => 'backfill_generated_file_checksum', 'count' => count($backfilledChecksums), 'references' => $backfilledChecksums],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repairDocumentChecklists(bool $apply, int $chunkSize): array
    {
        $repaired = [];

        LoanRequest::query()
            ->with(['documents', 'dataEntries', 'people', 'user'])
            ->where('workflow_version', LoanRequestWorkflowVersion::DocumentWorkflowV2->value)
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $loanRequests) use (
                $apply,
                &$repaired,
            ): void {
                foreach ($loanRequests as $loanRequest) {
                    $inspection = $this->documentWorkflowService->inspectChecklist($loanRequest);
                    $requiresRefresh = $inspection->contains(function (array $item): bool {
                        $storedDocument = $item['document'];
                        $expectedFill = $item['fill'];

                        return ! $storedDocument instanceof LoanRequestDocument
                            || $storedDocument->source_hash !== ($expectedFill['source_hash'] ?? null)
                            || $storedDocument->readiness_status !== ($expectedFill['readiness_status'] ?? null);
                    });

                    if (! $requiresRefresh) {
                        continue;
                    }

                    $repaired[] = $loanRequest->reference;

                    if ($apply) {
                        $before = $loanRequest->documents()
                            ->get()
                            ->map(fn (LoanRequestDocument $document): array => [
                                'document_key' => $document->document_key,
                                'status' => $document->readiness_status?->value,
                                'source_hash' => $document->source_hash,
                            ])
                            ->all();
                        $this->documentWorkflowService->refreshChecklist($loanRequest);
                        $after = $loanRequest->documents()
                            ->get()
                            ->map(fn (LoanRequestDocument $document): array => [
                                'document_key' => $document->document_key,
                                'status' => $document->readiness_status?->value,
                                'source_hash' => $document->source_hash,
                            ])
                            ->all();

                        $this->recordRepairAudit(
                            'loan_request',
                            $loanRequest->id,
                            $loanRequest->id,
                            'refresh_document_checklist',
                            $before,
                            $after,
                            ['reference' => $loanRequest->reference],
                        );
                    }
                }
            });

        return [
            [
                'type' => 'refresh_document_checklist',
                'count' => count($repaired),
                'references' => $repaired,
            ],
        ];
    }

    /**
     * @param  list<array{reference:string, loan_request_id:int, user_id:int}>  $candidates
     * @return array<string, mixed>
     */
    private function repairInactiveAssignments(
        array $candidates,
        AppUser $actor,
        bool $apply,
    ): array {
        $references = array_column($candidates, 'reference');

        if ($apply) {
            foreach ($candidates as $candidate) {
                $officer = AppUser::query()->find($candidate['user_id']);

                if (! $officer instanceof AppUser) {
                    continue;
                }

                $this->assignmentService->unassignUnavailableOfficerRequests(
                    $officer,
                    $actor,
                    'Released by loan workflow repair command because the assigned loan processor is inactive or ineligible.',
                    LoanRequestAssignmentService::CAUSE_STAFF_SUSPENDED,
                );
            }
        }

        return [
            'type' => 'release_inactive_assignments',
            'count' => count($references),
            'references' => $references,
        ];
    }

    private function workflowPermissionsSeeded(
        bool $allowLegacyLoanOfficer = false,
    ): bool {
        if (
            ! $this->schemaCapabilities->hasTable('roles')
            || ! $this->schemaCapabilities->hasTable('permissions')
            || ! $this->schemaCapabilities->hasTable('role_permissions')
        ) {
            return false;
        }

        $roleNames = Role::query()->pluck('name')->all();
        $permissionNames = Permission::query()->pluck('name')->all();
        $requiredRolePermissions = Role::workflowPermissionNames(
            includeLegacyLoanOfficer: $allowLegacyLoanOfficer,
        );

        foreach (array_keys($requiredRolePermissions) as $roleName) {
            if (! in_array($roleName, $roleNames, true)) {
                return false;
            }
        }

        foreach (Permission::defaultNames() as $permissionName) {
            if (! in_array($permissionName, $permissionNames, true)) {
                return false;
            }
        }

        if (! $allowLegacyLoanOfficer && in_array('loan_officer', $roleNames, true)) {
            return false;
        }

        $roles = Role::query()
            ->with('permissions')
            ->whereIn('name', array_keys($requiredRolePermissions))
            ->get()
            ->keyBy('name');

        foreach ($requiredRolePermissions as $roleName => $expectedPermissions) {
            $role = $roles->get($roleName);

            if (! $role instanceof Role) {
                return false;
            }

            $assignedPermissions = $role->permissions
                ->pluck('name')
                ->map(static fn (mixed $value): string => (string) $value)
                ->sort()
                ->values()
                ->all();
            sort($expectedPermissions);

            if ($assignedPermissions !== $expectedPermissions) {
                return false;
            }
        }

        return true;
    }

    private function determineWorkflowVersion(
        LoanRequest $loanRequest,
    ): LoanRequestWorkflowVersion {
        $loanRequest->loadMissing('documents', 'dataEntries', 'notificationEvents');

        if (
            $loanRequest->dataEntries->isNotEmpty()
            || $loanRequest->documents->isNotEmpty()
            || $loanRequest->notificationEvents->isNotEmpty()
            || $loanRequest->member_action_type !== null
            || $loanRequest->recommended_amount !== null
            || $loanRequest->recommended_term !== null
            || $loanRequest->recommended_interest_rate !== null
            || $loanRequest->recommended_payment_frequency !== null
            || $loanRequest->workflow_upgraded_at !== null
            || $loanRequest->workflow_upgraded_by !== null
            || trim((string) ($loanRequest->workflow_upgrade_reason ?? '')) !== ''
        ) {
            return LoanRequestWorkflowVersion::DocumentWorkflowV2;
        }

        return LoanRequestWorkflowVersion::LegacyV1;
    }

    private function workflowVersionValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->workflow_version instanceof LoanRequestWorkflowVersion
            ? $loanRequest->workflow_version->value
            : (string) ($loanRequest->workflow_version ?? '');
    }

    /**
     * @param  list<int>  $loanRequestIds
     * @return list<string>
     */
    private function loanRequestReferences(array $loanRequestIds): array
    {
        if ($loanRequestIds === []) {
            return [];
        }

        return LoanRequest::query()
            ->whereIn('id', $loanRequestIds)
            ->orderBy('id')
            ->get()
            ->map(fn (LoanRequest $loanRequest): string => $loanRequest->reference)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    private function recordRepairAudit(
        string $entityType,
        int $entityId,
        ?int $loanRequestId,
        string $repairType,
        array $before,
        array $after,
        array $metadata = [],
    ): void {
        if (! $this->schemaCapabilities->hasTable('loan_workflow_repairs')) {
            return;
        }

        DB::table('loan_workflow_repairs')->insert([
            'loan_request_id' => $loanRequestId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'repair_type' => $repairType,
            'before_json' => $before === [] ? null : json_encode($before),
            'after_json' => $after === [] ? null : json_encode($after),
            'metadata_json' => $metadata === [] ? null : json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{
     *     blocking:list<array<string, mixed>>,
     *     warnings:list<array<string, mixed>>,
     *     deferred:list<array<string, mixed>>,
     *     ok:list<array<string, mixed>>
     * }
     */
    private function wibsIssues(LoanWorkflowPreflightStage $stage): array
    {
        $issues = [
            'blocking' => [],
            'warnings' => [],
            'deferred' => [],
            'ok' => [],
        ];

        if (! $this->schemaCapabilities->hasTable('loan_requests')) {
            $issues['deferred'][] = $this->issue(
                'wibs_validation',
                'WIBS tracking checks are deferred because the loan_requests table is absent.',
                0,
            );

            return $issues;
        }

        if (! $this->schemaCapabilities->hasColumn('loan_requests', 'wibs_loan_reference')) {
            $issues['deferred'][] = $this->issue(
                'wibs_validation',
                'WIBS tracking checks are deferred because the wibs_loan_reference column is absent. Run migrations first.',
                0,
            );

            return $issues;
        }

        $staleDays = (int) config('loan_workflow.wibs_encoding_stale_days', 5);
        $staleThreshold = CarbonImmutable::now()->subWeekdays($staleDays);

        $staleEncoding = DB::table('loan_requests')
            ->where('status', LoanRequestStatus::ForWibsEncoding->value)
            ->where(function ($query) use ($staleThreshold): void {
                $query->where('wibs_encoded_at', '<', $staleThreshold)
                    ->orWhereNull('wibs_encoded_at');
            })
            ->pluck('id');

        if ($staleEncoding->isNotEmpty()) {
            $issues['deferred'][] = $this->issue(
                'stale_wibs_encoding',
                sprintf(
                    '%d loan request(s) have been in "for_wibs_encoding" status for more than %d business day(s) without a WIBS reference.',
                    $staleEncoding->count(),
                    $staleDays,
                ),
                $staleEncoding->count(),
                $staleEncoding->map(fn (int $id): string => 'request:'.$id)->all(),
            );
        }

        $missingReference = DB::table('loan_requests')
            ->where('status', LoanRequestStatus::WibsLoanCreated->value)
            ->whereNull('wibs_loan_reference')
            ->pluck('id');

        if ($missingReference->isNotEmpty()) {
            $issues['blocking'][] = $this->issue(
                'wibs_reference_missing',
                'Loan request(s) with "wibs_loan_created" status have a null wibs_loan_reference, indicating data inconsistency.',
                $missingReference->count(),
                $missingReference->map(fn (int $id): string => 'request:'.$id)->all(),
            );
        }

        if ($issues['blocking'] === [] && $issues['deferred'] === []) {
            $issues['ok'][] = $this->issue(
                'wibs_tracking',
                'WIBS tracking data integrity checks passed.',
                1,
            );
        }

        return $issues;
    }

    /**
     * @param  list<string>  $references
     * @return array<string, mixed>
     */
    private function issue(
        string $code,
        string $summary,
        int $count,
        array $references = [],
    ): array {
        return [
            'code' => $code,
            'summary' => $summary,
            'count' => $count,
            'references' => array_slice($references, 0, 20),
        ];
    }

    /**
     * @return array{name:string, status:string, summary:string}
     */
    private function smokeCheck(string $name, string $status, string $summary): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'summary' => $summary,
        ];
    }

    /**
     * @param  callable(): array{status:string, summary:string}  $callback
     * @return array{name:string, status:string, summary:string}
     */
    private function smokeCheckCallback(string $name, callable $callback): array
    {
        try {
            $result = $callback();
        } catch (Throwable $throwable) {
            return $this->smokeCheck(
                $name,
                'fail',
                mb_substr($throwable->getMessage(), 0, 160),
            );
        }

        return $this->smokeCheck(
            $name,
            (string) ($result['status'] ?? 'fail'),
            (string) ($result['summary'] ?? ''),
        );
    }
}
