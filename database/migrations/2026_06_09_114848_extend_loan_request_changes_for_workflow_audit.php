<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_request_changes')) {
            return;
        }

        $hasFromStatus = Schema::hasColumn('loan_request_changes', 'from_status');
        $hasToStatus = Schema::hasColumn('loan_request_changes', 'to_status');
        $hasMetadataJson = Schema::hasColumn('loan_request_changes', 'metadata_json');

        Schema::table('loan_request_changes', function (Blueprint $table) use ($hasFromStatus, $hasToStatus, $hasMetadataJson) {
            if (! $hasFromStatus) {
                $table->string('from_status')->nullable()->after('action');
            }

            if (! $hasToStatus) {
                $table->string('to_status')->nullable()->after('from_status');
            }

            if (! $hasMetadataJson) {
                $table->json('metadata_json')->nullable()->after('changed_fields_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_request_changes')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('loan_request_changes', 'metadata_json') ? 'metadata_json' : null,
            Schema::hasColumn('loan_request_changes', 'to_status') ? 'to_status' : null,
            Schema::hasColumn('loan_request_changes', 'from_status') ? 'from_status' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('loan_request_changes', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
