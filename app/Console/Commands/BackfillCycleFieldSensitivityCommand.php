<?php

namespace App\Console\Commands;

use App\Models\LoanRequestDataEntry;
use App\Models\MemberDependentProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillCycleFieldSensitivityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan-requests:backfill-cycle-field-sensitivity
        {--fix : Clear the stale is_sensitive flag on already-persisted dependent cycle entries (default is dry run)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the stale is_sensitive flag on dependent/spouse Group Life Insurance cycle_status/cycle_number entries persisted before those fields were reclassified as staff-owned, so the Generali document checklist stops requiring a member confirmation that can never be given.';

    public function handle(): int
    {
        if (! Schema::hasTable('loan_request_data_entries')) {
            $this->error('loan_request_data_entries table not found.');

            return self::FAILURE;
        }

        $fix = (bool) $this->option('fix');
        $fieldKeys = self::dependentCycleFieldKeys();

        $this->line($fix
            ? 'Fix mode enabled. Clearing is_sensitive on stale dependent cycle entries.'
            : 'Dry run. No writes will be applied.');

        $query = LoanRequestDataEntry::query()
            ->whereIn('field_key', $fieldKeys)
            ->where('is_sensitive', true);

        $found = (clone $query)->count();

        $this->line(sprintf('Stale entries found: %d', $found));

        if ($fix && $found > 0) {
            $updated = $query->update(['is_sensitive' => false]);

            $this->line(sprintf('Updated: %d', $updated));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    public static function dependentCycleFieldKeys(): array
    {
        $keys = [
            'dependent_spouse_cycle_status',
            'dependent_spouse_cycle_number',
        ];

        foreach (MemberDependentProfile::CATEGORY_CAPS as $category => $cap) {
            for ($slot = 1; $slot <= $cap; $slot++) {
                $keys[] = "dependent_{$category}_{$slot}_cycle_status";
                $keys[] = "dependent_{$category}_{$slot}_cycle_number";
            }
        }

        return $keys;
    }
}
