<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('superadmin/staff');
    }
}
