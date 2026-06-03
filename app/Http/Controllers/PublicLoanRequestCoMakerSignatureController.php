<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicLoanRequestCoMakerSignatureController extends Controller
{
    public function show(
        string $token,
    ): never {
        abort(Response::HTTP_NOT_FOUND);
    }

    public function store(
        Request $request,
        string $token,
    ): RedirectResponse {
        abort(Response::HTTP_NOT_FOUND);
    }
}
