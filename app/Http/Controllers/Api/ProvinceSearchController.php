<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Locations\PsgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinceSearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, PsgcService $service): JsonResponse
    {
        $query = trim((string) $request->query('search', ''));
        $limit = $request->integer('limit', 15);
        $limit = max(1, min($limit, 20));

        if (strlen($query) < 2) {
            return response()->json([
                'ok' => true,
                'available' => true,
                'data' => [],
            ]);
        }

        $result = $service->searchProvinces($query, $limit);

        if (! $result['available']) {
            return response()->json([
                'ok' => true,
                'available' => false,
                'message' => 'Province suggestions are temporarily unavailable.',
                'data' => [],
            ]);
        }

        return response()->json([
            'ok' => true,
            'available' => true,
            'data' => $result['results'],
        ]);
    }
}
