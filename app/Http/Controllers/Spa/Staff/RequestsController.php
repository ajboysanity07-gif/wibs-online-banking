<?php

namespace App\Http\Controllers\Spa\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\LoanWorkflowRequestsIndexRequest;
use App\Http\Resources\Admin\RequestPreviewResource;
use App\Models\AppUser;
use App\Services\Admin\RequestsService;
use Illuminate\Http\JsonResponse;

class RequestsController extends Controller
{
    public function __invoke(
        LoanWorkflowRequestsIndexRequest $request,
        RequestsService $service,
    ): JsonResponse {
        $actor = $request->user();

        abort_unless($actor instanceof AppUser, 403);

        $search = trim((string) $request->query('search', ''));
        $loanType = trim((string) $request->query('loanType', ''));
        $status = trim((string) $request->query('status', ''));
        $assignment = trim((string) $request->query('assignment', ''));
        $officerId = $request->query('officerId');
        $perPage = (int) $request->query('perPage', 10);
        $page = (int) $request->query('page', 1);
        $minAmount = $request->query('minAmount');
        $maxAmount = $request->query('maxAmount');
        $reported = $request->has('reported')
            ? $request->boolean('reported')
            : null;
        $officerId = is_numeric($officerId) ? (int) $officerId : null;
        $minAmount = is_numeric($minAmount) ? (float) $minAmount : null;
        $maxAmount = is_numeric($maxAmount) ? (float) $maxAmount : null;

        $result = $service->getPaginatedForWorkflowUser(
            $actor,
            $search,
            $perPage,
            $page,
            $loanType !== '' ? $loanType : null,
            $status !== '' ? $status : null,
            $assignment !== '' ? $assignment : null,
            $officerId,
            $minAmount,
            $maxAmount,
            $reported,
        );
        $items = RequestPreviewResource::collection($result['items'])->resolve();
        $paginator = $result['paginator'];
        $total = $paginator?->total() ?? 0;
        $lastPage = $paginator?->lastPage() ?? 1;

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'query' => $search !== '' ? $search : null,
                    'available' => $result['available'],
                    'message' => $result['message'],
                    'page' => max(1, $page),
                    'perPage' => max(1, min($perPage, 50)),
                    'total' => $total,
                    'lastPage' => $lastPage,
                    'loanTypes' => $result['loanTypes'] ?? [],
                    'openCorrectionReports' => $result['openCorrectionReports'] ?? 0,
                    'assignmentFilters' => $result['assignmentFilters'] ?? [],
                    'assignmentOfficers' => $result['assignmentOfficers'] ?? [],
                ],
            ],
        ]);
    }
}
