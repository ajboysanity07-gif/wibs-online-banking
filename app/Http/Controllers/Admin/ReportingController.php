<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MonthlyApplicationsExport;
use App\Exports\ProcessorWorkloadExport;
use App\Exports\RejectionReasonsExport;
use App\Exports\TurnaroundTimeExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RequestPreviewResource;
use App\Models\AppUser;
use App\Models\Permission;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\WatchlistService;
use App\Services\Reports\ReportMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportingController extends Controller
{
    public function index(
        Request $request,
        ReportMetricsService $metricsService,
    ): Response {
        Gate::authorize('viewAny', \App\Models\LoanRequest::class);

        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        [$from, $to] = $this->parseDateRange($request);

        return Inertia::render('admin/reports', [
            'reportingMetrics' => $metricsService->dashboardMetrics($actor, $from, $to),
            'canExport' => $actor->hasPermission(Permission::REPORT_EXPORT),
            'dateRange' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    public function dashboard(
        Request $request,
        ReportMetricsService $metricsService,
        AdminDashboardService $dashboardService,
    ): Response {
        Gate::authorize('viewAny', \App\Models\LoanRequest::class);

        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        [$from, $to] = $this->parseDateRange($request);

        $metrics = $dashboardService->getMetrics();
        $recentRequests = RequestPreviewResource::collection(
            $dashboardService->getRecentRequestsPreview()
        )->resolve();

        return Inertia::render('admin/dashboard', [
            'summary' => [
                'metrics' => $metrics,
                'requests' => $recentRequests,
                'watchlist' => [
                    'available' => false,
                    'message' => WatchlistService::UNAVAILABLE_MESSAGE,
                    'items' => [],
                ],
            ],
            'reportingMetrics' => $metricsService->dashboardMetrics($actor, $from, $to),
            'applicationVolume' => $metricsService->applicationVolume($from, $to),
            'staffPerformance' => $metricsService->staffPerformance($from, $to),
            'canExport' => $actor->hasPermission(Permission::REPORT_EXPORT),
            'dateRange' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    public function export(
        Request $request,
        string $type,
    ): BinaryFileResponse {
        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);
        abort_unless($actor->hasPermission(Permission::REPORT_EXPORT), 403);

        [$from, $to] = $this->parseDateRange($request);

        $export = match ($type) {
            'monthly-applications' => new MonthlyApplicationsExport($actor, $from, $to),
            'rejection-reasons' => new RejectionReasonsExport($actor, $from, $to),
            'turnaround-time' => new TurnaroundTimeExport($actor, $from, $to),
            'processor-workload' => new ProcessorWorkloadExport($actor, $from, $to),
            default => abort(404, 'Unknown report type.'),
        };

        $filename = sprintf('%s-%s.xlsx', $type, now()->format('Y-m-d'));

        return Excel::download($export, $filename);
    }

    /**
     * @return array{0:?Carbon,1:?Carbon}
     */
    private function parseDateRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : Carbon::today()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : Carbon::today()->endOfDay();

        return [$from, $to];
    }
}
