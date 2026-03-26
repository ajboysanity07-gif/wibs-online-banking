<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLoanPaymentsRequest;
use App\Http\Resources\Admin\MemberLoanPaymentResource;
use App\Http\Resources\Admin\MemberLoanResource;
use App\Http\Resources\Admin\MemberLoanSummaryResource;
use App\Models\AppUser;
use App\Services\Admin\MemberLoans\MemberLoanExportService;
use App\Services\Admin\MemberLoans\MemberLoanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MemberLoanPaymentsController extends Controller
{
    public function show(
        MemberLoanPaymentsRequest $request,
        AppUser $user,
        string $loanNumber,
        MemberLoanService $service,
    ): Response {
        $memberName = $user->username;

        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
            $memberName = $user->wmaster?->bname ?? $memberName;
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 10);
        $range = $request->query('range');
        $start = $request->query('start');
        $end = $request->query('end');

        $payload = $service->getPaymentsPageData(
            $user,
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
                'user_id' => $user->user_id,
                'member_name' => $memberName,
                'acctno' => $user->acctno,
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
        AppUser $user,
        string $loanNumber,
        MemberLoanExportService $service,
    ): View {
        return $service->renderPaymentsPrintView(
            $user,
            $loanNumber,
            $request->query('range'),
            $request->query('start'),
            $request->query('end'),
        );
    }
}
