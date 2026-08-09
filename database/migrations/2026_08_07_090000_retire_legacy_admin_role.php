<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
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

        if (
            ! $schema->hasTable('admin_profiles')
            || ! $schema->hasTable('roles')
            || ! $schema->hasTable('user_roles')
        ) {
            return;
        }

        DB::transaction(function (): void {
            AdminProfile::query()
                ->where('access_level', AdminProfile::ACCESS_LEVEL_ADMIN)
                ->update(['access_level' => AdminProfile::ACCESS_LEVEL_SUPERADMIN]);

            $adminRoleId = Role::query()->where('name', Role::ADMIN)->value('id');
            $superadminRoleId = Role::query()->where('name', Role::SUPERADMIN)->value('id');

            if ($adminRoleId === null) {
                return;
            }

            if ($superadminRoleId !== null) {
                AppUser::query()
                    ->whereHas('roles', fn ($q) => $q->where('roles.id', $adminRoleId))
                    ->orderBy('user_id')
                    ->chunkById(200, function ($users) use ($superadminRoleId): void {
                        foreach ($users as $user) {
                            $user->roles()->syncWithoutDetaching([$superadminRoleId]);
                        }
                    }, 'user_id');
            }

            DB::table('user_roles')->where('role_id', $adminRoleId)->delete();
            DB::table('roles')->where('id', $adminRoleId)->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}

    private function schema(): Builder
    {
        return Schema::connection((string) config('database.default'));
    }
};
