<?php

namespace App\Http\Middleware;

use App\Models\AppUser;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoanWorkflowStaffAccess
{
    public function __construct(
        private LoanWorkflowWorkspaceService $workspaceService,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            abort(403);
        }

        if (! $this->workspaceService->canAccess($user)) {
            abort(403);
        }

        return $next($request);
    }
}
