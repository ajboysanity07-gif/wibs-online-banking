<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceSwitchRequest;
use App\Models\AppUser;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Illuminate\Http\RedirectResponse;

class WorkspaceSwitchController extends Controller
{
    public function __invoke(
        WorkspaceSwitchRequest $request,
        LoanWorkflowWorkspaceService $workspaceService,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof AppUser, 403);

        $workspace = (string) $request->validated('workspace');

        abort_unless(
            $workspaceService->canAccessWorkspace($user, $workspace),
            403,
        );

        return redirect()->to(
            $workspaceService->switchWorkspace($request, $user, $workspace),
        );
    }
}
