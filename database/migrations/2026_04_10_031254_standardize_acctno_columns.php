<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMN = 'acctno';

    private const TARGET_LENGTH = 6;

    private const SOURCE_LENGTH = 255;

    private const PROPERTY_NAME = 'laravel_acctno_original_length';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = $this->schema();

        if ($schema->getConnection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        $targets = $this->resolveTargets($schema, self::SOURCE_LENGTH);

        if ($targets === []) {
            return;
        }

        $foreignKeys = $this->dropForeignKeys(
            $schema,
            array_keys($targets),
            self::COLUMN,
        );

        foreach ($targets as $table => $column) {
            $indexes = $this->dropIndexesForColumn(
                $schema,
                $table,
                self::COLUMN,
            );

            $this->alterAcctnoColumn(
                $schema,
                $table,
                $column,
                self::TARGET_LENGTH,
            );

            $this->storeOriginalLength(
                $schema,
                $table,
                self::COLUMN,
                self::SOURCE_LENGTH,
            );

            $this->restoreIndexes($schema, $table, $indexes);
        }

        $this->restoreForeignKeys($schema, $foreignKeys);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = $this->schema();

        if ($schema->getConnection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        $targets = $this->resolveTargetsForDown($schema);

        if ($targets === []) {
            return;
        }

        $foreignKeys = $this->dropForeignKeys(
            $schema,
            array_keys($targets),
            self::COLUMN,
        );

        foreach ($targets as $table => $originalLength) {
            $column = $this->columnDefinition($schema, $table, self::COLUMN);

            if ($column === null) {
                continue;
            }

            $shouldAlter = $column['type_name'] === 'nvarchar'
                && $column['max_length'] === self::TARGET_LENGTH * 2
                && $originalLength !== self::TARGET_LENGTH;

            if ($shouldAlter) {
                $indexes = $this->dropIndexesForColumn(
                    $schema,
                    $table,
                    self::COLUMN,
                );

                $this->alterAcctnoColumn(
                    $schema,
                    $table,
                    $column,
                    $originalLength,
                );

                $this->restoreIndexes($schema, $table, $indexes);
            }

            $this->dropOriginalLength($schema, $table, self::COLUMN);
        }

        $this->restoreForeignKeys($schema, $foreignKeys);
    }

    private function schema(): Builder
    {
        return Schema::connection((string) config('database.default'));
    }

    /**
     * @return array<string, array{max_length: int, is_nullable: bool, collation_name: ?string, type_name: string}>
     */
    private function resolveTargets(Builder $schema, int $sourceLength): array
    {
        $targets = [];

        foreach ($this->acctnoTables() as $table) {
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, self::COLUMN)) {
                continue;
            }

            $column = $this->columnDefinition($schema, $table, self::COLUMN);

            if ($column === null) {
                continue;
            }

            if (! $this->shouldShrink($column, $sourceLength)) {
                continue;
            }

            $targets[$table] = $column;
        }

        return $targets;
    }

    /**
     * @return array<string, int>
     */
    private function resolveTargetsForDown(Builder $schema): array
    {
        $targets = [];

        foreach ($this->acctnoTables() as $table) {
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, self::COLUMN)) {
                continue;
            }

            $originalLength = $this->originalLength($schema, $table, self::COLUMN);

            if ($originalLength === null) {
                continue;
            }

            $targets[$table] = $originalLength;
        }

        return $targets;
    }

    /**
     * @return list<string>
     */
    private function acctnoTables(): array
    {
        return [
            'appusers',
            'loan_requests',
            'wmaster',
            'wlnmaster',
            'wlnled',
            'wsvmaster',
            'wsavled',
        ];
    }

    /**
     * @return array{max_length: int, is_nullable: bool, collation_name: ?string, type_name: string}|null
     */
    private function columnDefinition(Builder $schema, string $table, string $column): ?array
    {
        $rows = $schema->getConnection()->select(
            'select col.max_length, col.is_nullable, col.collation_name, typ.name as type_name
             from sys.columns as col
             join sys.types as typ on col.user_type_id = typ.user_type_id
             where col.object_id = object_id(?) and col.name = ?',
            [$table, $column],
        );

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];

        return [
            'max_length' => (int) $row->max_length,
            'is_nullable' => (bool) $row->is_nullable,
            'collation_name' => $row->collation_name !== null ? (string) $row->collation_name : null,
            'type_name' => (string) $row->type_name,
        ];
    }

    /**
     * @param  array{max_length: int, is_nullable: bool, collation_name: ?string, type_name: string}  $column
     */
    private function shouldShrink(array $column, int $sourceLength): bool
    {
        if ($column['type_name'] !== 'nvarchar') {
            return false;
        }

        return $column['max_length'] === $sourceLength * 2;
    }

    /**
     * @param  array{max_length: int, is_nullable: bool, collation_name: ?string, type_name: string}  $column
     */
    private function alterAcctnoColumn(
        Builder $schema,
        string $table,
        array $column,
        int $length,
    ): void {
        $nullable = $column['is_nullable'] ? 'NULL' : 'NOT NULL';
        $collation = $column['collation_name'];
        $collationSql = $collation !== null ? ' COLLATE '.$collation : '';

        $schema->getConnection()->statement(
            sprintf(
                'ALTER TABLE [%s] ALTER COLUMN [%s] NVARCHAR(%d)%s %s',
                $table,
                self::COLUMN,
                $length,
                $collationSql,
                $nullable,
            ),
        );
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     type_desc: string,
     *     is_primary_key: bool,
     *     is_unique_constraint: bool,
     *     is_unique: bool,
     *     has_filter: bool,
     *     filter_definition: ?string,
     *     is_disabled: bool,
     *     key_columns: array<int, array{name: string, descending: bool}>,
     *     included_columns: array<int, string>
     * }>
     */
    private function indexesForColumn(Builder $schema, string $table, string $column): array
    {
        $rows = $schema->getConnection()->select(
            'select idx.name as name,
                    idx.type_desc as type_desc,
                    idx.is_primary_key as is_primary_key,
                    idx.is_unique_constraint as is_unique_constraint,
                    idx.is_unique as is_unique,
                    idx.has_filter as has_filter,
                    idx.filter_definition as filter_definition,
                    idx.is_disabled as is_disabled,
                    idxcol.key_ordinal as key_ordinal,
                    idxcol.is_included_column as is_included_column,
                    idxcol.is_descending_key as is_descending_key,
                    idxcol.index_column_id as index_column_id,
                    col.name as column_name
             from sys.indexes as idx
             join sys.tables as tbl on idx.object_id = tbl.object_id
             join sys.schemas as scm on tbl.schema_id = scm.schema_id
             join sys.index_columns as idxcol on idx.object_id = idxcol.object_id and idx.index_id = idxcol.index_id
             join sys.columns as col on idxcol.object_id = col.object_id and idxcol.column_id = col.column_id
             where tbl.name = ? and scm.name = schema_name() and idx.is_hypothetical = 0
             order by idx.name, idxcol.index_column_id',
            [$table],
        );

        $indexes = [];

        foreach ($rows as $row) {
            $name = (string) $row->name;

            if (! array_key_exists($name, $indexes)) {
                $indexes[$name] = [
                    'name' => $name,
                    'type_desc' => (string) $row->type_desc,
                    'is_primary_key' => (bool) $row->is_primary_key,
                    'is_unique_constraint' => (bool) $row->is_unique_constraint,
                    'is_unique' => (bool) $row->is_unique,
                    'has_filter' => (bool) $row->has_filter,
                    'filter_definition' => $row->filter_definition !== null ? (string) $row->filter_definition : null,
                    'is_disabled' => (bool) $row->is_disabled,
                    'key_columns' => [],
                    'included_columns' => [],
                ];
            }

            if ((bool) $row->is_included_column) {
                $indexes[$name]['included_columns'][] = (string) $row->column_name;

                continue;
            }

            $indexes[$name]['key_columns'][(int) $row->key_ordinal] = [
                'name' => (string) $row->column_name,
                'descending' => (bool) $row->is_descending_key,
            ];
        }

        $filtered = [];

        foreach ($indexes as $index) {
            ksort($index['key_columns']);
            $keyColumns = array_values($index['key_columns']);
            $index['key_columns'] = $keyColumns;

            $hasTarget = false;

            foreach ($keyColumns as $keyColumn) {
                if ($keyColumn['name'] === $column) {
                    $hasTarget = true;
                    break;
                }
            }

            if (! $hasTarget) {
                continue;
            }

            $filtered[$index['name']] = $index;
        }

        return $filtered;
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     type_desc: string,
     *     is_primary_key: bool,
     *     is_unique_constraint: bool,
     *     is_unique: bool,
     *     has_filter: bool,
     *     filter_definition: ?string,
     *     is_disabled: bool,
     *     key_columns: array<int, array{name: string, descending: bool}>,
     *     included_columns: array<int, string>
     * }>
     */
    private function dropIndexesForColumn(Builder $schema, string $table, string $column): array
    {
        $indexes = $this->indexesForColumn($schema, $table, $column);

        if ($indexes === []) {
            return [];
        }

        $this->dropIndexes($schema, $table, $indexes);

        return $indexes;
    }

    /**
     * @param  array<string, array{
     *     name: string,
     *     type_desc: string,
     *     is_primary_key: bool,
     *     is_unique_constraint: bool,
     *     is_unique: bool,
     *     has_filter: bool,
     *     filter_definition: ?string,
     *     is_disabled: bool,
     *     key_columns: array<int, array{name: string, descending: bool}>,
     *     included_columns: array<int, string>
     * }>  $indexes
     */
    private function dropIndexes(
        Builder $schema,
        string $table,
        array $indexes,
    ): void {
        $connection = $schema->getConnection();

        foreach ($indexes as $index) {
            $name = $index['name'];

            if ($index['is_primary_key'] || $index['is_unique_constraint']) {
                $connection->statement(
                    "IF EXISTS (
                        SELECT 1
                        FROM sys.key_constraints
                        WHERE name = ?
                        AND parent_object_id = OBJECT_ID(?)
                    )
                    ALTER TABLE [{$table}] DROP CONSTRAINT [{$name}]",
                    [$name, $table],
                );

                continue;
            }

            $connection->statement(
                "IF EXISTS (
                    SELECT 1
                    FROM sys.indexes
                    WHERE name = ?
                    AND object_id = OBJECT_ID(?)
                )
                DROP INDEX [{$name}] ON [{$table}]",
                [$name, $table],
            );
        }
    }

    /**
     * @param  array<string, array{
     *     name: string,
     *     type_desc: string,
     *     is_primary_key: bool,
     *     is_unique_constraint: bool,
     *     is_unique: bool,
     *     has_filter: bool,
     *     filter_definition: ?string,
     *     is_disabled: bool,
     *     key_columns: array<int, array{name: string, descending: bool}>,
     *     included_columns: array<int, string>
     * }>  $indexes
     */
    private function restoreIndexes(
        Builder $schema,
        string $table,
        array $indexes,
    ): void {
        $connection = $schema->getConnection();

        foreach ($indexes as $index) {
            $name = $index['name'];
            $columns = $this->formatIndexColumns($index['key_columns']);
            $type = strtoupper($index['type_desc']) === 'CLUSTERED' ? 'CLUSTERED' : 'NONCLUSTERED';

            if ($index['is_primary_key']) {
                $connection->statement(
                    "ALTER TABLE [{$table}] ADD CONSTRAINT [{$name}] PRIMARY KEY {$type} ({$columns})",
                );

                continue;
            }

            if ($index['is_unique_constraint']) {
                $connection->statement(
                    "ALTER TABLE [{$table}] ADD CONSTRAINT [{$name}] UNIQUE {$type} ({$columns})",
                );

                continue;
            }

            $unique = $index['is_unique'] ? 'UNIQUE ' : '';
            $include = $index['included_columns'] !== []
                ? ' INCLUDE ('.$this->formatColumnList($index['included_columns']).')'
                : '';
            $filter = ($index['has_filter'] && $index['filter_definition'] !== null)
                ? ' WHERE '.$index['filter_definition']
                : '';

            $connection->statement(
                "CREATE {$unique}{$type} INDEX [{$name}] ON [{$table}] ({$columns}){$include}{$filter}",
            );

            if ($index['is_disabled']) {
                $connection->statement(
                    "ALTER INDEX [{$name}] ON [{$table}] DISABLE",
                );
            }
        }
    }

    /**
     * @param  array<int, array{name: string, descending: bool}>  $columns
     */
    private function formatIndexColumns(array $columns): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $direction = $column['descending'] ? ' DESC' : '';
            $parts[] = '['.$column['name'].']'.$direction;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function formatColumnList(array $columns): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $parts[] = '['.$column.']';
        }

        return implode(', ', $parts);
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     parent_schema: string,
     *     parent_table: string,
     *     referenced_schema: string,
     *     referenced_table: string,
     *     parent_columns: array<int, string>,
     *     referenced_columns: array<int, string>,
     *     delete_action: string,
     *     update_action: string,
     *     is_disabled: bool,
     *     not_for_replication: bool
     * }>
     */
    private function foreignKeysForColumn(
        Builder $schema,
        array $tables,
        string $column,
    ): array {
        if ($tables === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $bindings = array_merge($tables, [$column], $tables, [$column]);

        $rows = $schema->getConnection()->select(
            "select fk.name as name,
                    parent_scm.name as parent_schema,
                    parent_tbl.name as parent_table,
                    ref_scm.name as referenced_schema,
                    ref_tbl.name as referenced_table,
                    parent_col.name as parent_column,
                    ref_col.name as referenced_column,
                    fk.delete_referential_action_desc as delete_action,
                    fk.update_referential_action_desc as update_action,
                    fk.is_disabled as is_disabled,
                    fk.is_not_for_replication as not_for_replication,
                    fkc.constraint_column_id as constraint_column_id
             from sys.foreign_keys as fk
             join sys.foreign_key_columns as fkc on fk.object_id = fkc.constraint_object_id
             join sys.tables as parent_tbl on fkc.parent_object_id = parent_tbl.object_id
             join sys.schemas as parent_scm on parent_tbl.schema_id = parent_scm.schema_id
             join sys.columns as parent_col on fkc.parent_object_id = parent_col.object_id and fkc.parent_column_id = parent_col.column_id
             join sys.tables as ref_tbl on fkc.referenced_object_id = ref_tbl.object_id
             join sys.schemas as ref_scm on ref_tbl.schema_id = ref_scm.schema_id
             join sys.columns as ref_col on fkc.referenced_object_id = ref_col.object_id and fkc.referenced_column_id = ref_col.column_id
             where (parent_tbl.name in ({$placeholders}) and parent_col.name = ?)
                or (ref_tbl.name in ({$placeholders}) and ref_col.name = ?)
             order by fk.name, fkc.constraint_column_id",
            $bindings,
        );

        $foreignKeys = [];

        foreach ($rows as $row) {
            $name = (string) $row->name;

            if (! array_key_exists($name, $foreignKeys)) {
                $foreignKeys[$name] = [
                    'name' => $name,
                    'parent_schema' => (string) $row->parent_schema,
                    'parent_table' => (string) $row->parent_table,
                    'referenced_schema' => (string) $row->referenced_schema,
                    'referenced_table' => (string) $row->referenced_table,
                    'parent_columns' => [],
                    'referenced_columns' => [],
                    'delete_action' => (string) $row->delete_action,
                    'update_action' => (string) $row->update_action,
                    'is_disabled' => (bool) $row->is_disabled,
                    'not_for_replication' => (bool) $row->not_for_replication,
                ];
            }

            $foreignKeys[$name]['parent_columns'][] = (string) $row->parent_column;
            $foreignKeys[$name]['referenced_columns'][] = (string) $row->referenced_column;
        }

        return $foreignKeys;
    }

    /**
     * @param  array<string, array{
     *     name: string,
     *     parent_schema: string,
     *     parent_table: string,
     *     referenced_schema: string,
     *     referenced_table: string,
     *     parent_columns: array<int, string>,
     *     referenced_columns: array<int, string>,
     *     delete_action: string,
     *     update_action: string,
     *     is_disabled: bool,
     *     not_for_replication: bool
     * }>  $foreignKeys
     */
    private function dropForeignKeys(
        Builder $schema,
        array $tables,
        string $column,
    ): array {
        $foreignKeys = $this->foreignKeysForColumn($schema, $tables, $column);

        if ($foreignKeys === []) {
            return [];
        }

        $connection = $schema->getConnection();

        foreach ($foreignKeys as $foreignKey) {
            $name = $foreignKey['name'];
            $parentTable = $foreignKey['parent_table'];

            $connection->statement(
                "IF EXISTS (
                    SELECT 1
                    FROM sys.foreign_keys
                    WHERE name = ?
                    AND parent_object_id = OBJECT_ID(?)
                )
                ALTER TABLE [{$foreignKey['parent_schema']}].[{$parentTable}] DROP CONSTRAINT [{$name}]",
                [$name, $parentTable],
            );
        }

        return $foreignKeys;
    }

    /**
     * @param  array<string, array{
     *     name: string,
     *     parent_schema: string,
     *     parent_table: string,
     *     referenced_schema: string,
     *     referenced_table: string,
     *     parent_columns: array<int, string>,
     *     referenced_columns: array<int, string>,
     *     delete_action: string,
     *     update_action: string,
     *     is_disabled: bool,
     *     not_for_replication: bool
     * }>  $foreignKeys
     */
    private function restoreForeignKeys(Builder $schema, array $foreignKeys): void
    {
        if ($foreignKeys === []) {
            return;
        }

        $connection = $schema->getConnection();

        foreach ($foreignKeys as $foreignKey) {
            $name = $foreignKey['name'];
            $parentColumns = $this->formatColumnList($foreignKey['parent_columns']);
            $refColumns = $this->formatColumnList($foreignKey['referenced_columns']);

            $notForReplication = $foreignKey['not_for_replication'] ? ' NOT FOR REPLICATION' : '';
            $deleteAction = $this->foreignKeyAction($foreignKey['delete_action'], 'DELETE');
            $updateAction = $this->foreignKeyAction($foreignKey['update_action'], 'UPDATE');

            $connection->statement(
                "ALTER TABLE [{$foreignKey['parent_schema']}].[{$foreignKey['parent_table']}] WITH CHECK
                 ADD CONSTRAINT [{$name}] FOREIGN KEY{$notForReplication} ({$parentColumns})
                 REFERENCES [{$foreignKey['referenced_schema']}].[{$foreignKey['referenced_table']}] ({$refColumns}){$deleteAction}{$updateAction}",
            );

            if ($foreignKey['is_disabled']) {
                $connection->statement(
                    "ALTER TABLE [{$foreignKey['parent_schema']}].[{$foreignKey['parent_table']}] NOCHECK CONSTRAINT [{$name}]",
                );
            }
        }
    }

    private function foreignKeyAction(string $action, string $type): string
    {
        $normalized = strtoupper($action);

        if ($normalized === 'CASCADE') {
            return " ON {$type} CASCADE";
        }

        if ($normalized === 'SET_NULL') {
            return " ON {$type} SET NULL";
        }

        if ($normalized === 'SET_DEFAULT') {
            return " ON {$type} SET DEFAULT";
        }

        return '';
    }

    private function storeOriginalLength(
        Builder $schema,
        string $table,
        string $column,
        int $length,
    ): void {
        $connection = $schema->getConnection();
        $property = $this->sqlServerStringLiteral(self::PROPERTY_NAME);
        $tableLiteral = $this->sqlServerStringLiteral($table);
        $columnLiteral = $this->sqlServerStringLiteral($column);
        $valueLiteral = (int) $length;

        $connection->statement(
            "IF NOT EXISTS (
                SELECT 1
                FROM sys.extended_properties
                WHERE name = {$property}
                AND class = 1
                AND major_id = OBJECT_ID({$tableLiteral})
                AND minor_id = COLUMNPROPERTY(OBJECT_ID({$tableLiteral}), {$columnLiteral}, 'ColumnId')
            )
            EXEC sp_addextendedproperty
                @name = {$property},
                @value = {$valueLiteral},
                @level0type = N'SCHEMA',
                @level0name = SCHEMA_NAME(),
                @level1type = N'TABLE',
                @level1name = {$tableLiteral},
                @level2type = N'COLUMN',
                @level2name = {$columnLiteral};",
        );
    }

    private function originalLength(
        Builder $schema,
        string $table,
        string $column,
    ): ?int {
        $rows = $schema->getConnection()->select(
            'select cast(value as int) as length
             from sys.extended_properties
             where name = ?
             and class = 1
             and major_id = OBJECT_ID(?)
             and minor_id = COLUMNPROPERTY(OBJECT_ID(?), ?, \'ColumnId\')',
            [self::PROPERTY_NAME, $table, $table, $column],
        );

        if ($rows === []) {
            return null;
        }

        return (int) $rows[0]->length;
    }

    private function sqlServerStringLiteral(string $value): string
    {
        return "N'".str_replace("'", "''", $value)."'";
    }

    private function dropOriginalLength(
        Builder $schema,
        string $table,
        string $column,
    ): void {
        $connection = $schema->getConnection();
        $property = $this->sqlServerStringLiteral(self::PROPERTY_NAME);
        $tableLiteral = $this->sqlServerStringLiteral($table);
        $columnLiteral = $this->sqlServerStringLiteral($column);

        $connection->statement(
            "IF EXISTS (
                SELECT 1
                FROM sys.extended_properties
                WHERE name = {$property}
                AND class = 1
                AND major_id = OBJECT_ID({$tableLiteral})
                AND minor_id = COLUMNPROPERTY(OBJECT_ID({$tableLiteral}), {$columnLiteral}, 'ColumnId')
            )
            EXEC sp_dropextendedproperty
                @name = {$property},
                @level0type = N'SCHEMA',
                @level0name = SCHEMA_NAME(),
                @level1type = N'TABLE',
                @level1name = {$tableLiteral},
                @level2type = N'COLUMN',
                @level2name = {$columnLiteral};",
        );
    }
};
