<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SchemaCapabilities
{
    /**
     * Schema metadata (table/column existence) changes only on deploy, but this
     * class is queried on nearly every request. The remote SQL Server sits behind
     * a Tailscale tunnel, so every uncached hasTable()/hasColumn() call is a slow
     * round-trip. Results are cached process-wide (not just per-request) via the
     * default cache store; run `php artisan cache:clear` after a migration that
     * adds/removes a table or column this class checks.
     *
     * @var array<string, bool>
     */
    private array $tables = [];

    /**
     * @var array<string, bool>
     */
    private array $columns = [];

    private const TTL_SECONDS = 21600; // 6 hours; schema is effectively static between deploys.

    public function hasTable(string $table, ?string $connection = null): bool
    {
        $key = $this->tableKey($table, $connection);

        if (array_key_exists($key, $this->tables)) {
            return $this->tables[$key];
        }

        $exists = Cache::remember(
            "schema_capabilities:table:{$key}",
            self::TTL_SECONDS,
            fn () => $connection !== null
                ? Schema::connection($connection)->hasTable($table)
                : Schema::hasTable($table),
        );

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

        $exists = Cache::remember(
            "schema_capabilities:column:{$key}",
            self::TTL_SECONDS,
            fn () => $connection !== null
                ? Schema::connection($connection)->hasColumn($table, $column)
                : Schema::hasColumn($table, $column),
        );

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
