<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrganizationSettingUpdateRequest;
use App\Models\OrganizationSetting;
use App\Services\OrganizationSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function __construct(
        private OrganizationSettingsService $brandingService,
    ) {}

    /**
     * Display the organization branding settings page.
     */
    public function index(): Response
    {
        return Inertia::render('admin/organization-settings');
    }

    /**
     * Update the organization branding settings.
     */
    public function update(
        OrganizationSettingUpdateRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();
        $setting = OrganizationSetting::query()->first();

        if ($setting === null) {
            $setting = OrganizationSetting::query()->create(
                $this->brandingService->defaultAttributes(),
            );
        }

        $payload = Arr::only($validated, [
            'company_name',
            'portal_label',
            'logo_preset',
            'support_email',
            'support_phone',
            'support_contact_name',
            'brand_primary_color',
            'brand_accent_color',
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
        ]);
        $shouldResetLogoMark = $request->boolean('logo_mark_reset');
        $shouldResetLogoFull = $request->boolean('logo_full_reset');
        $shouldResetFavicon = $request->boolean('favicon_reset');

        if ($request->hasFile('logo_mark')) {
            if ($setting->logo_mark_path) {
                Storage::disk('public')->delete($setting->logo_mark_path);
            }

            $payload['logo_mark_path'] = $request->file('logo_mark')
                ->store('branding/logos/mark', 'public');
        } elseif ($shouldResetLogoMark) {
            if ($setting->logo_mark_path) {
                Storage::disk('public')->delete($setting->logo_mark_path);
            }

            $payload['logo_mark_path'] = null;
        }

        if ($request->hasFile('logo_full')) {
            if ($setting->logo_full_path) {
                Storage::disk('public')->delete($setting->logo_full_path);
            }

            $payload['logo_full_path'] = $request->file('logo_full')
                ->store('branding/logos/full', 'public');
        } elseif ($shouldResetLogoFull) {
            if ($setting->logo_full_path) {
                Storage::disk('public')->delete($setting->logo_full_path);
            }

            $payload['logo_full_path'] = null;
        }

        if ($request->hasFile('favicon')) {
            if ($setting->favicon_path) {
                Storage::disk('public')->delete($setting->favicon_path);
            }

            $payload['favicon_path'] = $request->file('favicon')
                ->store('branding/favicons', 'public');
        } elseif ($shouldResetFavicon) {
            if ($setting->favicon_path) {
                Storage::disk('public')->delete($setting->favicon_path);
            }

            $payload['favicon_path'] = null;
        }

        if ($payload !== []) {
            if (array_key_exists('report_header_show_logo', $payload)) {
                $payload['report_header_show_logo'] = $request->boolean(
                    'report_header_show_logo',
                );
            }

            if (array_key_exists('report_header_show_company_name', $payload)) {
                $payload['report_header_show_company_name'] = $request->boolean(
                    'report_header_show_company_name',
                );
            }

            $setting->fill($payload);
            $setting->save();
        }

        return to_route('admin.settings.organization');
    }
}
