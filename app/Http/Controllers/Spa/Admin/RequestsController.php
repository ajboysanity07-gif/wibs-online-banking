<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RequestsIndexRequest;
use App\Http\Resources\Admin\RequestPreviewResource;
use App\Services\Admin\RequestsService;
use Illuminate\Http\JsonResponse;

class RequestsController extends Controller
{
    public function __invoke(RequestsIndexRequest $request, RequestsService $service): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $loanType = trim((string) $request->query('loanType', ''));
        $status = trim((string) $request->query('status', ''));
        $perPage = (int) $request->query('perPage', 10);
        $page = (int) $request->query('page', 1);
        $minAmount = $request->query('minAmount');
        $maxAmount = $request->query('maxAmount');
        $minAmount = is_numeric($minAmount) ? (float) $minAmount : null;
        $maxAmount = is_numeric($maxAmount) ? (float) $maxAmount : null;

        $result = $service->getPaginated(
            $search,
            $perPage,
            $page,
            $loanType !== '' ? $loanType : null,
            $status !== '' ? $status : null,
            $minAmount,
            $maxAmount,
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
                ],
            ],
        ]);
    }
}
