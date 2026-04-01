<?php

namespace App\Http\Controllers\Admin;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Resources\MemberLoanSecurityLedgerResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MemberSavingsController extends Controller
{
    public function show(AppUser $user, MemberAccountsService $service): Response
    {
        $memberName = $user->username;

        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
            $memberName = $user->wmaster?->displayName() ?: $memberName;
        }

        $summary = $service->getSummary($user);
        $ledgerSummary = $service->getLoanSecurityLedgerSummary($user);
        $summary['currentLoanSecurityBalance'] = $ledgerSummary['latestBalance'];
        $summary['lastLoanSecurityTransactionDate'] = $ledgerSummary['lastTransactionDate'];
        $paginator = $service->getPaginatedLoanSecurity($user, 10, 1);

        return Inertia::render('admin/member-savings', [
            'member' => [
                'user_id' => $user->user_id,
                'member_name' => $memberName,
                'acctno' => $user->acctno,
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
