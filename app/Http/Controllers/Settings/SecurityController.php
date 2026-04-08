<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\SettingsPageData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render(
            'settings/security',
            SettingsPageData::fromRequest($request, 'security'),
        );
    }
}
