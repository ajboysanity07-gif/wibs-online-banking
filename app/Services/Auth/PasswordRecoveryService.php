<?php

namespace App\Services\Auth;

use App\Models\AppUser;
use App\Models\PasswordRecoveryOtp;
use App\Services\Sms\SemaphoreSmsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryService
{
    public const LOOKUP_MESSAGE = 'If the details match our records, choose a recovery option below.';

    public const EMAIL_MESSAGE = 'If the details match our records, we sent a password reset link.';

    public const PHONE_MESSAGE = 'If the details match our records, we sent a verification code.';

    public const VERIFIED_MESSAGE = 'Verification confirmed. Set a new password below.';

    public const RESET_MESSAGE = 'Your password has been reset.';

    public const SESSION_EXPIRED_MESSAGE = 'Start account recovery again.';

    public const INVALID_CODE_MESSAGE = 'The verification code is invalid or has expired.';

    public const SMS_FAILURE_MESSAGE = 'We could not send a recovery code right now. Please try again.';

    public const OTP_LENGTH = 6;

    public const OTP_EXPIRES_IN_MINUTES = 5;

    public const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        private SemaphoreSmsService $smsService,
    ) {}

    public function findUserByIdentifier(string $identifier): ?AppUser
    {
        $normalized = trim($identifier);

        if ($normalized === '') {
            return null;
        }

        $normalizedLower = Str::lower($normalized);

        $emailUser = AppUser::query()
            ->whereRaw('lower(email) = ?', [$normalizedLower])
            ->first();

        if ($emailUser !== null) {
            return $emailUser;
        }

        $usernameUser = AppUser::query()
            ->whereRaw('lower(username) = ?', [$normalizedLower])
            ->first();

        if ($usernameUser !== null) {
            return $usernameUser;
        }

        return AppUser::query()
            ->where('acctno', $normalized)
            ->first();
    }

    /**
     * @return list<array{type: 'email'|'phone', label: string, masked_value: string}>
     */
    public function recoveryOptionsFor(AppUser $user): array
    {
        $options = [];

        if (filled($user->email)) {
            $options[] = [
                'type' => 'email',
                'label' => 'Send reset link',
                'masked_value' => $this->maskEmail((string) $user->email),
            ];
        }

        if (filled($user->phoneno)) {
            $options[] = [
                'type' => 'phone',
                'label' => 'Send code',
                'masked_value' => $this->maskPhone((string) $user->phoneno),
            ];
        }

        return $options;
    }

    public function sendEmailResetLink(AppUser $user): void
    {
        Password::broker(config('fortify.passwords'))
            ->sendResetLink([
                'email' => $user->email,
            ]);
    }

    public function sendPhoneOtp(AppUser $user): PasswordRecoveryOtp
    {
        $phone = trim((string) $user->phoneno);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'recovery' => self::SESSION_EXPIRED_MESSAGE,
            ]);
        }

        PasswordRecoveryOtp::query()
            ->where('user_id', $user->getKey())
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        $code = str_pad((string) random_int(0, (10 ** self::OTP_LENGTH) - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        $otp = PasswordRecoveryOtp::query()->create([
            'user_id' => $user->getKey(),
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::OTP_EXPIRES_IN_MINUTES),
            'attempts' => 0,
        ]);

        if (! $this->smsService->send($phone, $this->otpMessage($code))) {
            $otp->delete();

            throw ValidationException::withMessages([
                'recovery' => self::SMS_FAILURE_MESSAGE,
            ]);
        }

        return $otp;
    }

    public function verifyPhoneOtp(PasswordRecoveryOtp $otp, string $code): PasswordRecoveryOtp
    {
        if ($this->otpIsUnavailable($otp)) {
            throw $this->invalidCodeException();
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $attempts = $otp->attempts + 1;

            $otp->forceFill([
                'attempts' => $attempts,
                'used_at' => $attempts >= self::OTP_MAX_ATTEMPTS ? now() : null,
            ])->save();

            throw $this->invalidCodeException();
        }

        $otp->forceFill([
            'used_at' => now(),
        ])->save();

        return $otp;
    }

    public function maskEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $localPart = trim($localPart);
        $domain = trim($domain);

        if ($localPart === '' || $domain === '') {
            return '******';
        }

        $visible = Str::substr($localPart, 0, 1);
        $maskLength = max(1, Str::length($localPart) - 1);

        return $visible.str_repeat('*', $maskLength).'@'.$domain;
    }

    public function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '******';
        }

        $visible = Str::substr($digits, -4);
        $maskedLength = max(0, Str::length($digits) - Str::length($visible));

        return str_repeat('*', $maskedLength).$visible;
    }

    private function otpIsUnavailable(PasswordRecoveryOtp $otp): bool
    {
        return $otp->used_at !== null
            || now()->greaterThan($otp->expires_at)
            || $otp->attempts >= self::OTP_MAX_ATTEMPTS;
    }

    private function invalidCodeException(): ValidationException
    {
        return ValidationException::withMessages([
            'code' => self::INVALID_CODE_MESSAGE,
        ]);
    }

    private function otpMessage(string $code): string
    {
        $appName = trim((string) config('app.name', 'Your account'));

        return sprintf(
            '%s: Use %s to reset your password. This code expires in %d minutes.',
            $appName !== '' ? $appName : 'Your account',
            $code,
            self::OTP_EXPIRES_IN_MINUTES,
        );
    }
}
