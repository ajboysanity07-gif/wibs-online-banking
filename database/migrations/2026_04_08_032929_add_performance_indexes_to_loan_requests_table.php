<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndexIfMissing(
            'loan_requests',
            ['status', 'submitted_at', 'created_at'],
            'idx_loan_requests_status_submitted_at_created_at',
        );
        $this->addIndexIfMissing(
            'loan_requests',
            ['status', 'loan_type_label_snapshot'],
            'idx_loan_requests_status_loan_type_label_snapshot',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists(
            'loan_requests',
            'idx_loan_requests_status_submitted_at_created_at',
        );
        $this->dropIndexIfExists(
            'loan_requests',
            'idx_loan_requests_status_loan_type_label_snapshot',
        );
    }

    private function addIndexIfMissing(
        string $table,
        array $columns,
        string $index,
        ?string $connection = null,
    ): void {
        $schema = $this->schema($connection);

        if (! $schema->hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($schema, $table, $index)
            || $this->indexExists($schema, $table, $columns)
        ) {
            return;
        }

        $schema->table($table, function (Blueprint $table) use ($columns, $index): void {
            $table->index($columns, $index);
        });
    }

    private function dropIndexIfExists(
        string $table,
        string $index,
        ?string $connection = null,
    ): void {
        $schema = $this->schema($connection);

        if (! $schema->hasTable($table)) {
            return;
        }

        if (! $this->indexExists($schema, $table, $index)) {
            return;
        }

        $schema->table($table, function (Blueprint $table) use ($index): void {
            $table->dropIndex($index);
        });
    }

    private function schema(?string $connection = null): Builder
    {
        $connection = $connection ?? (string) config('database.default');

        return Schema::connection($connection);
    }

    private function indexExists(
        Builder $schema,
        string $table,
        string|array $index,
    ): bool {
        if ($schema->getConnection()->getDriverName() === 'sqlsrv') {
            return $this->sqlServerIndexExists($schema, $table, $index);
        }

        return $schema->hasIndex($table, $index);
    }

    private function sqlServerIndexExists(
        Builder $schema,
        string $table,
        string|array $index,
    ): bool {
        $rows = $schema->getConnection()->select(
            'select idx.name as name, col.name as column_name, idxcol.key_ordinal as key_ordinal
            from sys.indexes as idx
            join sys.tables as tbl on idx.object_id = tbl.object_id
            join sys.schemas as scm on tbl.schema_id = scm.schema_id
            join sys.index_columns as idxcol on idx.object_id = idxcol.object_id and idx.index_id = idxcol.index_id
            join sys.columns as col on idxcol.object_id = col.object_id and idxcol.column_id = col.column_id
            where tbl.name = ? and scm.name = schema_name()
            order by idx.name, idxcol.key_ordinal',
            [$table],
        );

        if (is_string($index)) {
            foreach ($rows as $row) {
                if ($row->name === $index) {
                    return true;
                }
            }

            return false;
        }

        $indexedColumns = [];

        foreach ($rows as $row) {
            $indexedColumns[$row->name][] = $row->column_name;
        }

        foreach ($indexedColumns as $columns) {
            if ($columns === $index) {
                return true;
            }
        }

        return false;
    }
};
