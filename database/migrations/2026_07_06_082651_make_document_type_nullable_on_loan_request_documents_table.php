<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy columns from the pre-workflow-v2 document schema. None of them
     * are read or written by the current document workflow code, but they
     * were still NOT NULL, so inserting a new-style row (which never sets
     * them) violated the NOT NULL constraint.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loan_request_documents')) {
            return;
        }

        Schema::table('loan_request_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_request_documents', 'document_type')) {
                $table->string('document_type', 255)->nullable()->change();
            }

            if (Schema::hasColumn('loan_request_documents', 'status')) {
                $table->string('status', 255)->nullable()->change();
            }

            if (Schema::hasColumn('loan_request_documents', 'version')) {
                $table->integer('version')->nullable()->change();
            }
        });

        $this->dropLegacyDocumentTypeUniqueIndex();
    }

    /**
     * SQL Server treats multiple NULLs in a unique index as duplicates
     * (unlike MySQL/Postgres), so this legacy unique index on
     * (loan_request_id, document_type) rejects every row after the first
     * once document_type is always NULL. The replacement unique index on
     * (loan_request_id, document_key) already enforces uniqueness for the
     * current schema, so this one is just dropped.
     *
     * This database runs at SQL Server 2008 compatibility level, where
     * Schema::hasIndex()'s STRING_AGG-based query fails, so existence is
     * checked directly against sys.indexes instead. Guarded to sqlsrv only
     * since the test suite runs against sqlite, which never had this index.
     */
    private function dropLegacyDocumentTypeUniqueIndex(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        $indexName = 'loan_request_documents_loan_request_document_type_unique';

        $exists = DB::selectOne(
            'select 1 as found from sys.indexes where object_id = object_id(?) and name = ?',
            ['loan_request_documents', $indexName],
        );

        if ($exists === null) {
            return;
        }

        Schema::table('loan_request_documents', function (Blueprint $table) use ($indexName): void {
            $table->dropUnique($indexName);
        });
    }

    public function down(): void
    {
        // Irreversible — rows written since this ran may have NULL in these
        // columns, which would violate a restored NOT NULL constraint, and
        // the dropped legacy unique index cannot be safely recreated once
        // multiple NULL document_type rows exist.
    }
};
