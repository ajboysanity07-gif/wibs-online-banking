<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Locations\PsgcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarangaySearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, PsgcService $service): JsonResponse
    {
        $municipality = trim((string) $request->query('municipality', ''));
        $province = trim((string) $request->query('province', ''));
        $query = trim((string) $request->query('search', ''));
        $limit = $request->integer('limit', 15);
        $limit = max(1, min($limit, 500));

        if ($municipality === '') {
            return response()->json([
                'ok' => true,
                'available' => true,
                'data' => [],
            ]);
        }

        $result = $service->searchBarangays($municipality, $query, $limit, $province !== '' ? $province : null);

        if (! $result['available']) {
            return response()->json([
                'ok' => true,
                'available' => false,
                'message' => 'Barangay suggestions are temporarily unavailable.',
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
