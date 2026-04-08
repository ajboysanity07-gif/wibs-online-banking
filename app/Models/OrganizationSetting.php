<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationSettingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_name',
        'company_logo_path',
        'logo_preset',
        'logo_mark_path',
        'logo_full_path',
        'portal_label',
        'favicon_path',
        'brand_primary_color',
        'brand_accent_color',
        'support_email',
        'support_phone',
        'support_contact_name',
        'loan_sms_approved_template',
        'loan_sms_declined_template',
        'report_header_title',
        'report_header_tagline',
        'report_header_show_logo',
        'report_header_show_company_name',
        'report_header_alignment',
        'report_header_font_color',
        'report_header_tagline_color',
        'report_label_font_color',
        'report_value_font_color',
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
    ];
}
