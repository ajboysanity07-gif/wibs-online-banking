<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\Permission;
use App\Models\Role;
use App\Services\LoanRequests\LoanWorkflowProductionSupportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('pre-migration preflight reports expected pending repository migrations as warnings', function (): void {
    rewindPendingLoanWorkflowMigrations();

    $this->artisan('loan-workflow:preflight', [
        '--stage' => 'pre-migration',
    ])
        ->expectsOutputToContain('pending_migrations')
        ->assertSuccessful();
});

test('pre-migration preflight defers post-migration workflow checks when new tables are absent', function (): void {
    rewindPendingLoanWorkflowMigrations();

    $this->artisan('loan-workflow:preflight', [
        '--stage' => 'pre-migration',
    ])
        ->expectsOutputToContain('Deferred checks')
        ->expectsOutputToContain('workflow_schema_artifacts')
        ->expectsOutputToContain('workflow_version_validation')
        ->expectsOutputToContain('document_integrity_validation')
        ->expectsOutputToContain('notification_event_validation')
        ->expectsOutputToContain('inactive_staff_assignment_validation')
        ->assertSuccessful();
});

test('strict post-migration preflight fails while workflow migrations remain pending', function (): void {
    rewindPendingLoanWorkflowMigrations();

    $this->artisan('loan-workflow:preflight', [
        '--stage' => 'post-migration',
    ])
        ->expectsOutputToContain('pending_migrations')
        ->assertFailed();
});

test('strict post-migration preflight passes after migrations, permission seeding, and audited repair', function (): void {
    $member = createDeploymentWorkflowActor([Role::MEMBER], '940001');
    $legacyRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingCoMakerSignatures->value,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'submitted_at' => now(),
    ]);

    rewindPendingLoanWorkflowMigrations();

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    $legacyRequest->refresh();
    expect(resolveLoanRequestStatusValue($legacyRequest))->toBe(
        LoanRequestStatus::PendingCoMakerSignatures->value,
    );

    $this->artisan('loan-workflow:seed-permissions')->assertSuccessful();

    $this->artisan('loan-workflow:preflight', [
        '--stage' => 'post-migration',
    ])
        ->expectsOutputToContain('active_legacy_co_maker_statuses')
        ->assertFailed();

    $this->artisan('loan-workflow:repair', [
        '--apply' => true,
    ])->assertSuccessful();

    $legacyRequest->refresh();

    expect(resolveLoanRequestStatusValue($legacyRequest))->toBe(
        LoanRequestStatus::PendingReview->value,
    )
        ->and(
            DB::table('loan_workflow_repairs')
                ->where('loan_request_id', $legacyRequest->id)
                ->where('repair_type', 'normalize_legacy_co_maker_status')
                ->exists(),
        )->toBeTrue();

    $this->artisan('loan-workflow:preflight', [
        '--stage' => 'post-migration',
    ])->assertSuccessful();
});

test('workflow permission seed command is idempotent and preserves unrelated roles and permissions', function (): void {
    $customRole = Role::query()->create([
        'name' => 'auditor',
        'display_name' => 'Auditor',
    ]);
    $customPermission = Permission::query()->create([
        'name' => 'reports.export',
        'display_name' => 'Export reports',
    ]);
    $customRole->permissions()->syncWithoutDetaching([$customPermission->id]);

    $user = AppUser::factory()->create();
    $user->roles()->syncWithoutDetaching([$customRole->id]);

    $this->artisan('loan-workflow:seed-permissions', [
        '--dry-run' => true,
    ])->assertSuccessful();

    $this->artisan('loan-workflow:seed-permissions')->assertSuccessful();

    $this->artisan('loan-workflow:seed-permissions')->assertSuccessful();

    $customRole->refresh();
    $user->refresh()->load('roles.permissions');

    expect($customRole->permissions()->pluck('name')->all())->toContain('reports.export')
        ->and($user->hasRole('auditor'))->toBeTrue();
});

test('repair command is idempotent and does not dispatch notifications or jobs', function (): void {
    Notification::fake();
    Queue::fake();

    $member = createDeploymentWorkflowActor([Role::MEMBER], '940201');
    $legacyRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingCoMakerSignatures->value,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
        'submitted_at' => now(),
    ]);

    $this->artisan('loan-workflow:repair', [
        '--apply' => true,
    ])->assertSuccessful();

    $firstAuditCount = DB::table('loan_workflow_repairs')->count();

    $this->artisan('loan-workflow:repair', [
        '--apply' => true,
    ])->assertSuccessful();

    $legacyRequest->refresh();

    expect(resolveLoanRequestStatusValue($legacyRequest))->toBe(
        LoanRequestStatus::PendingReview->value,
    )
        ->and(DB::table('loan_workflow_repairs')->count())->toBe($firstAuditCount);

    Notification::assertNothingSent();
    Queue::assertNothingPushed();
});

test('preflight blocks when duplicate document_key pairs exist for the same loan request', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    // Create one document normally while the unique index is in place.
    LoanRequestDocument::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'document_key' => 'application_form',
    ]);

    // Drop both unique indexes (the original from the create migration and the one
    // added by the Phase 7 hardening migration) to simulate a production database
    // where duplicates pre-existed and the Phase 7 unique index silently failed.
    DB::statement('DROP INDEX IF EXISTS loan_request_documents_loan_request_id_document_key_unique');
    DB::statement('DROP INDEX IF EXISTS loan_request_documents_request_document_unique');
    DB::table('loan_request_documents')->insert([
        'loan_request_id' => $loanRequest->id,
        'document_key' => 'application_form',
        'is_applicable' => true,
        'readiness_status' => 'not_started',
        'source_version' => 1,
        'generated_version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('loan-workflow:preflight', [
        '--stage' => 'post-migration',
    ])
        ->expectsOutputToContain('duplicate_document_keys')
        ->assertFailed();
});

test('workflow migrations and permission seeding do not dispatch notifications or jobs', function (): void {
    Notification::fake();
    Queue::fake();

    rewindPendingLoanWorkflowMigrations();

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    $this->artisan('loan-workflow:seed-permissions')->assertSuccessful();

    Notification::assertNothingSent();
    Queue::assertNothingPushed();
});

test('smoke test enforces staging-safe mail and sms configuration', function (): void {
    config()->set('mail.default', 'array');
    config()->set('services.semaphore.api_key', '');
    config()->set('services.semaphore.base_url', 'https://example.test/sms-blackhole');

    $safeChecks = collect(
        app(LoanWorkflowProductionSupportService::class)->smokeTest()['checks'],
    )->keyBy('name');

    expect($safeChecks['mail_delivery_configuration']['status'])->toBe('pass')
        ->and($safeChecks['sms_delivery_configuration']['status'])->toBe('pass');

    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', 'smtp.sendgrid.net');
    config()->set('services.semaphore.api_key', 'live-provider-key');
    config()->set('services.semaphore.base_url', 'https://api.semaphore.co/api/v4/messages');

    $unsafeChecks = collect(
        app(LoanWorkflowProductionSupportService::class)->smokeTest()['checks'],
    )->keyBy('name');

    expect($unsafeChecks['mail_delivery_configuration']['status'])->toBe('fail')
        ->and($unsafeChecks['sms_delivery_configuration']['status'])->toBe('fail');
});

/**
 * @param  list<string>  $roles
 */
function createDeploymentWorkflowActor(
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

function rewindPendingLoanWorkflowMigrations(): void
{
    DB::statement('DROP INDEX IF EXISTS loan_requests_workflow_status_index');

    $migrationPaths = [
        '2026_06_15_234636_add_phase_seven_hardening_to_loan_workflow_tables.php',
        '2026_06_15_081814_rename_loan_officer_role_to_loan_processor.php',
        '2026_06_15_081813_add_phase_five_workflow_fields_to_loan_requests_table.php',
        '2026_06_15_081803_create_loan_request_notification_events_table.php',
        '2026_06_15_081803_create_loan_request_documents_table.php',
        '2026_06_15_081803_create_loan_request_data_entries_table.php',
        '2026_06_15_081803_create_loan_request_data_changes_table.php',
        '2026_06_14_222346_create_user_role_changes_table.php',
        '2026_06_14_222338_create_staff_access_controls_table.php',
    ];

    foreach ($migrationPaths as $migrationPath) {
        /** @var \Illuminate\Database\Migrations\Migration $migration */
        $migration = require database_path('migrations/'.$migrationPath);
        $migration->down();
    }

    DB::table('migrations')
        ->whereIn('migration', [
            '2026_06_14_222338_create_staff_access_controls_table',
            '2026_06_14_222346_create_user_role_changes_table',
            '2026_06_15_081803_create_loan_request_data_changes_table',
            '2026_06_15_081803_create_loan_request_data_entries_table',
            '2026_06_15_081803_create_loan_request_documents_table',
            '2026_06_15_081803_create_loan_request_notification_events_table',
            '2026_06_15_081813_add_phase_five_workflow_fields_to_loan_requests_table',
            '2026_06_15_081814_rename_loan_officer_role_to_loan_processor',
            '2026_06_15_120000_backfill_explicit_superadmin_role',
            '2026_06_15_234636_add_phase_seven_hardening_to_loan_workflow_tables',
        ])->delete();
}

function resolveLoanRequestStatusValue(LoanRequest $loanRequest): string
{
    return $loanRequest->status instanceof LoanRequestStatus
        ? $loanRequest->status->value
        : (string) $loanRequest->status;
}
