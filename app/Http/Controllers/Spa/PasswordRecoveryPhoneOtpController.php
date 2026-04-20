<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Services\Auth\PasswordRecoveryService;
use App\Support\PasswordRecoveryState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordRecoveryPhoneOtpController extends Controller
{
    public function __invoke(
        Request $request,
        PasswordRecoveryService $passwordRecoveryService,
        PasswordRecoveryState $passwordRecoveryState,
    ): JsonResponse {
        $user = $passwordRecoveryState->lookupUser($request);

        if ($user === null || ! filled($user->phoneno)) {
            $passwordRecoveryState->clear($request);

            return response()->json([
                'message' => PasswordRecoveryService::SESSION_EXPIRED_MESSAGE,
                'recovery' => $passwordRecoveryState->pageData($request),
            ], 403);
        }

        $otp = $passwordRecoveryService->sendPhoneOtp($user);
        $passwordRecoveryState->storePhoneOtp($request, $otp);

        return response()->json([
            'ok' => true,
            'message' => PasswordRecoveryService::PHONE_MESSAGE,
            'recovery' => $passwordRecoveryState->pageData($request),
        ]);
    }
}
