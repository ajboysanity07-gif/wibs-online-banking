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
        Schema::table('member_co_makers', function (Blueprint $table) {
            $table->string('address_zip')->nullable()->after('address3');
            $table->string('employer_business_address_zip')->nullable()->after('employer_business_address3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_co_makers', function (Blueprint $table) {
            $table->dropColumn([
                'address_zip',
                'employer_business_address_zip',
            ]);
        });
    }
};