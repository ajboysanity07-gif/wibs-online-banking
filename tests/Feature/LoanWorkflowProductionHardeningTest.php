<?php

use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\LoanRequestNotificationEvent;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Services\LoanRequests\LoanRequestNotificationService;
use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('phase seven migration backfills representative legacy loan request documents and workflow metadata', function (): void {
    $member = createProductionWorkflowActor([Role::MEMBER], '930001');
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingCoMakerSignatures->value,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'member_action_type' => null,
        'recommended_amount' => null,
        'recommended_term' => null,
        'recommended_interest_rate' => null,
        'recommended_payment_frequency' => null,
    ]);

    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('loan_request_documents');
    Schema::enableForeignKeyConstraints();

    Schema::create('loan_request_documents', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('loan_request_id');
        $table->string('document_type')->nullable();
        $table->string('status')->nullable();
        $table->text('data_json')->nullable();
        $table->unsignedInteger('version')->default(0);
        $table->string('generated_path')->nullable();
        $table->timestamp('generated_at')->nullable();
        $table->unsignedBigInteger('edited_by')->nullable();
        $table->timestamp('edited_at')->nullable();
        $table->unsignedBigInteger('downloaded_by')->nullable();
        $table->timestamp('downloaded_at')->nullable();
        $table->timestamps();
    });

    DB::table('loan_request_documents')->insert([
        'loan_request_id' => $loanRequest->id,
        'document_type' => 'application_form',
        'status' => 'generated',
        'data_json' => json_encode(['legacy' => true]),
        'version' => 2,
        'generated_path' => sprintf(
            'loan-request-documents/%d/application_form/v2.pdf',
            $loanRequest->id,
        ),
        'generated_at' => now(),
        'edited_by' => $member->user_id,
        'edited_at' => now(),
        'downloaded_by' => $member->user_id,
        'downloaded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path(
        'migrations/2026_06_15_234636_add_phase_seven_hardening_to_loan_workflow_tables.php',
    );
    $migration->up();

    expect(Schema::hasColumn('loan_request_documents', 'document_key'))->toBeTrue()
        ->and(
            Schema::hasColumn(
                'loan_request_documents',
                'generated_checksum_sha256',
            ),
        )->toBeTrue();

    $document = DB::table('loan_request_documents')->first();
    $loanRequest->refresh();

    expect($document->document_key)->toBe('application_form')
        ->and($document->readiness_status)->toBe(
            LoanRequestDocumentReadinessStatus::GeneratedCurrent->value,
        )
        ->and((int) $document->generated_version)->toBe(2)
        ->and($document->generated_disk)->toBe('local')
        ->and((string) $document->metadata_json)->toContain('legacy_data_json')
        ->and($loanRequest->status)->toBe(LoanRequestStatus::PendingCoMakerSignatures)
        ->and($loanRequest->workflow_version)->toBe(
            LoanRequestWorkflowVersion::LegacyV1,
        );
});

test('loan workflow preflight reports blocking legacy and storage issues', function (): void {
    $member = createProductionWorkflowActor([Role::MEMBER], '930101');
    $processor = createProductionWorkflowActor([Role::LOAN_PROCESSOR]);

    StaffAccessControl::factory()->suspended($processor)->create([
        'user_id' => $processor->user_id,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingCoMakerSignatures->value,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);
    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    LoanRequestDocument::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'document_key' => 'application_form',
        'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
        'source_hash' => 'stale-hash',
        'source_version' => 1,
        'generated_version' => 1,
        'generated_disk' => 'local',
        'generated_path' => sprintf(
            'loan-request-documents/%d/application_form/missing.pdf',
            $loanRequest->id,
        ),
        'generated_filename' => 'missing.pdf',
        'generated_mime_type' => 'application/pdf',
        'generated_at' => now(),
    ]);

    $exitCode = Artisan::call('loan-workflow:preflight');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('active_legacy_co_maker_statuses')
        ->and($output)->toContain('missing_generated_files')
        ->and($output)->toContain('inactive_staff_assignments');
});

test('loan workflow repair command supports dry run and deterministic apply mode', function (): void {
    $member = createProductionWorkflowActor([Role::MEMBER], '930201');
    $repairActor = createProductionWorkflowActor([Role::LOAN_MANAGER]);
    $inactiveProcessor = createProductionWorkflowActor([Role::LOAN_PROCESSOR]);

    StaffAccessControl::factory()->suspended($repairActor)->create([
        'user_id' => $inactiveProcessor->user_id,
    ]);

    $legacyRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingCoMakerSignatures->value,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'assigned_officer_id' => $inactiveProcessor->user_id,
        'submitted_at' => now(),
    ]);
    $operationalRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $inactiveProcessor->user_id,
        'submitted_at' => now(),
    ]);

    $existingPath = sprintf(
        'loan-request-documents/%d/application_form/v1.pdf',
        $legacyRequest->id,
    );
    Storage::disk('local')->put($existingPath, 'workflow-pdf');

    LoanRequestDocument::factory()->create([
        'loan_request_id' => $legacyRequest->id,
        'document_key' => 'application_form',
        'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
        'source_hash' => 'hash-a',
        'source_version' => 1,
        'generated_version' => 1,
        'generated_disk' => 'local',
        'generated_path' => $existingPath,
        'generated_filename' => 'v1.pdf',
        'generated_mime_type' => 'application/pdf',
        'generated_checksum_sha256' => null,
        'generated_at' => now(),
    ]);

    LoanRequestDocument::factory()->create([
        'loan_request_id' => $legacyRequest->id,
        'document_key' => 'promissory_note',
        'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
        'source_hash' => 'hash-b',
        'source_version' => 1,
        'generated_version' => 1,
        'generated_disk' => 'local',
        'generated_path' => sprintf(
            'loan-request-documents/%d/promissory_note/missing.pdf',
            $legacyRequest->id,
        ),
        'generated_filename' => 'missing.pdf',
        'generated_mime_type' => 'application/pdf',
        'generated_at' => now(),
    ]);

    $dryRunExitCode = Artisan::call('loan-workflow:repair', [
        '--chunk' => 50,
        '--actor-user-id' => $repairActor->user_id,
    ]);
    $dryRunOutput = Artisan::output();

    $legacyRequest->refresh();
    $existingDocument = LoanRequestDocument::query()
        ->where('loan_request_id', $legacyRequest->id)
        ->where('document_key', 'application_form')
        ->firstOrFail();

    expect($dryRunExitCode)->toBe(0)
        ->and($dryRunOutput)->toContain('normalize_legacy_co_maker_status')
        ->and($dryRunOutput)->toContain('backfill_generated_file_checksum')
        ->and($dryRunOutput)->toContain('mark_missing_generated_file_failed')
        ->and($dryRunOutput)->toContain(
            'Inactive staff assignments were detected but were not released because no repair actor was provided.',
        )
        ->and($legacyRequest->status)->toBe(LoanRequestStatus::PendingCoMakerSignatures)
        ->and($legacyRequest->workflow_version)->toBe(
            LoanRequestWorkflowVersion::LegacyV1,
        )
        ->and($legacyRequest->assigned_officer_id)->toBe(
            $inactiveProcessor->user_id,
        )
        ->and($existingDocument->generated_checksum_sha256)->toBeNull();

    $applyExitCode = Artisan::call('loan-workflow:repair', [
        '--apply' => true,
        '--chunk' => 50,
        '--actor-user-id' => $repairActor->user_id,
    ]);

    $legacyRequest->refresh();
    $operationalRequest->refresh();
    $existingDocument->refresh();
    $missingDocument = LoanRequestDocument::query()
        ->where('loan_request_id', $legacyRequest->id)
        ->where('document_key', 'promissory_note')
        ->firstOrFail();

    expect($applyExitCode)->toBe(0)
        ->and($legacyRequest->status)->toBe(LoanRequestStatus::PendingReview)
        ->and($legacyRequest->workflow_version)->toBe(
            LoanRequestWorkflowVersion::LegacyV1,
        )
        ->and($legacyRequest->assigned_officer_id)->toBeNull()
        ->and($operationalRequest->assigned_officer_id)->toBeNull()
        ->and($existingDocument->generated_checksum_sha256)->not->toBeNull()
        ->and($missingDocument->readiness_status)->toBe(
            LoanRequestDocumentReadinessStatus::GenerationFailed,
        )
        ->and(DB::table('loan_workflow_repairs')->count())->toBeGreaterThan(0);
});

test('loan workflow smoke test passes when required configuration is present', function (): void {
    config()->set('mail.default', 'array');
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.after_commit', true);
    config()->set('services.semaphore.api_key', 'test-key');
    config()->set('services.semaphore.base_url', 'https://example.test/sms');

    if (! Schema::hasTable('loan_request_notification_events')) {
        $migration = require database_path(
            'migrations/2026_06_15_081803_create_loan_request_notification_events_table.php',
        );
        $migration->up();
    }

    Artisan::call('loan-workflow:seed-permissions');

    $exitCode = Artisan::call('loan-workflow:smoke-test');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('database_connection')
        ->and($output)->toContain('workflow_permissions_seeded')
        ->and($output)->toContain('pass');
});

test('workflow notifications are deduplicated and action links are signed', function (): void {
    config()->set('mail.default', 'array');

    $member = createProductionWorkflowActor([Role::MEMBER], '930301');
    $member->forceFill([
        'email' => 'member@example.test',
        'phoneno' => null,
    ])->save();

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::AwaitingMemberInformation,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $service = app(LoanRequestNotificationService::class);
    $service->notifyMember($loanRequest, LoanRequestNotificationService::EVENT_AWAITING_MEMBER_INFORMATION, [
        'title' => 'Loan request needs more information',
        'message' => 'Please review the requested fields.',
        'reason' => 'Missing information.',
    ]);
    $service->notifyMember($loanRequest, LoanRequestNotificationService::EVENT_AWAITING_MEMBER_INFORMATION, [
        'title' => 'Loan request needs more information',
        'message' => 'Please review the requested fields.',
        'reason' => 'Missing information.',
    ]);

    $events = LoanRequestNotificationEvent::query()
        ->where('loan_request_id', $loanRequest->id)
        ->orderBy('channel')
        ->get();

    $databaseEvent = $events->firstWhere('channel', 'database');

    expect($events)->toHaveCount(2)
        ->and($events->pluck('channel')->all())->toBe(['database', 'email'])
        ->and($databaseEvent)->not->toBeNull()
        ->and($databaseEvent?->metadata_json['action_url'] ?? null)->not->toBeNull()
        ->and(URL::hasValidSignature(Request::create(
            $databaseEvent->metadata_json['action_url'],
            'GET',
        )))->toBeTrue();
});

test('temporary workflow cleanup removes only expired temporary files', function (): void {
    $service = app(LoanWorkflowProductionSupportService::class);
    $disk = config('loan_workflow.documents.disk', 'local');

    $oldPath = config('loan_workflow.documents.temporary_directory').'/old-file.txt';
    $newPath = config('loan_workflow.documents.temporary_directory').'/new-file.txt';

    Storage::disk($disk)->put($oldPath, 'old');
    Storage::disk($disk)->put($newPath, 'new');

    $oldAbsolutePath = Storage::disk($disk)->path($oldPath);
    $newAbsolutePath = Storage::disk($disk)->path($newPath);

    File::lastModified($oldAbsolutePath);
    touch($oldAbsolutePath, now()->subHours(
        max(
            2,
            (int) config('loan_workflow.documents.temporary_retention_hours', 24) + 1,
        ),
    )->timestamp);

    $result = $service->cleanupTemporaryFiles(true);

    expect($result['candidates'])->toBeGreaterThanOrEqual(1)
        ->and(Storage::disk($disk)->exists($oldPath))->toBeFalse()
        ->and(Storage::disk($disk)->exists($newPath))->toBeTrue();

    @unlink($newAbsolutePath);
});

/**
 * @param  list<string>  $roles
 */
function createProductionWorkflowActor(
    array $roles,
    ?string $acctno = null,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}
