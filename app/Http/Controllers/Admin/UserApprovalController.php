<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $pendingUsers = AppUser::query()
            ->whereHas('userProfile', function ($query) {
                $query->where('status', 'pending');
            })
            ->with('userProfile')
            ->orderBy('user_id')
            ->get([
                'user_id',
                'username',
                'email',
                'acctno',
                'phoneno',
                'created_at',
            ]);

        return Inertia::render('admin/pending-users', [
            'pendingUsers' => $pendingUsers,
        ]);
    }

    public function approve(Request $request, AppUser $user): RedirectResponse
    {
        $user->userProfile()->updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'status' => 'active',
                'reviewed_by' => $request->user()->user_id,
                'reviewed_at' => now(),
            ]
        );

        return back();
    }
}
