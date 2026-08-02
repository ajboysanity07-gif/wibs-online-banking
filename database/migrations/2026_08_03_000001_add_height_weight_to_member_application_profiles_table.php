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
            $table->string('height_cm')->nullable()->after('id_number');
            $table->string('weight_kg')->nullable()->after('height_cm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_application_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'height_cm',
                'weight_kg',
            ]);
        });
    }
};
