<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $searchLike = $search !== ''
            ? '%'.addcslashes($search, '%_\\').'%'
            : null;

        $pendingQuery = AppUser::query()
            ->whereHas('userProfile', function ($query) {
                $query->where('status', 'pending');
            });

        $pendingCount = (clone $pendingQuery)->count();
        $pendingUsers = $pendingQuery
            ->orderBy('user_id')
            ->limit(10)
            ->get([
                'user_id',
                'username',
                'email',
                'acctno',
                'created_at',
            ]);

        $activeCount = AppUser::query()
            ->whereHas('userProfile', function ($query) {
                $query->where('status', 'active');
            })
            ->count();

        $totalCount = AppUser::query()->count();

        $searchResults = collect();

        if ($searchLike !== null) {
            $searchResults = AppUser::query()
                ->with('userProfile')
                ->where(function ($query) use ($searchLike) {
                    $query->where('acctno', 'like', $searchLike)
                        ->orWhere('username', 'like', $searchLike)
                        ->orWhere('email', 'like', $searchLike);
                })
                ->orderBy('user_id')
                ->limit(10)
                ->get([
                    'user_id',
                    'username',
                    'email',
                    'acctno',
                    'created_at',
                ])
                ->map(function (AppUser $user): array {
                    return [
                        'user_id' => $user->user_id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'acctno' => $user->acctno,
                        'status' => $user->userProfile?->status,
                        'created_at' => $user->created_at?->toDateTimeString(),
                    ];
                });
        }

        return Inertia::render('admin/dashboard', [
            'metrics' => [
                'pendingCount' => $pendingCount,
                'activeCount' => $activeCount,
                'totalCount' => $totalCount,
                'requestsCount' => null,
                'lastSync' => 'Manual WIBS Desktop processing',
            ],
            'pendingUsers' => $pendingUsers,
            'search' => $search,
            'searchResults' => $searchResults,
        ]);
    }
}
