<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('loan_request_people')) {
            return;
        }

        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        if ($this->foreignKeyExists(
            'loan_request_people',
            'loan_request_people_loan_request_id_foreign',
        )) {
            return;
        }

        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->foreign('loan_request_id')
                ->references('id')
                ->on('loan_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('loan_request_people')) {
            return;
        }

        if (! $this->foreignKeyExists(
            'loan_request_people',
            'loan_request_people_loan_request_id_foreign',
        )) {
            return;
        }

        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->dropForeign('loan_request_people_loan_request_id_foreign');
        });
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        if (! Schema::hasTable($table)) {
            return false;
        }

        return DB::table('information_schema.table_constraints')
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
