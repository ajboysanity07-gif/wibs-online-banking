<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreSavedCoMakerRequest;
use App\Models\AppUser;
use App\Services\LoanRequests\SavedCoMakersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SavedCoMakerController extends Controller
{
    public function store(
        StoreSavedCoMakerRequest $request,
        SavedCoMakersService $service,
    ): JsonResponse {
        $user = $this->resolveUser($request);
        $data = $request->validated();

        $existingId = $data['saved_co_maker_id'] ?? null;

        $result = $service->saveOrUpdate(
            $user->memberApplicationProfile,
            $data,
            is_numeric($existingId) ? (int) $existingId : null,
            $data['label'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'data' => $result['coMaker']->toArray(),
            'duplicate' => $result['wasDuplicate'],
        ]);
    }

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
