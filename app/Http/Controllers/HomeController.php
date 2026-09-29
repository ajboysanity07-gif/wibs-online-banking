<?php

namespace App\Http\Controllers;

use App\Services\LoanRequests\LoanRequestLookupService;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function __invoke(LoanRequestLookupService $loanRequestLookup): Response
    {
        return Inertia::render('welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'loanTypes' => $loanRequestLookup->getLoanTypes()->values()->all(),
        ]);
    }
}
