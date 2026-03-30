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
            $table->string('birthplace_city')->nullable()->after('birthplace');
            $table->string('birthplace_province')->nullable()->after('birthplace_city');
            $table->string('employer_business_address1')->nullable()->after('employer_business_address');
            $table->string('employer_business_address2')->nullable()->after('employer_business_address1');
            $table->string('employer_business_address3')->nullable()->after('employer_business_address2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'birthplace_city',
                'birthplace_province',
                'employer_business_address1',
                'employer_business_address2',
                'employer_business_address3',
            ]);
        });
    }
};
