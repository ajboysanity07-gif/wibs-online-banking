<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalise legacy payment-frequency values to the new WIBS-aligned
     * set (Daily, Due date, Monthly, Quincenal, Semi-annual, Weekly, Yearly)
     * and drop the now-unused confirmed_cycle_* columns from
     * member_dependent_profiles / member_dependents.
     */
    public function up(): void
    {
        $this->normalisePaymentFrequency();

        // SQLite's ALTER TABLE DROP COLUMN refuses columns that participate
        // in foreign key constraints (even with PRAGMA foreign_keys = OFF).
        // The confirmed_cycle_* columns are nullable and harmless, so skip
        // the drops on SQLite (test-only driver). SQL Server drops them normally.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_confirmed_cycle_status',
                'applicant_confirmed_cycle_number',
                'applicant_confirmed_by_loan_request_id',
                'spouse_confirmed_cycle_status',
                'spouse_confirmed_cycle_number',
                'spouse_confirmed_by_loan_request_id',
            ]);
        });

        Schema::table('member_dependents', function (Blueprint $table) {
            $table->dropColumn([
                'confirmed_cycle_status',
                'confirmed_cycle_number',
                'confirmed_by_loan_request_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->string('applicant_confirmed_cycle_status')->nullable()->after('applicant_confirmed_cycle_number');
            $table->unsignedTinyInteger('applicant_confirmed_cycle_number')->nullable()->after('applicant_confirmed_cycle_status');
            $table->string('spouse_confirmed_cycle_status')->nullable()->after('spouse_confirmed_cycle_number');
            $table->unsignedTinyInteger('spouse_confirmed_cycle_number')->nullable()->after('spouse_confirmed_cycle_status');
            $table->unsignedBigInteger('applicant_confirmed_by_loan_request_id')->nullable()->after('applicant_confirmed_cycle_number');
            $table->unsignedBigInteger('spouse_confirmed_by_loan_request_id')->nullable()->after('spouse_confirmed_cycle_number');
        });

        Schema::table('member_dependents', function (Blueprint $table) {
            $table->string('confirmed_cycle_status')->nullable()->after('cycle_number');
            $table->unsignedTinyInteger('confirmed_cycle_number')->nullable()->after('confirmed_cycle_status');
            $table->unsignedBigInteger('confirmed_by_loan_request_id')->nullable()->after('confirmed_cycle_number');
        });

        $this->reverseNormalisePaymentFrequency();
    }

    private function normalisePaymentFrequency(): void
    {
        $mapping = [
            '15th' => 'Quincenal',
            '30th' => 'Quincenal',
            '15th & 30th' => 'Quincenal',
            'Bi-Weekly' => 'Weekly',
            'Lump sum' => 'Due date',
        ];

        // loan_requests table
        foreach (['requested_payment_frequency', 'recommended_payment_frequency'] as $column) {
            if (! Schema::hasColumn('loan_requests', $column)) {
                continue;
            }

            foreach ($mapping as $old => $new) {
                DB::table('loan_requests')
                    ->where($column, $old)
                    ->update([$column => $new]);
            }
        }

        // loan_request_entries (EAV) — payment frequency is stored as
        // field values; update any entry whose value matches an old label.
        if (Schema::hasTable('loan_request_entries')) {
            foreach ($mapping as $old => $new) {
                DB::table('loan_request_entries')
                    ->where('value', $old)
                    ->whereIn('field_key', [
                        'requested_payment_frequency',
                        'recommended_payment_frequency',
                    ])
                    ->update(['value' => $new]);
            }
        }

        // loan_request_people.payday (co-maker payday)
        if (Schema::hasColumn('loan_request_people', 'payday')) {
            foreach ($mapping as $old => $new) {
                DB::table('loan_request_people')
                    ->where('payday', $old)
                    ->update(['payday' => $new]);
            }
        }

        // co_makers.payday (legacy co-maker table, if present)
        if (Schema::hasTable('co_makers') && Schema::hasColumn('co_makers', 'payday')) {
            foreach ($mapping as $old => $new) {
                DB::table('co_makers')
                    ->where('payday', $old)
                    ->update(['payday' => $new]);
            }
        }

        // member_dependent_profiles.spouse_payday (if present)
        if (Schema::hasColumn('member_dependent_profiles', 'spouse_payday')) {
            foreach ($mapping as $old => $new) {
                DB::table('member_dependent_profiles')
                    ->where('spouse_payday', $old)
                    ->update(['spouse_payday' => $new]);
            }
        }
    }

    private function reverseNormalisePaymentFrequency(): void
    {
        // Best-effort reverse: map new values back to the most common
        // legacy equivalent.  Exact original value is unrecoverable.
        $mapping = [
            'Quincenal' => '15th & 30th',
            'Due date' => 'Lump sum',
            // Daily, Semi-annual, Yearly are new — no legacy equivalent.
            // Weekly and Monthly are unchanged.
        ];

        foreach (['requested_payment_frequency', 'recommended_payment_frequency'] as $column) {
            if (! Schema::hasColumn('loan_requests', $column)) {
                continue;
            }

            foreach ($mapping as $new => $old) {
                DB::table('loan_requests')
                    ->where($column, $new)
                    ->update([$column => $old]);
            }
        }

        if (Schema::hasTable('loan_request_entries')) {
            foreach ($mapping as $new => $old) {
                DB::table('loan_request_entries')
                    ->where('value', $new)
                    ->whereIn('field_key', [
                        'requested_payment_frequency',
                        'recommended_payment_frequency',
                    ])
                    ->update(['value' => $old]);
            }
        }

        if (Schema::hasColumn('loan_request_people', 'payday')) {
            foreach ($mapping as $new => $old) {
                DB::table('loan_request_people')
                    ->where('payday', $new)
                    ->update(['payday' => $old]);
            }
        }

        if (Schema::hasTable('co_makers') && Schema::hasColumn('co_makers', 'payday')) {
            foreach ($mapping as $new => $old) {
                DB::table('co_makers')
                    ->where('payday', $new)
                    ->update(['payday' => $old]);
            }
        }

        if (Schema::hasColumn('member_dependent_profiles', 'spouse_payday')) {
            foreach ($mapping as $new => $old) {
                DB::table('member_dependent_profiles')
                    ->where('spouse_payday', $new)
                    ->update(['spouse_payday' => $old]);
            }
        }
    }
};
