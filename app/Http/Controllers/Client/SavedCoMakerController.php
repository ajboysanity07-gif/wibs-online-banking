<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Services\LoanRequests\SavedCoMakersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SavedCoMakerController extends Controller
{
    public function show(
        Request $request,
        int $coMaker,
        SavedCoMakersService $service,
    ): JsonResponse {
        $user = $this->resolveUser($request);

        $record = $service->find($user->memberApplicationProfile, $coMaker);

        if ($record === null) {
            abort(404);
        }

        return response()->json([
            'ok' => true,
            'data' => $record->toArray(),
        ]);
    }

    public function destroy(
        Request $request,
        int $coMaker,
        SavedCoMakersService $service,
    ): JsonResponse {
        $user = $this->resolveUser($request);

        $service->destroy($user->memberApplicationProfile, $coMaker);

        return response()->json(null, HttpResponse::HTTP_NO_CONTENT);
    }

    private function resolveUser(Request $request): AppUser
    {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            abort(403);
        }

        $user->loadMissing('memberApplicationProfile');

        if ($user->memberApplicationProfile === null) {
            abort(404);
        }

        return $user;
    }
}
