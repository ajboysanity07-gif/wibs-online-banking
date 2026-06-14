<?php

namespace App\Http\Controllers\Spa;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Spa\LoginRequest;
use App\Http\Requests\Spa\RegisterRequest;
use App\Models\AppUser;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

class AuthController extends Controller
{
    public function __construct(
        private LoanWorkflowWorkspaceService $workspaceService,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->loadMissing(
            'adminProfile',
            'userProfile',
            'roles.permissions',
            'staffAccessControl',
        );
        $status = $user->isAdminOnly() ? 'active' : $user->userProfile?->status;
        $availableWorkspaces = $this->workspaceService->availableWorkspaces($user);

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
                    'is_hybrid' => $this->workspaceService->hasMultipleWorkspaces($user),
                    'experience' => $user->experienceType(),
                    'has_active_staff_access' => $user->hasActiveStaffAccess(),
                    'can_access_loan_workflow' => $this->workspaceService->canAccessLoanWorkflow($user),
                    'available_workspaces' => $availableWorkspaces,
                    'active_workspace' => $this->workspaceService->resolveActiveWorkspace($request, $user),
                    'has_multiple_workspaces' => count($availableWorkspaces) > 1,
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

        if ($this->requiresTwoFactorChallenge($user)) {
            $storedWorkspace = $request->session()->get('active_workspace');

            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
                'url.intended' => $this->postAuthRedirect(
                    $request,
                    $user,
                    is_string($storedWorkspace) ? $storedWorkspace : null,
                ),
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return response()->json([
                'ok' => true,
                'requires_two_factor' => true,
                'redirect_to' => route('two-factor.login', absolute: false),
            ]);
        }

        $storedWorkspace = $request->session()->get('active_workspace');

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if (is_string($storedWorkspace) && $storedWorkspace !== '') {
            $request->session()->put('active_workspace', $storedWorkspace);
        }

        return response()->json([
            'ok' => true,
            'redirect_to' => $this->postAuthRedirect(
                $request,
                $user,
                is_string($storedWorkspace) ? $storedWorkspace : null,
            ),
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
            'redirect_to' => $this->postAuthRedirect($request, $user),
        ]);
    }

    private function postAuthRedirect(
        Request $request,
        AppUser $user,
        ?string $preferredWorkspace = null,
    ): string {
        if (
            is_string($preferredWorkspace)
            && $this->workspaceService->canAccessWorkspace(
                $user,
                $preferredWorkspace,
            )
        ) {
            return $this->workspaceService->defaultRouteForWorkspace(
                $user,
                $preferredWorkspace,
            );
        }

        return $this->workspaceService->postAuthenticationRedirectPath(
            $request,
            $user,
        );
    }

    private function requiresTwoFactorChallenge(AppUser $user): bool
    {
        return $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at !== null;
    }
}
