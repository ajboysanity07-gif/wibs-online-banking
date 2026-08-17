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
            $table->text('home_address')->nullable();
            $table->string('home_address1')->nullable();
            $table->string('home_address_barangay')->nullable();
            $table->string('home_address2')->nullable();
            $table->string('home_address3')->nullable();
            $table->string('home_address_zip')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'home_address',
                'home_address1',
                'home_address_barangay',
                'home_address2',
                'home_address3',
                'home_address_zip',
            ]);
        });
    }
};
