<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\LoanRequestController as AdminLoanRequestController;
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
use App\Http\Controllers\Api\BirthplaceSearchController;
use App\Http\Controllers\Api\CitySearchController;
use App\Http\Controllers\Api\ProvinceSearchController;
use App\Http\Controllers\Auth\MemberVerificationController;
use App\Http\Controllers\Auth\PendingApprovalController;
use App\Http\Controllers\Auth\UsernameSuggestionController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\LoanRequestController;
use App\Http\Controllers\Client\MemberLoanPaymentsController as ClientMemberLoanPaymentsController;
use App\Http\Controllers\Client\MemberLoanPaymentsExportController as ClientMemberLoanPaymentsExportController;
use App\Http\Controllers\Client\MemberLoanScheduleController as ClientMemberLoanScheduleController;
use App\Http\Controllers\Client\MemberLoansController as ClientMemberLoansController;
use App\Http\Controllers\Client\MemberSavingsController as ClientMemberSavingsController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\HomeController;
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
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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

Route::prefix('api/locations')->middleware(['auth', 'approved'])->group(function () {
    Route::get('birthplaces', BirthplaceSearchController::class)
        ->name('api.locations.birthplaces');
    Route::get('provinces', ProvinceSearchController::class)
        ->name('api.locations.provinces');
    Route::get('cities', CitySearchController::class)
        ->name('api.locations.cities');
});

Route::get('client/dashboard', ClientDashboardController::class)
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.dashboard');

Route::get('client/loans', ClientMemberLoansController::class)
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loans');

Route::get('client/loans/request', [LoanRequestController::class, 'create'])
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-requests.create');

Route::post('client/loans/request', [LoanRequestController::class, 'store'])
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-requests.store');

Route::patch('client/loans/request', [LoanRequestController::class, 'draft'])
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-requests.draft');

Route::get('client/loans/requests/{loanRequest}', [LoanRequestController::class, 'show'])
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-requests.show');

Route::get('client/loans/requests/{loanRequest}/pdf', [LoanRequestController::class, 'pdf'])
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-requests.pdf');

Route::get('client/loans/requests/{loanRequest}/print', [LoanRequestController::class, 'print'])
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-requests.print');

Route::get('client/loans/{loanNumber}/schedule', ClientMemberLoanScheduleController::class)
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-schedule');

Route::get('client/loans/{loanNumber}/payments', ClientMemberLoanPaymentsController::class)
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-payments');

Route::get(
    'client/loans/{loanNumber}/payments/print',
    [ClientMemberLoanPaymentsController::class, 'print'],
)
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-payments.print');

Route::get(
    'client/loans/{loanNumber}/payments/export',
    ClientMemberLoanPaymentsExportController::class,
)
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.loan-payments.export');

Route::get('client/savings', ClientMemberSavingsController::class)
    ->middleware(['auth', 'approved', 'verified', 'member-profile-complete'])
    ->name('client.savings');

Route::get('dashboard', DashboardRedirectController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('pending-approval', [PendingApprovalController::class, 'index'])
    ->middleware('auth')
    ->name('pending-approval');

Route::prefix('admin')->middleware(['auth', 'admin', 'verified'])->group(function () {
    Route::redirect('/', '/admin/dashboard')->name('admin.home');

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

    Route::get(
        'members/{user}/loans/{loanNumber}/payments/print',
        [MemberLoanPaymentsController::class, 'print'],
    )->name('admin.members.loan-payments-print');

    Route::get('members/{user}/savings', [MemberSavingsController::class, 'show'])
        ->name('admin.members.savings');

    Route::get('requests', [RequestsController::class, 'index'])
        ->name('admin.requests.index');

    Route::get('requests/{loanRequest}', [AdminLoanRequestController::class, 'show'])
        ->name('admin.requests.show');

    Route::get('requests/{loanRequest}/pdf', [AdminLoanRequestController::class, 'pdf'])
        ->name('admin.requests.pdf');

    Route::get('requests/{loanRequest}/print', [AdminLoanRequestController::class, 'print'])
        ->name('admin.requests.print');

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
