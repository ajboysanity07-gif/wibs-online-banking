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
        $perPage = (int) $request->query('perPage', 10);
        $page = (int) $request->query('page', 1);

        $result = $service->getPaginated($search, $perPage);
        $items = RequestPreviewResource::collection($result['items'])->resolve();

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
                    'total' => 0,
                    'lastPage' => 1,
                ],
            ],
        ]);
    }
}
