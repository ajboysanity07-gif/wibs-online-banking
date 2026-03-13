<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfileController extends Controller
{
    public function show(AppUser $user): Response
    {
        $user->loadMissing('userProfile');

        return Inertia::render('admin/member-profile', [
            'member' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'acctno' => $user->acctno,
                'status' => $user->userProfile?->status,
                'created_at' => $user->created_at?->toDateTimeString(),
            ],
        ]);
    }
}
