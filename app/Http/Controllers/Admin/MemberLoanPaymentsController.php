<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MemberAccounts\Resources\MemberLoanResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLoanPaymentsRequest;
use App\Http\Resources\Admin\MemberLoanPaymentResource;
use App\Http\Resources\Admin\MemberLoanSummaryResource;
use App\Services\Admin\MemberLoans\MemberLoanExportService;
use App\Services\Admin\MemberLoans\MemberLoanService;
use App\Services\Admin\MembersService;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;

class MemberLoanPaymentsController extends Controller
{
    public function show(
        MemberLoanPaymentsRequest $request,
        string $user,
        string $loanNumber,
        MembersService $membersService,
        MemberLoanService $service,
    ): Response {
        $context = $membersService->resolveAccountContext($user);
        $member = $context['member'];
        $memberName = $context['memberName'];

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 10);
        $range = $request->query('range');
        $start = $request->query('start');
        $end = $request->query('end');

        $payload = $service->getPaymentsPageData(
            $member,
            $loanNumber,
            $range,
            $start,
            $end,
            $perPage,
            $page,
        );

        $paginator = $payload['payments'];

        return Inertia::render('admin/member-loan-payments', [
            'member' => [
                'member_id' => $context['memberKey'],
                'user_id' => $context['userId'],
                'member_name' => $memberName,
                'acctno' => $context['acctno'],
            ],
            'loan' => (new MemberLoanResource($payload['loan']))->resolve(),
            'summary' => (new MemberLoanSummaryResource($payload['summary']))->resolve(),
            'payments' => [
                'items' => MemberLoanPaymentResource::collection(
                    $paginator->items(),
                )->resolve(),
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
                'filters' => $payload['filters'],
                'openingBalance' => $payload['openingBalance'],
                'closingBalance' => $payload['closingBalance'],
            ],
        ]);
    }

    public function print(
        MemberLoanPaymentsRequest $request,
        string $user,
        string $loanNumber,
        MembersService $membersService,
        MemberLoanExportService $service,
    ): View {
        $context = $membersService->resolveAccountContext($user);

        return $service->renderPaymentsPrintView(
            $context['member'],
            $loanNumber,
            $request->query('range'),
            $request->query('start'),
            $request->query('end'),
        );
    }
}
