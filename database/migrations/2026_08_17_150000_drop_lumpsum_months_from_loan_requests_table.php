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
        Schema::table('loan_requests', function (Blueprint $table) {
            if (Schema::hasColumn('loan_requests', 'requested_payment_frequency_lumpsum_months')) {
                $table->dropColumn('requested_payment_frequency_lumpsum_months');
            }

            if (Schema::hasColumn('loan_requests', 'recommended_payment_frequency_lumpsum_months')) {
                $table->dropColumn('recommended_payment_frequency_lumpsum_months');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('recommended_payment_frequency_lumpsum_months')
                ->nullable()
                ->after('recommended_payment_frequency');

            $table->unsignedTinyInteger('requested_payment_frequency_lumpsum_months')
                ->nullable()
                ->after('requested_payment_frequency');
        });
    }
};
