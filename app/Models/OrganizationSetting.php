<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationSettingFactory> */
    use HasFactory;

    /**
     * Customizable palette keys beyond primary/accent: surfaces and statuses.
     *
     * @var list<string>
     */
    public const PALETTE_KEYS = [
        'ink',
        'background',
        'card',
        'secondary',
        'muted',
        'border',
        'sidebar',
        'success',
        'warning',
        'info',
        'danger',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_name',
        'business_address',
        'business_address1',
        'business_address2',
        'business_address3',
        'business_address_barangay',
        'business_address_zip',
        'company_logo_path',
        'logo_preset',
        'logo_mark_path',
        'logo_full_path',
        'portal_label',
        'favicon_path',
        'brand_primary_color',
        'brand_accent_color',
        'brand_palette',
        'support_email',
        'support_phone',
        'support_contact_name',
        'business_tin',
        'registration_no',
        'payment_instructions',
        'loan_sms_approved_template',
        'loan_sms_declined_template',
        'report_header_design_path',
        'report_label_font_color',
        'report_value_font_color',
        'report_label_font_family',
        'report_label_font_variant',
        'report_label_font_weight',
        'report_label_font_size',
        'report_value_font_family',
        'report_value_font_variant',
        'report_value_font_weight',
        'report_value_font_size',
        'short_name',
        'timezone',
        'statement_currency',
        'report_footer',
        'report_footer_enabled',
        'service_hours',
        'loan_sms_approved_enabled',
        'loan_sms_declined_enabled',
        'sms_send_window',
        'updated_by',
    ];

    public const TIMEZONES = ['Asia/Manila', 'Asia/Singapore', 'UTC'];

    public const CURRENCIES = ['PHP', 'USD'];

    public const SMS_SEND_WINDOWS = ['7am-8pm-daily', '8am-6pm-mon-sat', 'any'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'brand_palette' => 'array',
            'report_footer_enabled' => 'boolean',
            'loan_sms_approved_enabled' => 'boolean',
            'loan_sms_declined_enabled' => 'boolean',
        ];
    }
}
