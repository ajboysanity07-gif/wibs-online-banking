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
            $table->string('report_header_font_color', 32)
                ->nullable()
                ->after('report_header_show_company_name');
            $table->string('report_label_font_color', 32)
                ->nullable()
                ->after('report_header_font_color');
            $table->string('report_value_font_color', 32)
                ->nullable()
                ->after('report_label_font_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn([
                'report_header_font_color',
                'report_label_font_color',
                'report_value_font_color',
            ]);
        });
    }
};
