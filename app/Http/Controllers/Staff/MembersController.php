<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        abort_unless(
            $actor instanceof AppUser && $actor->hasPermission(Permission::MEMBER_VIEW),
            403,
        );

        return Inertia::render('staff/members');
    }
}
