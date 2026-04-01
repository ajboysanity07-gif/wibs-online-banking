<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->string('access_level')
                ->default('admin')
                ->after('fullname');
        });

        DB::table('admin_profiles')
            ->whereNull('access_level')
            ->update(['access_level' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->dropColumn('access_level');
        });
    }
};
