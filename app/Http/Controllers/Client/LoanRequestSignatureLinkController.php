<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoanRequestSignatureLinkController extends Controller
{
    public function store(
        Request $request,
        string $role,
    ): JsonResponse {
        abort(Response::HTTP_NOT_FOUND);
    }
}
