<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class RequestsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/requests');
    }

    public function reported(): Response
    {
        return Inertia::render('admin/reported-requests');
    }
}
