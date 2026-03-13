<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLoanPaymentsExportRequest;
use App\Models\AppUser;
use App\Services\Admin\MemberLoans\MemberLoanExportService;
use Symfony\Component\HttpFoundation\Response;

class MemberLoanPaymentsExportController extends Controller
{
    public function __invoke(
        MemberLoanPaymentsExportRequest $request,
        AppUser $user,
        string $loanNumber,
        MemberLoanExportService $service,
    ): Response {
        $format = (string) $request->query('format', 'pdf');

        return $service->exportPayments(
            $user,
            $loanNumber,
            $format,
            $request->query('range'),
            $request->query('start'),
            $request->query('end'),
        );
    }
}
