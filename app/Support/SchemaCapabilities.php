<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class SchemaCapabilities
{
    /**
     * Memoize schema lookups per request to avoid repeated metadata checks.
     *
     * @var array<string, bool>
     */
    private array $tables = [];

    /**
     * @var array<string, bool>
     */
    private array $columns = [];

    public function hasTable(string $table, ?string $connection = null): bool
    {
        $key = $this->tableKey($table, $connection);

        if (array_key_exists($key, $this->tables)) {
            return $this->tables[$key];
        }

        $exists = $connection !== null
            ? Schema::connection($connection)->hasTable($table)
            : Schema::hasTable($table);

        $this->tables[$key] = $exists;

        return $exists;
    }

    public function hasColumn(
        string $table,
        string $column,
        ?string $connection = null,
    ): bool {
        $key = $this->columnKey($table, $column, $connection);

        if (array_key_exists($key, $this->columns)) {
            return $this->columns[$key];
        }

        $exists = $connection !== null
            ? Schema::connection($connection)->hasColumn($table, $column)
            : Schema::hasColumn($table, $column);

        $this->columns[$key] = $exists;

        return $exists;
    }

    private function tableKey(string $table, ?string $connection = null): string
    {
        return ($connection ?? 'default').'|'.$table;
    }

    private function columnKey(
        string $table,
        string $column,
        ?string $connection = null,
    ): string {
        return ($connection ?? 'default').'|'.$table.'|'.$column;
    }
}
