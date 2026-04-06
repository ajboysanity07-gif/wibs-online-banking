<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MemberAccounts\Resources\MemberLoanResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MemberLoanScheduleResource;
use App\Http\Resources\Admin\MemberLoanSummaryResource;
use App\Services\Admin\MemberLoans\MemberLoanService;
use App\Services\Admin\MembersService;
use Inertia\Inertia;
use Inertia\Response;

class MemberLoanScheduleController extends Controller
{
    public function show(
        string $user,
        string $loanNumber,
        MembersService $membersService,
        MemberLoanService $service,
    ): Response {
        $context = $membersService->resolveAccountContext($user);
        $member = $context['member'];
        $memberName = $context['memberName'];

        $payload = $service->getSchedulePageData($member, $loanNumber);

        return Inertia::render('admin/member-loan-schedule', [
            'member' => [
                'member_id' => $context['memberKey'],
                'user_id' => $context['userId'],
                'member_name' => $memberName,
                'acctno' => $context['acctno'],
            ],
            'loan' => (new MemberLoanResource($payload['loan']))->resolve(),
            'summary' => (new MemberLoanSummaryResource($payload['summary']))->resolve(),
            'schedule' => [
                'items' => MemberLoanScheduleResource::collection(
                    $payload['schedule'],
                )->resolve(),
            ],
        ]);
    }
}
