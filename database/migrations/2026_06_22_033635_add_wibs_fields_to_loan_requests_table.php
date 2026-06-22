<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_requests', 'wibs_loan_reference')) {
                $table->string('wibs_loan_reference')->nullable()->after('acctno');
            }

            if (! Schema::hasColumn('loan_requests', 'wibs_release_date')) {
                $table->date('wibs_release_date')->nullable()->after('wibs_loan_reference');
            }

            if (! Schema::hasColumn('loan_requests', 'wibs_encoded_at')) {
                $table->timestamp('wibs_encoded_at')->nullable()->after('wibs_release_date');
            }

            if (! Schema::hasColumn('loan_requests', 'wibs_released_at')) {
                $table->timestamp('wibs_released_at')->nullable()->after('wibs_encoded_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('loan_requests', 'wibs_released_at') ? 'wibs_released_at' : null,
            Schema::hasColumn('loan_requests', 'wibs_encoded_at') ? 'wibs_encoded_at' : null,
            Schema::hasColumn('loan_requests', 'wibs_release_date') ? 'wibs_release_date' : null,
            Schema::hasColumn('loan_requests', 'wibs_loan_reference') ? 'wibs_loan_reference' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
