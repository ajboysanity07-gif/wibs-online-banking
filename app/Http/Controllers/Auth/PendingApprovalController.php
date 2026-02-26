<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PendingApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('auth/pending-approval');
    }
}
