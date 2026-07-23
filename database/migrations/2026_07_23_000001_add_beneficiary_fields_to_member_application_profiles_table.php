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
            $table->string('beneficiary_primary_name')->nullable()->after('payout_atm_holder_name');
            $table->string('beneficiary_primary_relationship')->nullable()->after('beneficiary_primary_name');
            $table->date('beneficiary_primary_birthdate')->nullable()->after('beneficiary_primary_relationship');
            $table->string('beneficiary_secondary_name')->nullable()->after('beneficiary_primary_birthdate');
            $table->string('beneficiary_secondary_relationship')->nullable()->after('beneficiary_secondary_name');
            $table->date('beneficiary_secondary_birthdate')->nullable()->after('beneficiary_secondary_relationship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'beneficiary_primary_name',
                'beneficiary_primary_relationship',
                'beneficiary_primary_birthdate',
                'beneficiary_secondary_name',
                'beneficiary_secondary_relationship',
                'beneficiary_secondary_birthdate',
            ]);
        });
    }
};
