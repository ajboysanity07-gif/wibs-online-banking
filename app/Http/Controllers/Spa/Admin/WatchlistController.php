<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\WatchlistItemResource;
use App\Services\Admin\WatchlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function __invoke(Request $request, WatchlistService $service): JsonResponse
    {
        $type = (string) $request->query('type', 'eligible');
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('perPage', 10);
        $page = (int) $request->query('page', 1);

        $type = in_array($type, ['eligible', 'near', 'risk', 'inactive'], true)
            ? $type
            : 'eligible';

        $result = $service->getPaginated($type, $search, $perPage);
        $items = WatchlistItemResource::collection($result['items'])->resolve();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'type' => $type,
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
