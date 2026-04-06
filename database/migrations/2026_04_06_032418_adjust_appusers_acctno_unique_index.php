<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = $this->schema();

        if (! $schema->hasTable('appusers')) {
            return;
        }

        $connection = $schema->getConnection();

        if ($connection->getDriverName() !== 'sqlsrv') {
            return;
        }

        $this->dropSqlServerIndexIfExists($connection->getName());

        $connection->statement(
            'CREATE UNIQUE INDEX [appusers_acctno_unique] ON [appusers] ([acctno]) WHERE [acctno] IS NOT NULL',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = $this->schema();

        if (! $schema->hasTable('appusers')) {
            return;
        }

        $connection = $schema->getConnection();

        if ($connection->getDriverName() !== 'sqlsrv') {
            return;
        }

        $this->dropSqlServerIndexIfExists($connection->getName());

        $schema->table('appusers', function (Blueprint $table): void {
            $table->unique('acctno');
        });
    }

    private function schema(): Builder
    {
        return Schema::connection((string) config('database.default'));
    }

    private function dropSqlServerIndexIfExists(string $connection): void
    {
        DB::connection($connection)->statement(
            "IF EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = 'appusers_acctno_unique'
                AND object_id = OBJECT_ID('appusers')
            )
            DROP INDEX [appusers_acctno_unique] ON [appusers]",
        );
    }
};
