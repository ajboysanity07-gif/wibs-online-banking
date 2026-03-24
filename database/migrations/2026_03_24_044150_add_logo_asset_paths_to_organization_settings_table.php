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
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('logo_mark_path')
                ->nullable()
                ->after('logo_preset');
            $table->string('logo_full_path')
                ->nullable()
                ->after('logo_mark_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_mark_path',
                'logo_full_path',
            ]);
        });
    }
};
