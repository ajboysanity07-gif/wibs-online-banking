<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Support\SchemaCapabilities;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
     *     ok:list<array<string, mixed>>
     * }
     */
    public function preflight(): array
    {
        $report = [
            'blocking' => [],
            'warnings' => [],
            'ok' => [],
        ];

        $pendingMigrations = $this->pendingMigrationNames();
        if ($pendingMigrations !== []) {
            $report['blocking'][] = $this->issue(
                'pending_migrations',
                'Pending migrations must be applied before deployment.',
                count($pendingMigrations),
                $pendingMigrations,
            );
        } else {
            $report['ok'][] = $this->issue(
                'pending_migrations',
                'No pending migrations detected.',
                0,
            );
        }

        foreach ($this->loanRequestDataIssues() as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->roleAssignmentIssues() as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->documentIssues() as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->notificationIssues() as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->queueConfigurationIssues() as $severity => $issues) {
            $report[$severity] = array_merge($report[$severity], $issues);
        }

        foreach ($this->deliveryConfigurationIssues() as $severity => $issues) {
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
        $checks[] = $this->smokeCheck('database_connection', fn (): bool => DB::connection()->getPdo() !== null);
        $checks[] = $this->smokeCheck('loan_requests_table', fn (): bool => $this->schemaCapabilities->hasTable('loan_requests'));
        $checks[] = $this->smokeCheck('loan_request_documents_table', fn (): bool => $this->schemaCapabilities->hasTable('loan_request_documents'));
        $checks[] = $this->smokeCheck('loan_request_notification_events_table', fn (): bool => $this->schemaCapabilities->hasTable('loan_request_notification_events'));
        $checks[] = $this->smokeCheck('private_storage_round_trip', function (): bool {
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

            return $exists && $contents === 'ok' && ! Storage::disk($disk)->exists($path);
        });
        $checks[] = $this->smokeCheck('queue_configuration', fn (): bool => trim((string) config('queue.default')) !== '');
        $checks[] = $this->smokeCheck('mail_configuration_present', fn (): bool => trim((string) config('mail.default')) !== '');
        $checks[] = $this->smokeCheck('sms_configuration_present', fn (): bool => trim((string) config('services.semaphore.api_key', '')) !== '');
        $checks[] = $this->smokeCheck('document_templates_present', fn (): bool => $this->documentCatalog->templateAvailabilityIssues() === []);
        $checks[] = $this->smokeCheck('workflow_action_route_registered', fn (): bool => Route::has('loan-requests.action'));
        $checks[] = $this->smokeCheck('workflow_permissions_seeded', fn (): bool => $this->workflowPermissionsSeeded());

        return ['checks' => $checks];
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

    /**
     * @return list<string>
     */
    private function pendingMigrationNames(): array
    {
        if (! $this->schemaCapabilities->hasTable('migrations')) {
            return collect(File::files(database_path('migrations')))
                ->map(fn (\SplFileInfo $file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
                ->sort()
                ->values()
                ->all();
        }

        $ran = DB::table('migrations')->pluck('migration')->all();

        return collect(File::files(database_path('migrations')))
            ->map(fn (\SplFileInfo $file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->reject(fn (string $migration): bool => in_array($migration, $ran, true))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function loanRequestDataIssues(): array
    {
        $issues = ['blocking' => [], 'warnings' => [], 'ok' => []];

        if (! $this->schemaCapabilities->hasTable('loan_requests')) {
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
        }

        $legacyStatusIds = LoanRequest::query()
            ->where('status', LoanRequestStatus::PendingCoMakerSignatures->value)
            ->pluck('id');

        if ($legacyStatusIds->isNotEmpty()) {
            $issues['blocking'][] = $this->issue(
                'active_legacy_co_maker_statuses',
                'Legacy pending co-maker signature statuses are still active.',
                $legacyStatusIds->count(),
                $this->loanRequestReferences($legacyStatusIds->all()),
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function roleAssignmentIssues(): array
    {
        $issues = ['blocking' => [], 'warnings' => [], 'ok' => []];

        if ($this->schemaCapabilities->hasTable('user_roles')) {
            $invalidAssignments = DB::table('user_roles as user_roles')
                ->leftJoin('appusers', 'appusers.user_id', '=', 'user_roles.user_id')
                ->leftJoin('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where(function ($query): void {
                    $query
                        ->whereNull('appusers.user_id')
                        ->orWhereNull('roles.id')
                        ->orWhereNotIn('roles.name', [
                            ...array_map(
                                static fn (array $role): string => $role['name'],
                                Role::defaults(),
                            ),
                            'loan_officer',
                        ]);
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
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function documentIssues(): array
    {
        $issues = ['blocking' => [], 'warnings' => [], 'ok' => []];

        if (
            ! $this->schemaCapabilities->hasTable('loan_request_documents')
            || ! $this->schemaCapabilities->hasColumn('loan_request_documents', 'document_key')
        ) {
            return $issues;
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
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function notificationIssues(): array
    {
        $issues = ['blocking' => [], 'warnings' => [], 'ok' => []];

        if (! $this->schemaCapabilities->hasTable('loan_request_notification_events')) {
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
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function queueConfigurationIssues(): array
    {
        $issues = ['blocking' => [], 'warnings' => [], 'ok' => []];
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
        }

        $failedDriver = trim((string) config('queue.failed.driver'));
        if ($failedDriver === '' || $failedDriver === 'null') {
            $issues['warnings'][] = $this->issue(
                'failed_jobs_driver',
                'Failed jobs are not configured to be retained.',
                1,
            );
        }

        return $issues;
    }

    /**
     * @return array{blocking:list<array<string,mixed>>,warnings:list<array<string,mixed>>,ok:list<array<string,mixed>>}
     */
    private function deliveryConfigurationIssues(): array
    {
        $issues = ['blocking' => [], 'warnings' => [], 'ok' => []];
        $mailDefault = trim((string) config('mail.default'));

        if ($mailDefault === '') {
            $issues['warnings'][] = $this->issue(
                'mail_configuration',
                'Mail delivery is not configured.',
                1,
            );
        }

        if (trim((string) config('services.semaphore.api_key', '')) === '') {
            $issues['warnings'][] = $this->issue(
                'sms_configuration',
                'SMS delivery is not configured.',
                1,
            );
        }

        return $issues;
    }

    /**
     * @return list<array{reference:string, loan_request_id:int, user_id:int}>
     */
    private function inactiveAssignmentCandidates(): array
    {
        if (! $this->schemaCapabilities->hasTable('loan_requests')) {
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

                    $this->recordRepairAudit(
                        'loan_request',
                        $loanRequest->id,
                        $loanRequest->id,
                        array_key_exists('workflow_version', $updates)
                            ? 'backfill_workflow_version'
                            : 'normalize_legacy_co_maker_status',
                        $before,
                        $loanRequest->fresh()->only(['status', 'workflow_version']),
                        [
                            'reference' => $loanRequest->reference,
                        ],
                    );
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

    private function workflowPermissionsSeeded(): bool
    {
        if (
            ! $this->schemaCapabilities->hasTable('roles')
            || ! $this->schemaCapabilities->hasTable('permissions')
        ) {
            return false;
        }

        $roleNames = Role::query()->pluck('name')->all();
        $permissionNames = Permission::query()->pluck('name')->all();

        foreach (array_map(
            static fn (array $role): string => $role['name'],
            Role::defaults(),
        ) as $roleName) {
            if (! in_array($roleName, $roleNames, true)) {
                return false;
            }
        }

        foreach (Permission::defaultNames() as $permissionName) {
            if (! in_array($permissionName, $permissionNames, true)) {
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
     * @param  callable(): bool  $callback
     * @return array{name:string, status:string}
     */
    private function smokeCheck(string $name, callable $callback): array
    {
        return [
            'name' => $name,
            'status' => $callback() ? 'pass' : 'fail',
        ];
    }
}
