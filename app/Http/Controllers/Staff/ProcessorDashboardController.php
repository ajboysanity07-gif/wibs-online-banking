<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Services\Reports\ReportMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcessorDashboardController extends Controller
{
    public function index(
        Request $request,
        ReportMetricsService $metricsService,
    ): Response {
        $actor = $request->user();
        abort_unless($actor instanceof AppUser, 403);

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $approvedThisMonth = LoanRequest::query()
            ->where('approved_by', $actor->user_id)
            ->whereBetween('approved_at', [$monthStart, $monthEnd])
            ->count();

        $rejectedThisMonth = LoanRequest::query()
            ->where('rejected_by', $actor->user_id)
            ->whereBetween('rejected_at', [$monthStart, $monthEnd])
            ->count();

        $queueData = $metricsService->processorQueue($actor);

        return Inertia::render('staff/processor-dashboard', [
            'queueData' => $queueData,
            'thisMonth' => [
                'approved' => $approvedThisMonth,
                'rejected' => $rejectedThisMonth,
                'month_label' => Carbon::now()->format('F Y'),
            ],
        ]);
    }
}
