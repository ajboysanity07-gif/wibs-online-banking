<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'user_roles_one_editable_role_per_user';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = $this->schema();

        if (! $schema->hasTable('roles') || ! $schema->hasTable('user_roles')) {
            return;
        }

        $editableRoleIds = Role::query()
            ->whereIn('name', Role::editableStaffNames())
            ->pluck('id', 'name');

        if ($editableRoleIds->isEmpty()) {
            return;
        }

        $this->backfillSingleEditableRolePerUser($editableRoleIds);

        $connection = $schema->getConnection();

        if ($connection->getDriverName() !== 'sqlsrv') {
            return;
        }

        $this->dropSqlServerIndexIfExists($connection->getName());

        $idList = $editableRoleIds->values()->implode(', ');

        $connection->statement(
            'CREATE UNIQUE INDEX ['.self::INDEX_NAME.'] ON [user_roles] ([user_id]) WHERE [role_id] IN ('.$idList.')',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = $this->schema();

        if (! $schema->hasTable('user_roles')) {
            return;
        }

        $connection = $schema->getConnection();

        if ($connection->getDriverName() !== 'sqlsrv') {
            return;
        }

        $this->dropSqlServerIndexIfExists($connection->getName());
    }

    /**
     * One-time cleanup: some staff accounts were assigned more than one
     * editable role before this constraint existed. Keep the
     * highest-precedence role per user and drop the rest. Member role rows
     * are untouched since they're excluded from $editableRoleIds.
     *
     * @param  \Illuminate\Support\Collection<string, int>  $editableRoleIds
     */
    private function backfillSingleEditableRolePerUser($editableRoleIds): void
    {
        $precedence = array_values(array_filter([
            $editableRoleIds->get(Role::SUPERADMIN),
            $editableRoleIds->get(Role::LOAN_MANAGER),
            $editableRoleIds->get(Role::LOAN_PROCESSOR),
        ]));

        if ($precedence === []) {
            return;
        }

        DB::transaction(function () use ($editableRoleIds, $precedence): void {
            $usersWithMultipleRoles = DB::table('user_roles')
                ->whereIn('role_id', $editableRoleIds->values())
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            foreach ($usersWithMultipleRoles as $userId) {
                $heldRoleIds = DB::table('user_roles')
                    ->where('user_id', $userId)
                    ->whereIn('role_id', $editableRoleIds->values())
                    ->pluck('role_id')
                    ->map(static fn (mixed $roleId): int => (int) $roleId)
                    ->all();

                $keepRoleId = null;

                foreach ($precedence as $roleId) {
                    if (in_array($roleId, $heldRoleIds, true)) {
                        $keepRoleId = $roleId;
                        break;
                    }
                }

                if ($keepRoleId === null) {
                    continue;
                }

                DB::table('user_roles')
                    ->where('user_id', $userId)
                    ->whereIn('role_id', $editableRoleIds->values())
                    ->where('role_id', '!=', $keepRoleId)
                    ->delete();
            }
        });
    }

    private function schema(): Builder
    {
        return Schema::connection((string) config('database.default'));
    }

    private function dropSqlServerIndexIfExists(string $connection): void
    {
        DB::connection($connection)->statement(
            'IF EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = \''.self::INDEX_NAME.'\'
                AND object_id = OBJECT_ID(\'user_roles\')
            )
            DROP INDEX ['.self::INDEX_NAME.'] ON [user_roles]',
        );
    }
};
