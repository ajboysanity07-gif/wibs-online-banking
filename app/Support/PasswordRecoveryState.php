<?php

namespace App\Support;

use App\Models\AppUser;
use App\Models\PasswordRecoveryOtp;
use App\Services\Auth\PasswordRecoveryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class PasswordRecoveryState
{
    public const SESSION_KEY = 'password_recovery';

    private const LOOKUP_TTL_MINUTES = 15;

    private const VERIFIED_TTL_MINUTES = 10;

    public function __construct(
        private PasswordRecoveryService $passwordRecoveryService,
    ) {}

    /**
     * @return array{
     *     step: 'lookup'|'options'|'phone_verify'|'phone_reset',
     *     options: list<array{type: 'email'|'phone', label: string, masked_value: string}>,
     *     phone: array{masked_value: string}|null
     * }
     */
    public function pageData(Request $request): array
    {
        $lookupUser = $this->lookupUser($request);
        $verifiedUser = $this->verifiedUser($request);
        $pendingOtp = $this->pendingPhoneOtp($request);

        $step = 'lookup';

        if ($lookupUser !== null) {
            $step = 'options';
        }

        if ($pendingOtp !== null) {
            $step = 'phone_verify';
        }

        if ($verifiedUser !== null) {
            $step = 'phone_reset';
        }

        $maskedPhone = null;

        if ($lookupUser !== null && filled($lookupUser->phoneno) && ($pendingOtp !== null || $verifiedUser !== null)) {
            $maskedPhone = [
                'masked_value' => $this->passwordRecoveryService->maskPhone((string) $lookupUser->phoneno),
            ];
        }

        return [
            'step' => $step,
            'options' => $lookupUser !== null
                ? $this->passwordRecoveryService->recoveryOptionsFor($lookupUser)
                : [],
            'phone' => $maskedPhone,
        ];
    }

    public function storeLookup(Request $request, AppUser $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'lookup' => [
                'user_id' => $user->getKey(),
                'looked_up_at' => now()->getTimestamp(),
            ],
        ]);
    }

    public function storePhoneOtp(Request $request, PasswordRecoveryOtp $otp): void
    {
        $state = $this->sessionState($request);
        $state['phone'] = [
            'otp_id' => $otp->getKey(),
            'sent_at' => now()->getTimestamp(),
        ];
        unset($state['verified']);

        $request->session()->put(self::SESSION_KEY, $state);
    }

    public function markPhoneVerified(Request $request, PasswordRecoveryOtp $otp): void
    {
        $state = $this->sessionState($request);
        $state['verified'] = [
            'otp_id' => $otp->getKey(),
            'user_id' => $otp->user_id,
            'verified_at' => now()->getTimestamp(),
        ];
        unset($state['phone']);

        $request->session()->put(self::SESSION_KEY, $state);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function lookupUser(Request $request): ?AppUser
    {
        $state = $this->sessionState($request);
        $lookup = $state['lookup'] ?? null;

        if (! is_array($lookup)) {
            return null;
        }

        $lookedUpAt = $lookup['looked_up_at'] ?? null;
        $userId = $lookup['user_id'] ?? null;

        if (! is_numeric($lookedUpAt) || ! is_numeric($userId)) {
            $this->clear($request);

            return null;
        }

        $expiresAt = CarbonImmutable::createFromTimestamp((int) $lookedUpAt)
            ->addMinutes(self::LOOKUP_TTL_MINUTES);

        if (now()->greaterThan($expiresAt)) {
            $this->clear($request);

            return null;
        }

        $user = AppUser::query()->find((int) $userId);

        if ($user === null) {
            $this->clear($request);

            return null;
        }

        return $user;
    }

    public function pendingPhoneOtp(Request $request): ?PasswordRecoveryOtp
    {
        $otp = $this->phoneOtp($request);

        if ($otp === null) {
            return null;
        }

        if (
            $otp->used_at !== null
            || now()->greaterThan($otp->expires_at)
        ) {
            return null;
        }

        return $otp;
    }

    public function phoneOtp(Request $request): ?PasswordRecoveryOtp
    {
        $user = $this->lookupUser($request);

        if ($user === null) {
            return null;
        }

        $state = $this->sessionState($request);
        $phone = $state['phone'] ?? null;

        if (! is_array($phone) || ! is_numeric($phone['otp_id'] ?? null)) {
            return null;
        }

        $otp = PasswordRecoveryOtp::query()
            ->where('id', (int) $phone['otp_id'])
            ->where('user_id', $user->getKey())
            ->first();

        if ($otp === null) {
            $this->clearPhoneState($request);

            return null;
        }

        return $otp;
    }

    public function verifiedUser(Request $request): ?AppUser
    {
        $user = $this->lookupUser($request);

        if ($user === null) {
            return null;
        }

        $state = $this->sessionState($request);
        $verified = $state['verified'] ?? null;

        if (! is_array($verified)) {
            return null;
        }

        $verifiedAt = $verified['verified_at'] ?? null;
        $verifiedUserId = $verified['user_id'] ?? null;
        $otpId = $verified['otp_id'] ?? null;

        if (
            ! is_numeric($verifiedAt)
            || ! is_numeric($verifiedUserId)
            || ! is_numeric($otpId)
            || (int) $verifiedUserId !== (int) $user->getKey()
        ) {
            $this->clearVerifiedState($request);

            return null;
        }

        $expiresAt = CarbonImmutable::createFromTimestamp((int) $verifiedAt)
            ->addMinutes(self::VERIFIED_TTL_MINUTES);

        if (now()->greaterThan($expiresAt)) {
            $this->clearVerifiedState($request);

            return null;
        }

        $otp = PasswordRecoveryOtp::query()
            ->where('id', (int) $otpId)
            ->where('user_id', $user->getKey())
            ->whereNotNull('used_at')
            ->first();

        if ($otp === null) {
            $this->clearVerifiedState($request);

            return null;
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionState(Request $request): array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        return is_array($state) ? $state : [];
    }

    private function clearPhoneState(Request $request): void
    {
        $state = $this->sessionState($request);
        unset($state['phone']);
        $request->session()->put(self::SESSION_KEY, $state);
    }

    private function clearVerifiedState(Request $request): void
    {
        $state = $this->sessionState($request);
        unset($state['verified']);
        $request->session()->put(self::SESSION_KEY, $state);
    }
}
