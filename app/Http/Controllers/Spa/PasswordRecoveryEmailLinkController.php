<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Services\Auth\PasswordRecoveryService;
use App\Support\PasswordRecoveryState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordRecoveryEmailLinkController extends Controller
{
    public function __invoke(
        Request $request,
        PasswordRecoveryService $passwordRecoveryService,
        PasswordRecoveryState $passwordRecoveryState,
    ): JsonResponse {
        $user = $passwordRecoveryState->lookupUser($request);

        if ($user !== null && filled($user->email)) {
            $passwordRecoveryService->sendEmailResetLink($user);
        }

        return response()->json([
            'ok' => true,
            'message' => PasswordRecoveryService::EMAIL_MESSAGE,
            'recovery' => $passwordRecoveryState->pageData($request),
        ]);
    }
}
