<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\LoanRequestCoMakerSignatureSubmitRequest;
use App\Services\LoanRequests\LoanRequestSignatureLinkService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicLoanRequestCoMakerSignatureController extends Controller
{
    public function show(
        string $token,
        LoanRequestSignatureLinkService $service,
    ): Response {
        $payload = $service->resolvePublicPage($token);

        return Inertia::render('public/loan-request-co-maker-signature', [
            'status' => $payload['status'],
            'signing' => $payload['signing'],
            'submitUrl' => route('loan-requests.sign.co-maker.store', [
                'token' => $token,
            ]),
            'recentlySigned' => request()->boolean('signed'),
        ]);
    }

    public function store(
        LoanRequestCoMakerSignatureSubmitRequest $request,
        string $token,
        LoanRequestSignatureLinkService $service,
    ): RedirectResponse {
        $service->consume(
            $token,
            (string) $request->validated('signature_data'),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('loan-requests.sign.co-maker.show', [
            'token' => $token,
            'signed' => 1,
        ]);
    }
}
