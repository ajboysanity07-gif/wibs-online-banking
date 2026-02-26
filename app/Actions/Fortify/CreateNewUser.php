<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\AppUser;
use App\Models\UserProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    private const VERIFICATION_SESSION_KEY = 'member_verification';

    private const VERIFICATION_TTL_MINUTES = 15;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): AppUser
    {
        $verification = $this->verifiedMember();

        Validator::make([
            ...$input,
            'acctno' => $verification['acctno'],
        ], [
            ...$this->profileRules(requirePhone: false),
            'acctno' => ['required', 'string', Rule::unique(AppUser::class, 'acctno')],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = AppUser::create([
            'username' => $input['username'],
            'email' => $input['email'],
            'phoneno' => filled($input['phoneno'] ?? null) ? $input['phoneno'] : null,
            'acctno' => $verification['acctno'],
            'password' => $input['password'],
        ]);

        UserProfile::create([
            'user_id' => $user->user_id,
            'role' => 'client',
            'status' => 'pending',
        ]);

        request()->session()->forget(self::VERIFICATION_SESSION_KEY);

        return $user;
    }

    /**
     * @return array{acctno: string, verified_at: int}
     */
    private function verifiedMember(): array
    {
        $session = request()->session();
        $verification = $session->get(self::VERIFICATION_SESSION_KEY);

        if (! is_array($verification)) {
            throw ValidationException::withMessages([
                'verification' => 'Please verify your membership before creating a login.',
            ]);
        }

        $acctno = $verification['acctno'] ?? null;
        $verifiedAt = $verification['verified_at'] ?? null;

        if (! is_string($acctno) || $acctno === '' || ! is_numeric($verifiedAt)) {
            $session->forget(self::VERIFICATION_SESSION_KEY);

            throw ValidationException::withMessages([
                'verification' => 'Please verify your membership before creating a login.',
            ]);
        }

        $expiresAt = CarbonImmutable::createFromTimestamp((int) $verifiedAt)
            ->addMinutes(self::VERIFICATION_TTL_MINUTES);

        if (now()->greaterThan($expiresAt)) {
            $session->forget(self::VERIFICATION_SESSION_KEY);

            throw ValidationException::withMessages([
                'verification' => 'Please verify your membership before creating a login.',
            ]);
        }

        return [
            'acctno' => $acctno,
            'verified_at' => (int) $verifiedAt,
        ];
    }
}
