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
            $table->string('requested_payment_frequency')
                ->nullable()
                ->after('recommended_payment_frequency_lumpsum_months');

            $table->unsignedTinyInteger('requested_payment_frequency_lumpsum_months')
                ->nullable()
                ->after('requested_payment_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropColumn([
                'requested_payment_frequency',
                'requested_payment_frequency_lumpsum_months',
            ]);
        });
    }
};
