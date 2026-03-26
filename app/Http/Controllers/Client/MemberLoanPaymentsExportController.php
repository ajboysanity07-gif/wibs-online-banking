<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\MemberLoanPaymentsExportRequest;
use App\Services\Admin\MemberLoans\MemberLoanExportService;
use Symfony\Component\HttpFoundation\Response;

class MemberLoanPaymentsExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberLoanPaymentsExportRequest $request,
        string $loanNumber,
        MemberLoanExportService $service,
    ): Response {
        $user = $request->user();

        if ($user === null) {
            abort(404);
        }

        $format = (string) $request->query('format', 'pdf');
        $download = $request->boolean('download');

        return $service->exportPayments(
            $user,
            $loanNumber,
            $format,
            $request->query('range'),
            $request->query('start'),
            $request->query('end'),
            $download,
        );
    }
}
