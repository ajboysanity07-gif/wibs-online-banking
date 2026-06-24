<?php

namespace App\Console\Commands;

use App\Models\AppUser;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillMemberRoles extends Command
{
    protected $signature = 'members:backfill-roles';

    protected $description = 'Assign the member role to AppUsers with an acctno that are missing it.';

    public function handle(): int
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            $this->error('Required tables (roles, user_roles) are missing.');

            return self::FAILURE;
        }

        Role::ensureWorkflowDefaults();

        $memberRoleId = Role::query()->where('name', Role::MEMBER)->value('id');

        if ($memberRoleId === null) {
            $this->error('Member role not found after seeding defaults.');

            return self::FAILURE;
        }

        $backfilled = 0;

        DB::transaction(function () use ($memberRoleId, &$backfilled): void {
            AppUser::query()
                ->whereNotNull('acctno')
                ->where('acctno', '!=', '')
                ->whereDoesntHave('roles', function ($q): void {
                    $q->where('name', Role::MEMBER);
                })
                ->orderBy('user_id')
                ->chunkById(200, function ($users) use ($memberRoleId, &$backfilled): void {
                    foreach ($users as $user) {
                        $user->roles()->syncWithoutDetaching([$memberRoleId]);
                        $this->line(sprintf(
                            'Assigned member role: user_id=%d acctno=%s',
                            $user->user_id,
                            $user->acctno,
                        ));
                        $backfilled++;
                    }
                }, 'user_id');
        });

        $this->newLine();
        $this->info(sprintf('Backfilled: %d member(s)', $backfilled));

        return self::SUCCESS;
    }
}
