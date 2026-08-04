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
            $table->boolean('release_uses_payout_account')->default(true)->after('payout_atm_holder_name');
            $table->string('release_bank_name')->nullable()->after('release_uses_payout_account');
            $table->string('release_account_name')->nullable()->after('release_bank_name');
            $table->string('release_account_number')->nullable()->after('release_account_name');
            $table->string('release_account_type')->nullable()->after('release_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'release_uses_payout_account',
                'release_bank_name',
                'release_account_name',
                'release_account_number',
                'release_account_type',
            ]);
        });
    }
};
