<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks which loan request produced each confirmed cycle value, so
     * that the confirming loan request itself is never treated as locked
     * out of its own confirmation -- only a *different* loan request
     * should see the slot as locked/read-only.
     */
    public function up(): void
    {
        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->foreignId('applicant_confirmed_by_loan_request_id')
                ->nullable()
                ->after('applicant_confirmed_cycle_number')
                ->constrained('loan_requests')
                ->nullOnDelete();
            $table->foreignId('spouse_confirmed_by_loan_request_id')
                ->nullable()
                ->after('spouse_confirmed_cycle_number')
                ->constrained('loan_requests')
                ->nullOnDelete();
        });

        Schema::table('member_dependents', function (Blueprint $table) {
            $table->foreignId('confirmed_by_loan_request_id')
                ->nullable()
                ->after('confirmed_cycle_number')
                ->constrained('loan_requests')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applicant_confirmed_by_loan_request_id');
            $table->dropConstrainedForeignId('spouse_confirmed_by_loan_request_id');
        });

        Schema::table('member_dependents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by_loan_request_id');
        });
    }
};
