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
            'wlnled',
            ['acctno', 'lnnumber', 'date_in'],
            'idx_wlnled_acctno_lnnumber_date_in',
        );
        $this->addIndexIfMissing(
            'wsavled',
            ['acctno', 'typecode', 'date_in'],
            'idx_wsavled_acctno_typecode_date_in',
        );
        $this->addIndexIfMissing(
            'wsavled',
            ['acctno', 'svnumber'],
            'idx_wsavled_acctno_svnumber',
        );
        $this->addIndexIfMissing(
            'wsvmaster',
            ['acctno', 'svnumber'],
            'idx_wsvmaster_acctno_svnumber',
        );
        $this->addIndexIfMissing(
            'wsvmaster',
            ['acctno', 'typecode', 'lastmove'],
            'idx_wsvmaster_acctno_typecode_lastmove',
        );
        $this->addIndexIfMissing(
            'wlnmaster',
            ['acctno', 'lnnumber'],
            'idx_wlnmaster_acctno_lnnumber',
        );
        $this->addIndexIfMissing(
            'user_profiles',
            ['status', 'user_id'],
            'idx_user_profiles_status_user_id',
        );
        $this->addIndexIfMissing(
            'admin_profiles',
            ['user_id'],
            'idx_admin_profiles_user_id',
        );
        $this->addIndexIfMissing(
            'appusers',
            ['created_at'],
            'idx_appusers_created_at',
        );
        $this->addIndexIfMissing(
            'appusers',
            ['acctno'],
            'idx_appusers_acctno',
        );
        $this->addIndexIfMissing(
            'wmaster',
            ['acctno'],
            'idx_wmaster_acctno',
        );

        $schema = $this->schema();

        if ($schema->hasTable('wlnmaster') && $schema->hasColumn('wlnmaster', 'acctno')) {
            if ($schema->hasColumn('wlnmaster', 'lastmove')) {
                $this->addIndexIfMissing(
                    'wlnmaster',
                    ['acctno', 'lastmove'],
                    'idx_wlnmaster_acctno_lastmove',
                );
            } elseif ($schema->hasColumn('wlnmaster', 'dateopen')) {
                $this->addIndexIfMissing(
                    'wlnmaster',
                    ['acctno', 'dateopen'],
                    'idx_wlnmaster_acctno_dateopen',
                );
            }
        }

        /**
         * Amortsched may live on the rbank2 connection; fall back to default if absent.
         */
        $amortschedConnection = $this->scheduleConnectionName();

        if ($amortschedConnection !== null
            && $this->schema($amortschedConnection)->hasTable('Amortsched')
        ) {
            $this->addIndexIfMissing(
                'Amortsched',
                ['lnnumber', 'Date_pay'],
                'idx_amortsched_lnnumber_date_pay',
                $amortschedConnection,
            );
        } else {
            $this->addIndexIfMissing(
                'Amortsched',
                ['lnnumber', 'Date_pay'],
                'idx_amortsched_lnnumber_date_pay',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists(
            'wlnled',
            'idx_wlnled_acctno_lnnumber_date_in',
        );
        $this->dropIndexIfExists(
            'wsavled',
            'idx_wsavled_acctno_typecode_date_in',
        );
        $this->dropIndexIfExists(
            'wsavled',
            'idx_wsavled_acctno_svnumber',
        );
        $this->dropIndexIfExists(
            'wsvmaster',
            'idx_wsvmaster_acctno_svnumber',
        );
        $this->dropIndexIfExists(
            'wsvmaster',
            'idx_wsvmaster_acctno_typecode_lastmove',
        );
        $this->dropIndexIfExists(
            'wlnmaster',
            'idx_wlnmaster_acctno_lnnumber',
        );
        $this->dropIndexIfExists(
            'wlnmaster',
            'idx_wlnmaster_acctno_lastmove',
        );
        $this->dropIndexIfExists(
            'wlnmaster',
            'idx_wlnmaster_acctno_dateopen',
        );
        $this->dropIndexIfExists(
            'user_profiles',
            'idx_user_profiles_status_user_id',
        );
        $this->dropIndexIfExists(
            'admin_profiles',
            'idx_admin_profiles_user_id',
        );
        $this->dropIndexIfExists(
            'appusers',
            'idx_appusers_created_at',
        );
        $this->dropIndexIfExists(
            'appusers',
            'idx_appusers_acctno',
        );
        $this->dropIndexIfExists(
            'wmaster',
            'idx_wmaster_acctno',
        );

        $amortschedConnection = $this->scheduleConnectionName();

        if ($amortschedConnection !== null
            && $this->schema($amortschedConnection)->hasTable('Amortsched')
        ) {
            $this->dropIndexIfExists(
                'Amortsched',
                'idx_amortsched_lnnumber_date_pay',
                $amortschedConnection,
            );
        } else {
            $this->dropIndexIfExists(
                'Amortsched',
                'idx_amortsched_lnnumber_date_pay',
            );
        }
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

    private function scheduleConnectionName(): ?string
    {
        $connections = config('database.connections');

        if (is_array($connections) && array_key_exists('rbank2', $connections)) {
            return 'rbank2';
        }

        return null;
    }
};
