<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordRecoveryLookupRequest;
use App\Services\Auth\PasswordRecoveryService;
use App\Support\PasswordRecoveryState;
use Illuminate\Http\JsonResponse;

class PasswordRecoveryLookupController extends Controller
{
    public function __invoke(
        PasswordRecoveryLookupRequest $request,
        PasswordRecoveryService $passwordRecoveryService,
        PasswordRecoveryState $passwordRecoveryState,
    ): JsonResponse {
        $identifier = (string) $request->validated('identifier');
        $user = $passwordRecoveryService->findUserByIdentifier($identifier);

        if ($user !== null) {
            $passwordRecoveryState->storeLookup($request, $user);
        } else {
            $passwordRecoveryState->clear($request);
        }

        return response()->json([
            'ok' => true,
            'message' => PasswordRecoveryService::LOOKUP_MESSAGE,
            'recovery' => $passwordRecoveryState->pageData($request),
        ]);
    }
}
