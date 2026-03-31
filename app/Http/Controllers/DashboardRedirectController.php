<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile', 'userProfile');

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->userProfile?->status === 'suspended') {
            return redirect()->route('pending-approval');
        }

        return redirect()->route('client.dashboard');
    }
}
