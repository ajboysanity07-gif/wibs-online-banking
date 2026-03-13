<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PendingApprovalPreviewResource;
use App\Http\Resources\Admin\RequestPreviewResource;
use App\Http\Resources\Admin\WatchlistItemResource;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\WatchlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardDataController extends Controller
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

        $watchlistTypes = ['eligible', 'near', 'risk', 'inactive'];
        $watchlistItems = [];

        foreach ($watchlistTypes as $type) {
            $watchlistItems[$type] = WatchlistItemResource::collection(
                $dashboardService->getWatchlistPreview($type)
            )->resolve();
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'metrics' => $metrics,
                'pendingApprovals' => $pendingApprovals,
                'requests' => $recentRequests,
                'watchlist' => [
                    'available' => false,
                    'message' => WatchlistService::UNAVAILABLE_MESSAGE,
                    'items' => $watchlistItems,
                ],
            ],
        ]);
    }
}
