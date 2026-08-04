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
        if (! Schema::hasColumn('loan_requests', 'recommendation_remarks')) {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropColumn('recommendation_remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('loan_requests', 'recommendation_remarks')) {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->text('recommendation_remarks')
                ->nullable()
                ->after('recommended_payment_frequency');
        });
    }
};
