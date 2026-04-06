<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Resources\MemberRecentAccountActionResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MemberDetailResource;
use App\Services\Admin\MembersService;
use Inertia\Inertia;
use Inertia\Response;

class MemberProfileController extends Controller
{
    public function show(
        string $member,
        MembersService $service,
        MemberAccountsService $accountsService,
    ): Response {
        $member = $service->getMemberDetail($member);
        $accountsSummary = null;
        $recentAccountActions = null;
        $accountContext = $service->resolveAccountContextFromMember($member);

        if ($accountContext !== null) {
            $summary = $accountsService->getSummary($accountContext['member']);
            $accountsSummary = (new MemberAccountsSummaryResource($summary))->resolve();

            $actionsPaginator = $accountsService->getPaginatedRecentActions(
                $accountContext['member'],
                5,
                1,
            );
            $recentAccountActions = [
                'items' => MemberRecentAccountActionResource::collection(
                    $actionsPaginator->items(),
                )->resolve(),
                'meta' => [
                    'page' => $actionsPaginator->currentPage(),
                    'perPage' => $actionsPaginator->perPage(),
                    'total' => $actionsPaginator->total(),
                    'lastPage' => $actionsPaginator->lastPage(),
                ],
            ];
        }

        return Inertia::render('admin/member-profile', [
            'member' => (new MemberDetailResource($member))->resolve(),
            'accountsSummary' => $accountsSummary,
            'recentAccountActions' => $recentAccountActions,
        ]);
    }
}
