<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Services\Admin\MembersService;
use Illuminate\Http\JsonResponse;

class MemberAccountsSummaryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        string $user,
        MembersService $membersService,
        MemberAccountsService $service,
    ): JsonResponse {
        $context = $membersService->resolveAccountContext($user);
        $member = $context['member'];

        $summary = $service->getSummary($member);
        $ledgerSummary = $service->getLoanSecurityLedgerSummary($member);
        $summary['currentLoanSecurityBalance'] = $ledgerSummary['latestBalance'];
        $summary['lastLoanSecurityTransactionDate'] = $ledgerSummary['lastTransactionDate'];

        return response()->json([
            'ok' => true,
            'data' => [
                'summary' => (new MemberAccountsSummaryResource($summary))->resolve(),
            ],
        ]);
    }
}
