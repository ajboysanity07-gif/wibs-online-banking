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
        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->string('address_zip')->nullable()->after('address3');
            $table->string('employer_business_address_zip')->nullable()->after('employer_business_address3');
        });

        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->string('employer_business_address_zip')->nullable()->after('employer_business_address3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->dropColumn(['address_zip', 'employer_business_address_zip']);
        });

        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn('employer_business_address_zip');
        });
    }
};
