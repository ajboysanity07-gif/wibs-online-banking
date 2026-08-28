<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreSavedPaymentAccountRequest;
use App\Http\Requests\Client\UpdateSavedPaymentAccountRequest;
use App\Models\AppUser;
use App\Services\LoanRequests\SavedPaymentAccountsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SavedPaymentAccountController extends Controller
{
    public function index(
        Request $request,
        SavedPaymentAccountsService $service,
    ): JsonResponse {
        $user = $this->resolveUser($request);

        return response()->json([
            'ok' => true,
            'data' => $service->listFor($user->memberApplicationProfile)->values(),
        ]);
    }

    public function store(
        StoreSavedPaymentAccountRequest $request,
        SavedPaymentAccountsService $service,
    ): JsonResponse {
        $user = $this->resolveUser($request);

        $account = $service->create($user->memberApplicationProfile, $request->validated());

        return response()->json([
            'ok' => true,
            'data' => $account->toArray(),
        ], HttpResponse::HTTP_CREATED);
    }

    public function update(
        UpdateSavedPaymentAccountRequest $request,
        int $paymentAccount,
        SavedPaymentAccountsService $service,
    ): JsonResponse {
        $user = $this->resolveUser($request);

        $account = $service->update($user->memberApplicationProfile, $paymentAccount, $request->validated());

        return response()->json([
            'ok' => true,
            'data' => $account->toArray(),
        ]);
    }

    public function destroy(
        Request $request,
        int $paymentAccount,
        SavedPaymentAccountsService $service,
    ): JsonResponse {
        $user = $this->resolveUser($request);

        $service->destroy($user->memberApplicationProfile, $paymentAccount);

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
