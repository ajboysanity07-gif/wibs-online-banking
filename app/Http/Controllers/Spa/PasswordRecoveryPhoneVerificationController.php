<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyPasswordRecoveryOtpRequest;
use App\Services\Auth\PasswordRecoveryService;
use App\Support\PasswordRecoveryState;
use Illuminate\Http\JsonResponse;

class PasswordRecoveryPhoneVerificationController extends Controller
{
    public function __invoke(
        VerifyPasswordRecoveryOtpRequest $request,
        PasswordRecoveryService $passwordRecoveryService,
        PasswordRecoveryState $passwordRecoveryState,
    ): JsonResponse {
        $otp = $passwordRecoveryState->phoneOtp($request);

        if ($otp === null) {
            $passwordRecoveryState->clear($request);

            return response()->json([
                'message' => PasswordRecoveryService::SESSION_EXPIRED_MESSAGE,
                'recovery' => $passwordRecoveryState->pageData($request),
            ], 403);
        }

        $verifiedOtp = $passwordRecoveryService->verifyPhoneOtp(
            $otp,
            (string) $request->validated('code'),
        );

        $passwordRecoveryState->markPhoneVerified($request, $verifiedOtp);

        return response()->json([
            'ok' => true,
            'message' => PasswordRecoveryService::VERIFIED_MESSAGE,
            'recovery' => $passwordRecoveryState->pageData($request),
        ]);
    }
}
