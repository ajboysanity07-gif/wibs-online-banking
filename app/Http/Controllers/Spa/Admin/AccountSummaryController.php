<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PendingApprovalPreviewResource;
use App\Http\Resources\Admin\RequestPreviewResource;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSummaryController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboardService): JsonResponse
    {
        $metrics = $dashboardService->getMetrics();
        $pendingApprovals = PendingApprovalPreviewResource::collection(
            $dashboardService->getPendingApprovalsPreview()
        )->resolve();
        $recentRequests = RequestPreviewResource::collection(
            $dashboardService->getRecentRequestsPreview()
        )->resolve();

        return response()->json([
            'ok' => true,
            'data' => [
                'metrics' => $metrics,
                'pendingApprovals' => $pendingApprovals,
                'requests' => $recentRequests,
            ],
        ]);
    }
}
