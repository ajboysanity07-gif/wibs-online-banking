<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('loan_requests')
            ->where('recommended_payment_frequency', 'Lumpsum')
            ->update(['recommended_payment_frequency' => 'Lump sum']);

        DB::table('loan_requests')
            ->where('requested_payment_frequency', 'Lumpsum')
            ->update(['requested_payment_frequency' => 'Lump sum']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('loan_requests')
            ->where('recommended_payment_frequency', 'Lump sum')
            ->update(['recommended_payment_frequency' => 'Lumpsum']);

        DB::table('loan_requests')
            ->where('requested_payment_frequency', 'Lump sum')
            ->update(['requested_payment_frequency' => 'Lumpsum']);
    }
};
