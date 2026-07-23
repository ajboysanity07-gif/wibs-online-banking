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
            $table->string('payout_bank_name')->nullable()->after('payday');
            $table->string('payout_account_name')->nullable()->after('payout_bank_name');
            $table->string('payout_account_number')->nullable()->after('payout_account_name');
            $table->string('payout_account_type')->nullable()->after('payout_account_number');
            $table->string('release_method')->nullable()->after('payout_account_type');
            $table->string('payout_atm_number')->nullable()->after('release_method');
            $table->string('payout_bank_branch')->nullable()->after('payout_atm_number');
            $table->string('payout_atm_holder_name')->nullable()->after('payout_bank_branch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'payout_bank_name',
                'payout_account_name',
                'payout_account_number',
                'payout_account_type',
                'release_method',
                'payout_atm_number',
                'payout_bank_branch',
                'payout_atm_holder_name',
            ]);
        });
    }
};
