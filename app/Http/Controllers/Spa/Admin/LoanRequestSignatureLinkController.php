<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LoanRequestSignatureLinkController extends Controller
{
    public function store(
        LoanRequest $loanRequest,
        string $role,
    ): JsonResponse {
        abort(Response::HTTP_NOT_FOUND);
    }
}
