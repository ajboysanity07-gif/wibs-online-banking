<?php

use App\Models\AppUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
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
            ! $schema->hasTable('appusers')
            || ! $schema->hasTable('admin_profiles')
            || ! $schema->hasTable('user_profiles')
        ) {
            return;
        }

        AppUser::query()
            ->whereNotNull('acctno')
            ->where('acctno', '!=', '')
            ->whereHas('adminProfile', function ($query): void {
                $query->whereNull('profile_pic_path')
                    ->orWhere('profile_pic_path', '');
            })
            ->orderBy('user_id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $user->loadMissing('adminProfile', 'userProfile');

                    $adminProfile = $user->adminProfile;
                    $userProfile = $user->userProfile;

                    if ($adminProfile === null || $userProfile === null) {
                        continue;
                    }

                    $adminPath = is_string($adminProfile->profile_pic_path)
                        ? trim($adminProfile->profile_pic_path)
                        : '';
                    $userPath = is_string($userProfile->profile_pic_path)
                        ? trim($userProfile->profile_pic_path)
                        : '';

                    if ($adminPath !== '' || $userPath === '') {
                        continue;
                    }

                    $adminProfile->profile_pic_path = $userPath;
                    $adminProfile->save();
                }
            }, 'user_id');
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
