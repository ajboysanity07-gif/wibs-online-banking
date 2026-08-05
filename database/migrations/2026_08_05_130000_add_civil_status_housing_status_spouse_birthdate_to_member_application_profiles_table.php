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
            $table->string('civil_status')->nullable();
            $table->string('housing_status')->nullable();
            $table->date('spouse_birthdate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'civil_status',
                'housing_status',
                'spouse_birthdate',
            ]);
        });
    }
};
