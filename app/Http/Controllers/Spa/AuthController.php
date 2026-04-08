<?php

namespace App\Http\Controllers\Spa;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Spa\LoginRequest;
use App\Http\Requests\Spa\RegisterRequest;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->loadMissing('adminProfile', 'userProfile');
        $status = $user->isAdminOnly() ? 'active' : $user->userProfile?->status;

        return response()->json([
            'ok' => true,
            'data' => [
                'user' => [
                    'id' => $user->getKey(),
                    'user_id' => $user->user_id,
                    'display_code' => $user->display_code,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $status,
                    'acctno' => $user->acctno,
                    'is_admin' => $user->isAdmin(),
                    'is_superadmin' => $user->isSuperadmin(),
                    'has_member_access' => $user->hasMemberAccess(),
                    'is_admin_only' => $user->isAdminOnly(),
                    'is_hybrid' => $user->isHybrid(),
                    'experience' => $user->experienceType(),
                ],
            ],
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $login = $request->input('email') ?? $request->input('username');
        $password = (string) $request->input('password');

        if (! is_string($login) || trim($login) === '') {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = AppUser::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect_to' => $this->postAuthRedirect($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
            'redirect_to' => '/',
        ]);
    }

    public function register(RegisterRequest $request, CreateNewUser $creator): JsonResponse
    {
        $user = $creator->create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect_to' => $this->postAuthRedirect($user),
        ]);
    }

    private function postAuthRedirect(AppUser $user): string
    {
        $user->loadMissing('adminProfile', 'userProfile');

        $experience = $user->experienceType();

        if (
            $experience === AppUser::EXPERIENCE_SUPERADMIN
            || $experience === AppUser::EXPERIENCE_ADMIN_ONLY
        ) {
            return '/admin/dashboard';
        }

        if ($experience === AppUser::EXPERIENCE_USER_ADMIN) {
            return '/dashboard';
        }

        if ($user->userProfile?->status === 'suspended') {
            return '/pending-approval';
        }

        if (! $user->memberApplicationProfileIsComplete()) {
            return '/settings/profile?onboarding=1';
        }

        return '/client/dashboard';
    }
}
