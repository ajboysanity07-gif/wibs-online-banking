<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Resources\MemberLoanResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Services\Admin\MembersService;
use Inertia\Inertia;
use Inertia\Response;

class MemberLoansController extends Controller
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
        $paginator = $service->getPaginatedLoans($member, 10, 1);

        return Inertia::render('admin/member-loans', [
            'member' => [
                'member_id' => $context['memberKey'],
                'user_id' => $context['userId'],
                'member_name' => $memberName,
                'acctno' => $context['acctno'],
            ],
            'summary' => (new MemberAccountsSummaryResource($summary))->resolve(),
            'loans' => [
                'items' => MemberLoanResource::collection($paginator->items())->resolve(),
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
