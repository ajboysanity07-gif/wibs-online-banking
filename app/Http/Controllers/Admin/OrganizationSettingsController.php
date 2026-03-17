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
            'support_email',
            'support_phone',
            'support_contact_name',
            'brand_primary_color',
            'brand_accent_color',
        ]);

        if ($request->hasFile('company_logo')) {
            if ($setting->company_logo_path) {
                Storage::disk('public')->delete($setting->company_logo_path);
            }

            $payload['company_logo_path'] = $request->file('company_logo')
                ->store('branding', 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($setting->favicon_path) {
                Storage::disk('public')->delete($setting->favicon_path);
            }

            $payload['favicon_path'] = $request->file('favicon')
                ->store('branding/favicons', 'public');
        }

        if ($payload !== []) {
            $setting->fill($payload);
            $setting->save();
        }

        return to_route('admin.settings.organization');
    }
}
