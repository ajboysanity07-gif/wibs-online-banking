<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Locations\ZipCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZipLookupController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, ZipCodeService $service): JsonResponse
    {
        $localityCode = trim((string) $request->query('locality_code', ''));

        if ($localityCode === '') {
            return response()->json([
                'ok' => true,
                'available' => true,
                'zip' => null,
            ]);
        }

        $zip = $service->lookup($localityCode);

        return response()->json([
            'ok' => true,
            'available' => true,
            'zip' => $zip,
        ]);
    }
}
