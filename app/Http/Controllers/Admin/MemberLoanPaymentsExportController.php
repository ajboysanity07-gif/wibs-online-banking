<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLoanPaymentsExportRequest;
use App\Services\Admin\MemberLoans\MemberLoanExportService;
use App\Services\Admin\MembersService;
use Symfony\Component\HttpFoundation\Response;

class MemberLoanPaymentsExportController extends Controller
{
    public function __invoke(
        MemberLoanPaymentsExportRequest $request,
        string $user,
        string $loanNumber,
        MembersService $membersService,
        MemberLoanExportService $service,
    ): Response {
        $context = $membersService->resolveAccountContext($user);
        $format = (string) $request->query('format', 'pdf');
        $download = $request->boolean('download');

        return $service->exportPayments(
            $context['member'],
            $loanNumber,
            $format,
            $request->query('range'),
            $request->query('start'),
            $request->query('end'),
            $download,
        );
    }
}
