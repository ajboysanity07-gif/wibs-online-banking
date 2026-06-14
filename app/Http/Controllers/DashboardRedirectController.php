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

        $redirectPath = $workspaceService->dashboardRedirectPath(
            $request,
            $user,
        );

        if ($redirectPath !== null) {
            return redirect()->to($redirectPath);
        }

        return Inertia::render('dashboard');
    }
}
