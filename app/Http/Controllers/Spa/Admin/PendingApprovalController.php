<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PendingApprovalsIndexRequest;
use App\Http\Resources\Admin\PendingApprovalRowResource;
use App\Services\Admin\PendingApprovalsService;
use Illuminate\Http\JsonResponse;

class PendingApprovalController extends Controller
{
    public function __invoke(
        PendingApprovalsIndexRequest $request,
        PendingApprovalsService $service,
    ): JsonResponse {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'newest');
        $perPage = (int) $request->query('perPage', 10);

        $paginator = $service->getPaginated($search, $sort, $perPage);
        $rows = PendingApprovalRowResource::collection($paginator->items())->resolve();

        return response()->json([
            'ok' => true,
            'data' => [
                'rows' => $rows,
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}
