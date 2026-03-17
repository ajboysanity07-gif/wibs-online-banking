<?php

namespace Database\Seeders;

use App\Models\OrganizationSetting;
use App\Services\OrganizationSettingsService;
use Illuminate\Database\Seeder;

class OrganizationSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = app(OrganizationSettingsService::class)->defaultAttributes();

        $existing = OrganizationSetting::query()->first();

        if ($existing !== null) {
            return;
        }

        OrganizationSetting::query()->create($defaults);
    }
}
