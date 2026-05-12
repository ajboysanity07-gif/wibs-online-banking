<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        if (Schema::hasColumn('loan_requests', 'corrected_from_id')) {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('corrected_from_id')->nullable()->after('user_id');

            $foreignKey = $table->foreign('corrected_from_id')
                ->references('id')
                ->on('loan_requests');

            if (Schema::getConnection()->getDriverName() === 'sqlsrv') {
                $foreignKey->onDelete('no action');
            } else {
                $foreignKey
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            $table->index('corrected_from_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('loan_requests')) {
            return;
        }

        if (! Schema::hasColumn('loan_requests', 'corrected_from_id')) {
            return;
        }

        try {
            Schema::table('loan_requests', function (Blueprint $table) {
                $table->dropForeign(['corrected_from_id']);
            });
        } catch (\Throwable) {
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropColumn('corrected_from_id');
        });
    }
};
