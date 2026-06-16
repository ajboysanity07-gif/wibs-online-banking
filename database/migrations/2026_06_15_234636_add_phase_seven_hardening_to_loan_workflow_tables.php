<?php

use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createRepairAuditTable();
        $this->hardenLoanRequestDocumentsTable();
        $this->hardenLoanRequestDataEntriesTable();
        $this->hardenLoanRequestDataChangesTable();
        $this->hardenLoanRequestNotificationEventsTable();
        $this->hardenLoanRequestChangesTable();
        $this->hardenLoanRequestsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_workflow_repairs');
    }

    private function createRepairAuditTable(): void
    {
        if (Schema::hasTable('loan_workflow_repairs')) {
            return;
        }

        Schema::create('loan_workflow_repairs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_request_id')->nullable();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('repair_type');
            $table->text('before_json')->nullable();
            $table->text('after_json')->nullable();
            $table->text('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['repair_type', 'created_at']);
            $table->index(['loan_request_id', 'created_at']);
        });
    }

    private function hardenLoanRequestDocumentsTable(): void
    {
        if (! Schema::hasTable('loan_request_documents')) {
            return;
        }

        Schema::table('loan_request_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_request_documents', 'document_key')) {
                $table->string('document_key')->nullable()->after('loan_request_id');
            }

            if (! Schema::hasColumn('loan_request_documents', 'is_applicable')) {
                $table->boolean('is_applicable')->default(true)->after('document_key');
            }

            if (! Schema::hasColumn('loan_request_documents', 'readiness_status')) {
                $table->string('readiness_status')
                    ->default(LoanRequestDocumentReadinessStatus::NotStarted->value)
                    ->after('is_applicable');
            }

            if (! Schema::hasColumn('loan_request_documents', 'template_version')) {
                $table->string('template_version')->nullable()->after('readiness_status');
            }

            if (! Schema::hasColumn('loan_request_documents', 'source_hash')) {
                $table->string('source_hash')->nullable()->after('template_version');
            }

            if (! Schema::hasColumn('loan_request_documents', 'source_version')) {
                $table->unsignedInteger('source_version')->default(0)->after('source_hash');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_version')) {
                $table->unsignedInteger('generated_version')->default(0)->after('source_version');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_disk')) {
                $table->string('generated_disk')->nullable()->after('generated_version');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_path')) {
                $table->string('generated_path')->nullable()->after('generated_disk');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_filename')) {
                $table->string('generated_filename')->nullable()->after('generated_path');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_mime_type')) {
                $table->string('generated_mime_type')->nullable()->after('generated_filename');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_size_bytes')) {
                $table->unsignedBigInteger('generated_size_bytes')->nullable()->after('generated_mime_type');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_checksum_sha256')) {
                $table->string('generated_checksum_sha256')->nullable()->after('generated_size_bytes');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_by')) {
                $table->unsignedBigInteger('generated_by')->nullable()->after('generated_checksum_sha256');
            }

            if (! Schema::hasColumn('loan_request_documents', 'generated_at')) {
                $table->timestamp('generated_at')->nullable()->after('generated_by');
            }

            if (! Schema::hasColumn('loan_request_documents', 'failure_information_json')) {
                $table->text('failure_information_json')->nullable()->after('generated_at');
            }

            if (! Schema::hasColumn('loan_request_documents', 'metadata_json')) {
                $table->text('metadata_json')->nullable()->after('failure_information_json');
            }
        });

        $this->backfillLegacyLoanRequestDocuments();

        $this->tryAddIndex('loan_request_documents', ['loan_request_id', 'document_key'], 'loan_request_documents_request_document_unique', true);
        $this->tryAddIndex('loan_request_documents', ['loan_request_id', 'readiness_status'], 'loan_request_documents_request_status_index');
        $this->tryAddIndex('loan_request_documents', ['readiness_status'], 'loan_request_documents_status_index');
        $this->tryAddIndex('loan_request_documents', ['generated_checksum_sha256'], 'loan_request_documents_checksum_index');
    }

    private function hardenLoanRequestDataEntriesTable(): void
    {
        if (! Schema::hasTable('loan_request_data_entries')) {
            return;
        }

        $this->tryAddIndex('loan_request_data_entries', ['loan_request_id', 'section_key'], 'loan_request_data_entries_request_section_index');
        $this->tryAddIndex('loan_request_data_entries', ['owner_type', 'confirmed_by_member'], 'loan_request_data_entries_owner_confirmed_index');
    }

    private function hardenLoanRequestDataChangesTable(): void
    {
        if (! Schema::hasTable('loan_request_data_changes')) {
            return;
        }

        $this->tryAddIndex('loan_request_data_changes', ['loan_request_id', 'field_key'], 'loan_request_data_changes_request_field_index');
        $this->tryAddIndex('loan_request_data_changes', ['actor_user_id', 'created_at'], 'loan_request_data_changes_actor_created_index');
    }

    private function hardenLoanRequestNotificationEventsTable(): void
    {
        if (! Schema::hasTable('loan_request_notification_events')) {
            return;
        }

        Schema::table('loan_request_notification_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_request_notification_events', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('result');
            }

            if (! Schema::hasColumn('loan_request_notification_events', 'last_attempt_at')) {
                $table->timestamp('last_attempt_at')->nullable()->after('queued_at');
            }

            if (! Schema::hasColumn('loan_request_notification_events', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('last_attempt_at');
            }

            if (! Schema::hasColumn('loan_request_notification_events', 'attempt_count')) {
                $table->unsignedInteger('attempt_count')->default(0)->after('failed_at');
            }

            if (! Schema::hasColumn('loan_request_notification_events', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('attempt_count');
            }

            if (! Schema::hasColumn('loan_request_notification_events', 'reminder_attempts')) {
                $table->unsignedInteger('reminder_attempts')->default(0)->after('retry_count');
            }

            if (! Schema::hasColumn('loan_request_notification_events', 'provider_reference')) {
                $table->string('provider_reference')->nullable()->after('reminder_attempts');
            }

            if (! Schema::hasColumn('loan_request_notification_events', 'provider_error')) {
                $table->text('provider_error')->nullable()->after('provider_reference');
            }
        });

        $this->tryAddIndex('loan_request_notification_events', ['loan_request_id', 'result'], 'loan_request_notification_events_request_result_index');
        $this->tryAddIndex('loan_request_notification_events', ['loan_request_id', 'event_type', 'channel'], 'loan_request_notification_events_request_event_channel_index');
        $this->tryAddIndex('loan_request_notification_events', ['recipient_user_id', 'channel'], 'loan_request_notification_events_recipient_channel_index');
    }

    private function hardenLoanRequestChangesTable(): void
    {
        if (! Schema::hasTable('loan_request_changes')) {
            return;
        }

        $this->tryAddIndex('loan_request_changes', ['action', 'created_at'], 'loan_request_changes_action_created_index');

        if (Schema::hasColumn('loan_request_changes', 'from_status')) {
            $this->tryAddIndex('loan_request_changes', ['from_status'], 'loan_request_changes_from_status_index');
        }

        if (Schema::hasColumn('loan_request_changes', 'to_status')) {
            $this->tryAddIndex('loan_request_changes', ['to_status'], 'loan_request_changes_to_status_index');
        }
    }

    private function hardenLoanRequestsTable(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        $this->tryAddIndex('loan_requests', ['workflow_version', 'status'], 'loan_requests_workflow_status_index');
        $this->tryAddIndex('loan_requests', ['assigned_officer_id', 'status'], 'loan_requests_assignment_status_index');

        if (! Schema::hasColumn('loan_requests', 'workflow_version')) {
            return;
        }

        DB::table('loan_requests')
            ->select([
                'id',
                'status',
                'workflow_version',
                'member_action_type',
                'recommended_amount',
                'recommended_term',
                'recommended_interest_rate',
                'recommended_payment_frequency',
                'workflow_upgraded_at',
                'workflow_upgraded_by',
                'workflow_upgrade_reason',
            ])
            ->orderBy('id')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $updates = [];

                    if ($row->workflow_version === null || trim((string) $row->workflow_version) === '') {
                        $updates['workflow_version'] = $this->determineWorkflowVersion($row);
                    }

                    if ($row->status === LoanRequestStatus::PendingCoMakerSignatures->value) {
                        $updates['status'] = LoanRequestStatus::PendingReview->value;
                        $updates['updated_at'] = now();
                    }

                    if ($updates === []) {
                        continue;
                    }

                    DB::table('loan_requests')
                        ->where('id', $row->id)
                        ->update($updates);
                }
            }, 'id');
    }

    private function backfillLegacyLoanRequestDocuments(): void
    {
        $hasLegacyType = Schema::hasColumn('loan_request_documents', 'document_type');
        $hasLegacyStatus = Schema::hasColumn('loan_request_documents', 'status');
        $hasLegacyData = Schema::hasColumn('loan_request_documents', 'data_json');
        $hasLegacyVersion = Schema::hasColumn('loan_request_documents', 'version');
        $hasLegacyEditedBy = Schema::hasColumn('loan_request_documents', 'edited_by');
        $hasLegacyEditedAt = Schema::hasColumn('loan_request_documents', 'edited_at');
        $hasLegacyDownloadedBy = Schema::hasColumn('loan_request_documents', 'downloaded_by');
        $hasLegacyDownloadedAt = Schema::hasColumn('loan_request_documents', 'downloaded_at');

        DB::table('loan_request_documents')
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) use (
                $hasLegacyType,
                $hasLegacyStatus,
                $hasLegacyData,
                $hasLegacyVersion,
                $hasLegacyEditedBy,
                $hasLegacyEditedAt,
                $hasLegacyDownloadedBy,
                $hasLegacyDownloadedAt,
            ): void {
                foreach ($rows as $row) {
                    $updates = [];
                    $metadata = [];

                    if (
                        $hasLegacyType
                        && ($row->document_key === null || trim((string) $row->document_key) === '')
                    ) {
                        $mappedDocumentKey = $this->mapLegacyDocumentKey(
                            $row->document_type,
                        );

                        if ($mappedDocumentKey !== null) {
                            $updates['document_key'] = $mappedDocumentKey;
                        }
                    }

                    if (
                        $hasLegacyStatus
                        && (
                            $row->readiness_status === null
                            || trim((string) $row->readiness_status) === ''
                            || $row->readiness_status === LoanRequestDocumentReadinessStatus::NotStarted->value
                        )
                    ) {
                        $updates['readiness_status'] = $this->mapLegacyReadinessStatus(
                            $row->status,
                            $row->generated_at,
                        );
                    }

                    if (
                        $hasLegacyVersion
                        && (int) ($row->generated_version ?? 0) === 0
                        && (int) ($row->version ?? 0) > 0
                    ) {
                        $updates['generated_version'] = (int) $row->version;
                    }

                    if (
                        ($row->generated_disk === null || trim((string) $row->generated_disk) === '')
                        && $row->generated_path !== null
                        && trim((string) $row->generated_path) !== ''
                    ) {
                        $updates['generated_disk'] = config(
                            'loan_workflow.documents.disk',
                            'local',
                        );
                    }

                    if ($row->generated_filename === null && $row->generated_path !== null) {
                        $filename = basename((string) $row->generated_path);

                        if ($filename !== '') {
                            $updates['generated_filename'] = $filename;
                        }
                    }

                    if ($hasLegacyData && $row->data_json !== null) {
                        $metadata['legacy_data_json'] = $row->data_json;
                    }

                    if ($hasLegacyEditedBy && $row->edited_by !== null) {
                        $metadata['legacy_edited_by'] = $row->edited_by;
                    }

                    if ($hasLegacyEditedAt && $row->edited_at !== null) {
                        $metadata['legacy_edited_at'] = $row->edited_at;
                    }

                    if ($hasLegacyDownloadedBy && $row->downloaded_by !== null) {
                        $metadata['legacy_downloaded_by'] = $row->downloaded_by;
                    }

                    if ($hasLegacyDownloadedAt && $row->downloaded_at !== null) {
                        $metadata['legacy_downloaded_at'] = $row->downloaded_at;
                    }

                    if ($metadata !== [] && ($row->metadata_json === null || trim((string) $row->metadata_json) === '')) {
                        $updates['metadata_json'] = json_encode($metadata);
                    }

                    if ($updates === []) {
                        continue;
                    }

                    DB::table('loan_request_documents')
                        ->where('id', $row->id)
                        ->update($updates);
                }
            });
    }

    private function mapLegacyDocumentKey(mixed $value): ?string
    {
        $documentType = trim((string) $value);

        if ($documentType === '') {
            return null;
        }

        return match ($documentType) {
            'application_form',
            'grepalife',
            'affidavit_undertaking',
            'authorization',
            'loan_information',
            'plan_of_payment',
            'disclosure_statement',
            'promissory_note',
            'undertaking_barangay',
            'loan_security_agreement' => $documentType,
            default => null,
        };
    }

    private function mapLegacyReadinessStatus(
        mixed $legacyStatus,
        mixed $generatedAt,
    ): string {
        $value = trim((string) $legacyStatus);

        return match ($value) {
            LoanRequestDocumentReadinessStatus::NotStarted->value,
            LoanRequestDocumentReadinessStatus::Incomplete->value,
            LoanRequestDocumentReadinessStatus::AwaitingMemberConfirmation->value,
            LoanRequestDocumentReadinessStatus::ReadyToGenerate->value,
            LoanRequestDocumentReadinessStatus::GeneratedCurrent->value,
            LoanRequestDocumentReadinessStatus::GeneratedStale->value,
            LoanRequestDocumentReadinessStatus::GenerationFailed->value,
            LoanRequestDocumentReadinessStatus::NotApplicable->value,
            LoanRequestDocumentReadinessStatus::LegacyDataIncomplete->value => $value,
            'generated',
            'current' => LoanRequestDocumentReadinessStatus::GeneratedCurrent->value,
            'failed' => LoanRequestDocumentReadinessStatus::GenerationFailed->value,
            default => $generatedAt !== null
                ? LoanRequestDocumentReadinessStatus::GeneratedCurrent->value
                : LoanRequestDocumentReadinessStatus::NotStarted->value,
        };
    }

    private function determineWorkflowVersion(object $row): string
    {
        if (
            $row->member_action_type !== null
            || $row->recommended_amount !== null
            || $row->recommended_term !== null
            || $row->recommended_interest_rate !== null
            || $row->recommended_payment_frequency !== null
            || $row->workflow_upgraded_at !== null
            || $row->workflow_upgraded_by !== null
            || trim((string) ($row->workflow_upgrade_reason ?? '')) !== ''
        ) {
            return LoanRequestWorkflowVersion::DocumentWorkflowV2->value;
        }

        if (
            Schema::hasTable('loan_request_data_entries')
            && DB::table('loan_request_data_entries')
                ->where('loan_request_id', $row->id)
                ->exists()
        ) {
            return LoanRequestWorkflowVersion::DocumentWorkflowV2->value;
        }

        if (
            Schema::hasTable('loan_request_documents')
            && DB::table('loan_request_documents')
                ->where('loan_request_id', $row->id)
                ->whereNotNull('document_key')
                ->exists()
        ) {
            return LoanRequestWorkflowVersion::DocumentWorkflowV2->value;
        }

        if (
            Schema::hasTable('loan_request_notification_events')
            && DB::table('loan_request_notification_events')
                ->where('loan_request_id', $row->id)
                ->exists()
        ) {
            return LoanRequestWorkflowVersion::DocumentWorkflowV2->value;
        }

        return LoanRequestWorkflowVersion::LegacyV1->value;
    }

    private function tryAddIndex(
        string $table,
        array $columns,
        string $name,
        bool $unique = false,
    ): void {
        try {
            Schema::table($table, function (Blueprint $blueprint) use (
                $columns,
                $name,
                $unique,
            ): void {
                if ($unique) {
                    $blueprint->unique($columns, $name);

                    return;
                }

                $blueprint->index($columns, $name);
            });
        } catch (\Throwable) {
        }
    }
};
