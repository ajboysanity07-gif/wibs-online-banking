<?php

namespace App\Http\Controllers\Spa;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordWithOtpRequest;
use App\Services\Auth\PasswordRecoveryService;
use App\Support\PasswordRecoveryState;
use Illuminate\Http\JsonResponse;

class PasswordRecoveryPhoneResetController extends Controller
{
    public function __invoke(
        ResetPasswordWithOtpRequest $request,
        ResetUserPassword $resetUserPassword,
        PasswordRecoveryState $passwordRecoveryState,
    ): JsonResponse {
        $user = $passwordRecoveryState->verifiedUser($request);

        if ($user === null) {
            $passwordRecoveryState->clear($request);

            return response()->json([
                'message' => PasswordRecoveryService::SESSION_EXPIRED_MESSAGE,
                'recovery' => $passwordRecoveryState->pageData($request),
            ], 403);
        }

        $resetUserPassword->reset($user, $request->validated());
        $passwordRecoveryState->clear($request);
        $request->session()->flash('status', PasswordRecoveryService::RESET_MESSAGE);

        return response()->json([
            'ok' => true,
            'message' => PasswordRecoveryService::RESET_MESSAGE,
            'redirect_to' => route('login', absolute: false),
            'recovery' => $passwordRecoveryState->pageData($request),
        ]);
    }
}
