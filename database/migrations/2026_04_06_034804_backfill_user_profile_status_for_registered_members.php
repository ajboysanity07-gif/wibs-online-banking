<?php

use App\Models\AppUser;
use App\Models\UserProfile;
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

        if (! $schema->hasTable('appusers') || ! $schema->hasTable('user_profiles')) {
            return;
        }

        $query = AppUser::query();

        if ($schema->hasTable('admin_profiles')) {
            $query->whereDoesntHave('adminProfile');
        }

        $query
            ->orderBy('user_id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $user->loadMissing('userProfile');

                    $profile = $user->userProfile;

                    if ($profile === null) {
                        UserProfile::query()->create([
                            'user_id' => $user->user_id,
                            'role' => 'client',
                            'status' => 'active',
                        ]);

                        continue;
                    }

                    $status = is_string($profile->status) ? trim($profile->status) : '';

                    if ($status === '') {
                        $profile->status = 'active';
                        $profile->save();
                    }
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
