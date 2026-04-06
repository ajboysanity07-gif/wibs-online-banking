<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Resources\MemberLoanSecurityLedgerResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Services\Admin\MembersService;
use Inertia\Inertia;
use Inertia\Response;

class MemberSavingsController extends Controller
{
    public function show(
        string $user,
        MembersService $membersService,
        MemberAccountsService $service,
    ): Response {
        $context = $membersService->resolveAccountContext($user);
        $member = $context['member'];
        $memberName = $context['memberName'];

        $summary = $service->getSummary($member);
        $ledgerSummary = $service->getLoanSecurityLedgerSummary($member);
        $summary['currentLoanSecurityBalance'] = $ledgerSummary['latestBalance'];
        $summary['lastLoanSecurityTransactionDate'] = $ledgerSummary['lastTransactionDate'];
        $paginator = $service->getPaginatedLoanSecurity($member, 10, 1);

        return Inertia::render('admin/member-savings', [
            'member' => [
                'member_id' => $context['memberKey'],
                'user_id' => $context['userId'],
                'member_name' => $memberName,
                'acctno' => $context['acctno'],
            ],
            'summary' => (new MemberAccountsSummaryResource($summary))->resolve(),
            'savings' => [
                'items' => MemberLoanSecurityLedgerResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}
