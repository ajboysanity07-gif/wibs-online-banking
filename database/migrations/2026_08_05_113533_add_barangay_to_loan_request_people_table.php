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
            $table->string('address_barangay')->nullable()->after('address1');
            $table->string('employer_business_address_barangay')->nullable()->after('employer_business_address1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_request_people', function (Blueprint $table) {
            $table->dropColumn([
                'address_barangay',
                'employer_business_address_barangay',
            ]);
        });
    }
};
