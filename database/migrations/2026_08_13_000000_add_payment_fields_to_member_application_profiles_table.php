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
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->string('payment_option')->nullable()->after('payout_atm_holder_name');
            $table->string('payment_bank_name')->nullable()->after('payment_option');
            $table->string('payment_account_name')->nullable()->after('payment_bank_name');
            $table->string('payment_account_number')->nullable()->after('payment_account_name');
            $table->string('payment_account_type')->nullable()->after('payment_account_number');
            $table->string('payment_atm_number')->nullable()->after('payment_account_type');
            $table->string('payment_bank_branch')->nullable()->after('payment_atm_number');
            $table->string('payment_atm_holder_name')->nullable()->after('payment_bank_branch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'payment_option',
                'payment_bank_name',
                'payment_account_name',
                'payment_account_number',
                'payment_account_type',
                'payment_atm_number',
                'payment_bank_branch',
                'payment_atm_holder_name',
            ]);
        });
    }
};
