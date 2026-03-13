<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberStatusRequest;
use App\Models\AppUser;
use App\Services\Admin\MemberStatusService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserApprovalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/pending-users');
    }

    public function approve(
        MemberStatusRequest $request,
        AppUser $user,
        MemberStatusService $service,
    ): RedirectResponse {
        $service->approve($user, $request->user());

        return back();
    }
}
