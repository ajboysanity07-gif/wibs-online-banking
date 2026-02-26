<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\UserApprovalController;
use App\Http\Controllers\Auth\MemberVerificationController;
use App\Http\Controllers\Auth\PendingApprovalController;
use App\Http\Controllers\Auth\UsernameSuggestionController;
use Illuminate\Support\Facades\Route;
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

Route::get('client/dashboard', function () {
    return Inertia::render('client/dashboard');
})->middleware(['auth', 'approved', 'verified'])->name('client.dashboard');

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

    Route::get('users/pending', [UserApprovalController::class, 'index'])
        ->name('admin.users.pending');

    Route::patch('users/{user}/approve', [UserApprovalController::class, 'approve'])
        ->name('admin.users.approve');
});

require __DIR__.'/settings.php';
