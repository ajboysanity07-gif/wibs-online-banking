<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RequestPreviewResource;
use App\Http\Resources\Admin\WatchlistItemResource;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\WatchlistService;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(AdminDashboardService $dashboardService): Response
    {
        $metrics = $dashboardService->getMetrics();
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

        return Inertia::render('admin/dashboard', [
            'summary' => [
                'metrics' => $metrics,
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
