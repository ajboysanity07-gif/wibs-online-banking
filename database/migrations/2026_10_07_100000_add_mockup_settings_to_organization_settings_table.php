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
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('short_name', 32)->nullable();
            $table->string('timezone', 64)->default('Asia/Manila');
            $table->string('statement_currency', 3)->default('PHP');
            $table->string('report_footer')->nullable();
            $table->boolean('report_footer_enabled')->default(true);
            $table->string('service_hours')->nullable();
            $table->boolean('loan_sms_approved_enabled')->default(true);
            $table->boolean('loan_sms_declined_enabled')->default(true);
            $table->string('sms_send_window', 32)->default('any');
            $table->unsignedBigInteger('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn([
                'short_name',
                'timezone',
                'statement_currency',
                'report_footer',
                'report_footer_enabled',
                'service_hours',
                'loan_sms_approved_enabled',
                'loan_sms_declined_enabled',
                'sms_send_window',
                'updated_by',
            ]);
        });
    }
};
