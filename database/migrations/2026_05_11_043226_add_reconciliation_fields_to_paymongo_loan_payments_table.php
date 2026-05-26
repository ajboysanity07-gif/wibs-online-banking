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
        Schema::table('paymongo_loan_payments', function (Blueprint $table) {
            $table->string('reconciliation_status')
                ->default('unreconciled')
                ->index();
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable()->index();
            $table->string('desktop_reference_no')->nullable();
            $table->string('official_receipt_no')->nullable();
            $table->text('reconciliation_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paymongo_loan_payments', function (Blueprint $table) {
            $table->dropIndex(['reconciliation_status']);
            $table->dropIndex(['reconciled_by']);
            $table->dropColumn([
                'reconciliation_status',
                'reconciled_at',
                'reconciled_by',
                'desktop_reference_no',
                'official_receipt_no',
                'reconciliation_notes',
            ]);
        });
    }
};
