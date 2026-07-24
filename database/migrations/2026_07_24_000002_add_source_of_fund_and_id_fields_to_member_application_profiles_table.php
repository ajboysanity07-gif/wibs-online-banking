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
            $table->string('source_of_fund_wealth')->nullable()->after('beneficiary_secondary_birthdate');
            $table->string('id_type')->nullable()->after('source_of_fund_wealth');
            $table->string('id_type_other')->nullable()->after('id_type');
            $table->string('id_number')->nullable()->after('id_type_other');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'source_of_fund_wealth',
                'id_type',
                'id_type_other',
                'id_number',
            ]);
        });
    }
};
