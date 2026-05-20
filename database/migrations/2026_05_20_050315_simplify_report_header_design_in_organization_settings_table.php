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
            $table->dropColumn([
                'report_header_title',
                'report_header_tagline',
                'report_header_show_logo',
                'report_header_show_company_name',
                'report_header_alignment',
                'report_header_font_color',
                'report_header_tagline_color',
                'report_header_title_font_family',
                'report_header_title_font_variant',
                'report_header_title_font_weight',
                'report_header_title_font_size',
                'report_header_tagline_font_family',
                'report_header_tagline_font_variant',
                'report_header_tagline_font_weight',
                'report_header_tagline_font_size',
            ]);
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('report_header_design_path')
                ->nullable()
                ->after('support_contact_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn('report_header_design_path');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->string('report_header_title')->nullable();
            $table->string('report_header_tagline')->nullable();
            $table->boolean('report_header_show_logo')->default(true);
            $table->boolean('report_header_show_company_name')->default(true);
            $table->string('report_header_alignment', 16)->nullable();
            $table->string('report_header_font_color', 32)->nullable();
            $table->string('report_header_tagline_color', 32)->nullable();
            $table->string('report_header_title_font_family')->nullable();
            $table->string('report_header_title_font_variant', 32)->nullable();
            $table->string('report_header_title_font_weight', 16)->nullable();
            $table->unsignedTinyInteger('report_header_title_font_size')->nullable();
            $table->string('report_header_tagline_font_family')->nullable();
            $table->string('report_header_tagline_font_variant', 32)->nullable();
            $table->string('report_header_tagline_font_weight', 16)->nullable();
            $table->unsignedTinyInteger('report_header_tagline_font_size')->nullable();
        });
    }
};
