<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\Permission;
use App\Models\Role;
use App\Services\LoanRequests\LoanWorkflowPermissionSeedService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LoanWorkflowRbacSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('roles') ||
            ! Schema::hasTable('user_roles')
        ) {
            $this->command?->warn('Loan workflow RBAC tables are not available yet. Skipping RBAC seed.');

            return;
        }

        $report = app(LoanWorkflowPermissionSeedService::class)->seed();

        if (($report['conflicts'] ?? []) !== []) {
            throw new RuntimeException(
                implode(
                    ' ',
                    array_map(
                        static fn (array $issue): string => (string) ($issue['summary'] ?? ''),
                        $report['conflicts'],
                    ),
                ),
            );
        }

        $this->command?->info(sprintf(
            'Loan workflow RBAC seeded: %d roles, %d permissions, %d superadmin backfills, %d member backfills.',
            Role::query()->count(),
            Permission::query()->count(),
            AppUser::query()->whereHas('roles', function ($query): void {
                $query->where('name', Role::SUPERADMIN);
            })->count(),
            AppUser::query()->whereHas('roles', function ($query): void {
                $query->where('name', Role::MEMBER);
            })->count(),
        ));
        $this->command?->line('Legacy superadmins are backfilled to the explicit superadmin role when their admin profile access level is superadmin.');
        $this->command?->line(
            'Member backfill only attaches the member role to users with a non-empty acctno. Users without that signal are left unchanged.',
        );
    }
}
