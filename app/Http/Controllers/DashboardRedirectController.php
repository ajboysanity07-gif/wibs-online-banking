<?php

namespace App\Http\Controllers;

use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardRedirectController extends Controller
{
    public function __invoke(
        Request $request,
        LoanWorkflowWorkspaceService $workspaceService,
    ): RedirectResponse|Response {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('adminProfile', 'userProfile');

        if ($user->isSuperadmin() || $user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isHybrid()) {
            return Inertia::render('dashboard');
        }

        if (! $user->hasMemberAccess() && $workspaceService->canAccess($user)) {
            return redirect()->route('staff.loan-requests.index');
        }

        if ($user->userProfile?->status === 'suspended') {
            return redirect()->route('pending-approval');
        }

        if (! $user->memberApplicationProfileIsComplete()) {
            return redirect()->route('profile.edit', ['onboarding' => 1]);
        }

        return redirect()->route('client.dashboard');
    }
}
