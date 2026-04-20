<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\AppUser;
use App\Support\PasswordRecoveryState;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request): ?AppUser {
            $login = $request->input('email') ?? $request->input('username');

            if (! $login) {
                return null;
            }

            $user = AppUser::query()
                ->where('email', $login)
                ->orWhere('username', $login)
                ->first();

            if ($user !== null && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'recovery' => app(PasswordRecoveryState::class)->pageData($request),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/verify-member'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $login = $request->input('email') ?? $request->input('username') ?? '';
            $throttleKey = Str::transliterate(Str::lower($login).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('member-verification', function (Request $request) {
            $accountNumber = (string) $request->input('accntno', '');
            $throttleKey = Str::transliterate(Str::lower($accountNumber).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('username-suggestions', function (Request $request) {
            $verification = $request->session()->get('member_verification');
            $accountNumber = is_array($verification) ? (string) ($verification['acctno'] ?? '') : '';
            $throttleKey = Str::transliterate(Str::lower($accountNumber.'|'.$request->ip()));

            return Limit::perMinute(30)->by($throttleKey);
        });

        RateLimiter::for('password-recovery-lookup', function (Request $request) {
            $identifier = (string) $request->input('identifier', '');
            $throttleKey = Str::transliterate(Str::lower(trim($identifier).'|'.$request->ip()));

            return $this->passwordRecoveryLimit(
                5,
                $throttleKey,
                'Too many recovery lookups. Please wait a minute and try again.',
            );
        });

        RateLimiter::for('password-recovery-email', function (Request $request) {
            return $this->passwordRecoveryLimit(
                3,
                $this->passwordRecoveryUserThrottleKey($request, 'email'),
                'Too many recovery link requests. Please wait a few minutes and try again.',
                5,
            );
        });

        RateLimiter::for('password-recovery-phone-send', function (Request $request) {
            return $this->passwordRecoveryLimit(
                3,
                $this->passwordRecoveryUserThrottleKey($request, 'phone-send'),
                'Too many code requests. Please wait a few minutes and try again.',
                5,
            );
        });

        RateLimiter::for('password-recovery-phone-verify', function (Request $request) {
            return $this->passwordRecoveryLimit(
                3,
                $this->passwordRecoveryUserThrottleKey($request, 'phone-verify'),
                'Too many code verification attempts. Please wait a minute and try again.',
            );
        });

        RateLimiter::for('password-recovery-phone-reset', function (Request $request) {
            return $this->passwordRecoveryLimit(
                3,
                $this->passwordRecoveryUserThrottleKey($request, 'phone-reset'),
                'Too many password reset attempts. Please wait a few minutes and try again.',
                10,
            );
        });
    }

    private function passwordRecoveryLimit(
        int $maxAttempts,
        string $throttleKey,
        string $message,
        int $decayMinutes = 1,
    ): Limit {
        return Limit::perMinute($maxAttempts, $decayMinutes)
            ->by($throttleKey)
            ->response(fn () => response()->json([
                'message' => $message,
            ], 429));
    }

    private function passwordRecoveryUserThrottleKey(Request $request, string $scope): string
    {
        $state = $request->session()->get(PasswordRecoveryState::SESSION_KEY, []);
        $lookupUserId = is_array($state) ? ($state['lookup']['user_id'] ?? null) : null;
        $verifiedUserId = is_array($state) ? ($state['verified']['user_id'] ?? null) : null;
        $userKey = $verifiedUserId ?? $lookupUserId ?? 'guest';

        return Str::transliterate(Str::lower($scope.'|'.$userKey.'|'.$request->ip()));
    }
}
