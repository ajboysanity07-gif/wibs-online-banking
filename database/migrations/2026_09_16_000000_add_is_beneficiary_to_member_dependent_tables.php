<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Insurance beneficiary designation previously only supported the
     * spouse (via free-text beneficiary_primary_ and beneficiary_secondary_
     * fields on member_application_profiles, disconnected from this table
     * entirely) -- members can now flag any saved dependent, spouse
     * included, as an insurance beneficiary.
     */
    public function up(): void
    {
        Schema::table('member_dependents', function (Blueprint $table) {
            $table->boolean('is_beneficiary')->default(false);
        });

        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->boolean('spouse_is_beneficiary')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_dependents', function (Blueprint $table) {
            $table->dropColumn('is_beneficiary');
        });

        Schema::table('member_dependent_profiles', function (Blueprint $table) {
            $table->dropColumn('spouse_is_beneficiary');
        });
    }
};
