<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\MemberLoanPaymentsController;
use App\Http\Controllers\Admin\MemberLoanPaymentsExportController;
use App\Http\Controllers\Admin\MemberLoanScheduleController;
use App\Http\Controllers\Admin\MemberLoansController;
use App\Http\Controllers\Admin\MemberProfileController;
use App\Http\Controllers\Admin\MemberSavingsController;
use App\Http\Controllers\Admin\OrganizationSettingsController;
use App\Http\Controllers\Admin\RequestsController;
use App\Http\Controllers\Admin\UserApprovalController;
use App\Http\Controllers\Admin\WatchlistController;
use App\Http\Controllers\Auth\MemberVerificationController;
use App\Http\Controllers\Auth\PendingApprovalController;
use App\Http\Controllers\Auth\UsernameSuggestionController;
use App\Http\Controllers\Client\MemberLoanPaymentsController as ClientMemberLoanPaymentsController;
use App\Http\Controllers\Client\MemberLoanScheduleController as ClientMemberLoanScheduleController;
use App\Http\Controllers\Client\MemberLoansController as ClientMemberLoansController;
use App\Http\Controllers\Client\MemberSavingsController as ClientMemberSavingsController;
use App\Http\Controllers\Spa\Admin\AccountSummaryController as SpaAccountSummaryController;
use App\Http\Controllers\Spa\Admin\DashboardDataController as SpaDashboardDataController;
use App\Http\Controllers\Spa\Admin\MemberAccountActionsController as SpaMemberAccountActionsController;
use App\Http\Controllers\Spa\Admin\MemberAccountsSummaryController as SpaMemberAccountsSummaryController;
use App\Http\Controllers\Spa\Admin\MemberLoanPaymentsController as SpaMemberLoanPaymentsController;
use App\Http\Controllers\Spa\Admin\MemberLoanScheduleController as SpaMemberLoanScheduleController;
use App\Http\Controllers\Spa\Admin\MemberLoansController as SpaMemberLoansController;
use App\Http\Controllers\Spa\Admin\MemberSavingsController as SpaMemberSavingsController;
use App\Http\Controllers\Spa\Admin\MembersController as SpaMembersController;
use App\Http\Controllers\Spa\Admin\MemberStatusController as SpaMemberStatusController;
use App\Http\Controllers\Spa\Admin\PendingApprovalController as SpaPendingApprovalController;
use App\Http\Controllers\Spa\Admin\RequestsController as SpaRequestsController;
use App\Http\Controllers\Spa\Admin\UserApprovalController as SpaUserApprovalController;
use App\Http\Controllers\Spa\Admin\WatchlistController as SpaWatchlistController;
use App\Http\Controllers\Spa\AuthController as SpaAuthController;
use App\Http\Controllers\Spa\MemberVerificationController as SpaMemberVerificationController;
use App\Http\Controllers\Spa\UsernameSuggestionController as SpaUsernameSuggestionController;
use App\Http\Resources\Admin\MemberAccountsSummaryResource;
use App\Http\Resources\Admin\MemberRecentAccountActionResource;
use App\Services\Admin\MemberAccounts\MemberAccountsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::post('register/verify', [MemberVerificationController::class, 'store'])
        ->middleware('throttle:member-verification')
        ->name('register.verify');

    Route::get('register/username-suggestions', UsernameSuggestionController::class)
        ->middleware('throttle:username-suggestions')
        ->name('register.username-suggestions');

    Route::get('register/create', [MemberVerificationController::class, 'create'])
        ->name('register.create');
});

Route::prefix('spa')->middleware('web')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('auth/login', [SpaAuthController::class, 'login'])
            ->middleware('throttle:login');

        Route::post('member/verify', [SpaMemberVerificationController::class, 'store'])
            ->middleware('throttle:member-verification');

        Route::get('username/suggestions', SpaUsernameSuggestionController::class)
            ->middleware(['member-verified', 'throttle:username-suggestions']);

        Route::post('auth/register', [SpaAuthController::class, 'register'])
            ->middleware('member-verified');
    });

    Route::middleware('auth')->group(function () {
        Route::get('auth/me', [SpaAuthController::class, 'me']);
        Route::post('auth/logout', [SpaAuthController::class, 'logout']);
    });

    Route::middleware(['auth', 'admin', 'verified'])->group(function () {
        Route::patch('admin/users/{user}/approve', [SpaUserApprovalController::class, 'approve']);
        Route::get('admin/summary', SpaAccountSummaryController::class);
        Route::get('admin/dashboard/summary', SpaDashboardDataController::class);
        Route::get('admin/members', [SpaMembersController::class, 'index']);
        Route::get('admin/members/{user}', [SpaMembersController::class, 'show']);
        Route::patch('admin/members/{user}/approve', [SpaMemberStatusController::class, 'approve']);
        Route::patch('admin/members/{user}/suspend', [SpaMemberStatusController::class, 'suspend']);
        Route::patch('admin/members/{user}/reactivate', [SpaMemberStatusController::class, 'reactivate']);
        Route::get('admin/pending-approvals', SpaPendingApprovalController::class);
        Route::get('admin/requests', SpaRequestsController::class);
        Route::get('admin/watchlist', SpaWatchlistController::class);
    });
});

Route::prefix('admin/api')->middleware(['auth', 'admin', 'verified'])->group(function () {
    Route::get('members/{user}/accounts/summary', SpaMemberAccountsSummaryController::class);
    Route::get('members/{user}/accounts/actions', SpaMemberAccountActionsController::class);
    Route::get('members/{user}/accounts/loans', SpaMemberLoansController::class);
    Route::get('members/{user}/accounts/savings', SpaMemberSavingsController::class);
    Route::get('members/{user}/loans/{loanNumber}/schedule', SpaMemberLoanScheduleController::class);
    Route::get('members/{user}/loans/{loanNumber}/payments', SpaMemberLoanPaymentsController::class);
});

Route::get('client/dashboard', function (
    Request $request,
    MemberAccountsService $service,
) {
    $user = $request->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    $user->loadMissing('userProfile', 'adminProfile');

    if ($user->adminProfile !== null) {
        return redirect()->route('admin.dashboard');
    }

    $memberName = $user->username;

    try {
        if (Schema::hasTable('wmaster')) {
            $wmasterName = $user->wmaster()->value('bname');

            if (is_string($wmasterName) && trim($wmasterName) !== '') {
                $memberName = $wmasterName;
            }
        }
    } catch (Throwable $exception) {
        report($exception);
    }

    $summary = null;
    $summaryError = null;

    try {
        $summary = $service->getSummary($user);
    } catch (Throwable $exception) {
        report($exception);
        $summaryError = 'Unable to load summary.';
    }

    $sanitizeString = static function (?string $value): ?string {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding(
                $value,
                'UTF-8',
                'UTF-8,ISO-8859-1,Windows-1252',
            );

            if (is_string($converted) && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($converted === false) {
            return '';
        }

        return $converted;
    };

    $sanitize = static function (mixed $value) use (&$sanitize, $sanitizeString): mixed {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = $sanitize($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $sanitizeString($value);
        }

        return $value;
    };

    $memberPayload = $sanitize([
        'name' => $memberName,
        'username' => $user->username,
        'email' => $user->email,
        'phone' => $user->phoneno,
        'acctno' => $user->acctno,
        'status' => $user->userProfile?->status,
        'created_at' => $user->created_at?->toDateTimeString(),
        'avatar_url' => $user->avatar,
    ]);
    $summaryPayload = $summary === null
        ? null
        : $sanitize((new MemberAccountsSummaryResource($summary))->resolve());

    $actionsPage = (int) $request->query('actions_page', 1);
    $actionsPage = max(1, $actionsPage);
    $actionsPerPage = 5;
    $recentAccountActionsPayload = null;
    $recentAccountActionsError = null;

    try {
        $paginator = $service->getPaginatedRecentActions(
            $user,
            $actionsPerPage,
            $actionsPage,
        );
        $items = MemberRecentAccountActionResource::collection(
            $paginator->items(),
        )->resolve();
        $recentAccountActionsPayload = [
            'items' => $items,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    } catch (Throwable $exception) {
        report($exception);
        $recentAccountActionsError = 'Unable to load account actions.';
    }

    $recentAccountActionsPayload = $recentAccountActionsPayload === null
        ? null
        : $sanitize($recentAccountActionsPayload);

    return Inertia::render('client/dashboard', [
        'member' => $memberPayload,
        'summary' => $summaryPayload,
        'summaryError' => $summaryError,
        'recentAccountActions' => $recentAccountActionsPayload,
        'recentAccountActionsError' => $recentAccountActionsError,
    ]);
})->middleware(['auth', 'approved', 'verified'])->name('client.dashboard');

Route::get('client/loans', ClientMemberLoansController::class)
    ->middleware(['auth', 'approved', 'verified'])
    ->name('client.loans');

Route::get('client/loans/{loanNumber}/schedule', ClientMemberLoanScheduleController::class)
    ->middleware(['auth', 'approved', 'verified'])
    ->name('client.loan-schedule');

Route::get('client/loans/{loanNumber}/payments', ClientMemberLoanPaymentsController::class)
    ->middleware(['auth', 'approved', 'verified'])
    ->name('client.loan-payments');

Route::get('client/savings', ClientMemberSavingsController::class)
    ->middleware(['auth', 'approved', 'verified'])
    ->name('client.savings');

Route::get('dashboard', function () {
    $user = request()->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    $user->loadMissing('adminProfile', 'userProfile');

    if ($user->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    if ($user->userProfile?->status !== 'active') {
        return redirect()->route('pending-approval');
    }

    return redirect()->route('client.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('pending-approval', [PendingApprovalController::class, 'index'])
    ->middleware('auth')
    ->name('pending-approval');

Route::prefix('admin')->middleware(['auth', 'admin', 'verified'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    })->name('admin.home');

    Route::get('dashboard', [AdminDashboardController::class, 'index'])
        ->name('admin.dashboard');

    Route::get('members/{user}', [MemberProfileController::class, 'show'])
        ->name('admin.members.show');

    Route::get('members/{user}/loans', [MemberLoansController::class, 'show'])
        ->name('admin.members.loans');

    Route::get('members/{user}/loans/{loanNumber}/schedule', [MemberLoanScheduleController::class, 'show'])
        ->name('admin.members.loan-schedule');

    Route::get('members/{user}/loans/{loanNumber}/payments', [MemberLoanPaymentsController::class, 'show'])
        ->name('admin.members.loan-payments');

    Route::get(
        'members/{user}/loans/{loanNumber}/payments/export',
        MemberLoanPaymentsExportController::class,
    )->name('admin.members.loan-payments-export');

    Route::get('members/{user}/savings', [MemberSavingsController::class, 'show'])
        ->name('admin.members.savings');

    Route::get('requests', [RequestsController::class, 'index'])
        ->name('admin.requests.index');

    Route::get('users/pending', [UserApprovalController::class, 'index'])
        ->name('admin.users.pending');

    Route::patch('users/{user}/approve', [UserApprovalController::class, 'approve'])
        ->name('admin.users.approve');

    Route::get('watchlist', [WatchlistController::class, 'index'])
        ->name('admin.watchlist.index');

    Route::get('settings/organization', [OrganizationSettingsController::class, 'index'])
        ->name('admin.settings.organization');

    Route::patch('settings/organization', [OrganizationSettingsController::class, 'update'])
        ->name('admin.settings.organization.update');
});

require __DIR__.'/settings.php';
