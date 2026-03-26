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
            $table->string('report_header_title')->nullable()->after('support_contact_name');
            $table->string('report_header_tagline')->nullable()->after('report_header_title');
            $table->boolean('report_header_show_logo')
                ->default(true)
                ->after('report_header_tagline');
            $table->boolean('report_header_show_company_name')
                ->default(true)
                ->after('report_header_show_logo');
            $table->string('report_header_title_font_family')
                ->nullable()
                ->after('report_header_show_company_name');
            $table->string('report_header_title_font_variant', 32)
                ->nullable()
                ->after('report_header_title_font_family');
            $table->string('report_header_title_font_weight', 16)
                ->nullable()
                ->after('report_header_title_font_variant');
            $table->unsignedTinyInteger('report_header_title_font_size')
                ->nullable()
                ->after('report_header_title_font_weight');
            $table->string('report_header_tagline_font_family')
                ->nullable()
                ->after('report_header_title_font_size');
            $table->string('report_header_tagline_font_variant', 32)
                ->nullable()
                ->after('report_header_tagline_font_family');
            $table->string('report_header_tagline_font_weight', 16)
                ->nullable()
                ->after('report_header_tagline_font_variant');
            $table->unsignedTinyInteger('report_header_tagline_font_size')
                ->nullable()
                ->after('report_header_tagline_font_weight');
            $table->string('report_label_font_family')
                ->nullable()
                ->after('report_header_tagline_font_size');
            $table->string('report_label_font_variant', 32)
                ->nullable()
                ->after('report_label_font_family');
            $table->string('report_label_font_weight', 16)
                ->nullable()
                ->after('report_label_font_variant');
            $table->unsignedTinyInteger('report_label_font_size')
                ->nullable()
                ->after('report_label_font_weight');
            $table->string('report_value_font_family')
                ->nullable()
                ->after('report_label_font_size');
            $table->string('report_value_font_variant', 32)
                ->nullable()
                ->after('report_value_font_family');
            $table->string('report_value_font_weight', 16)
                ->nullable()
                ->after('report_value_font_variant');
            $table->unsignedTinyInteger('report_value_font_size')
                ->nullable()
                ->after('report_value_font_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn([
                'report_header_title',
                'report_header_tagline',
                'report_header_show_logo',
                'report_header_show_company_name',
                'report_header_title_font_family',
                'report_header_title_font_variant',
                'report_header_title_font_weight',
                'report_header_title_font_size',
                'report_header_tagline_font_family',
                'report_header_tagline_font_variant',
                'report_header_tagline_font_weight',
                'report_header_tagline_font_size',
                'report_label_font_family',
                'report_label_font_variant',
                'report_label_font_weight',
                'report_label_font_size',
                'report_value_font_family',
                'report_value_font_variant',
                'report_value_font_weight',
                'report_value_font_size',
            ]);
        });
    }
};
