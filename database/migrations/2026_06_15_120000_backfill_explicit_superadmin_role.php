<?php

use App\Models\AdminProfile;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('roles')
            || ! Schema::hasTable('user_roles')
            || ! Schema::hasTable('admin_profiles')
        ) {
            return;
        }

        Role::ensureWorkflowDefaults();

        $superadminRoleId = Role::query()
            ->where('name', Role::SUPERADMIN)
            ->value('id');

        if ($superadminRoleId === null) {
            return;
        }

        $timestamps = [
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('admin_profiles')
            ->where('access_level', AdminProfile::ACCESS_LEVEL_SUPERADMIN)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->chunk(200)
            ->each(function ($userIds) use ($superadminRoleId, $timestamps): void {
                $rows = collect($userIds)
                    ->map(fn ($userId): array => [
                        'user_id' => $userId,
                        'role_id' => $superadminRoleId,
                        ...$timestamps,
                    ])
                    ->all();

                DB::table('user_roles')->upsert(
                    $rows,
                    ['user_id', 'role_id'],
                    ['updated_at'],
                );
            });
    }

    public function down(): void
    {
        // Intentionally left blank to preserve explicit role history.
    }
};
