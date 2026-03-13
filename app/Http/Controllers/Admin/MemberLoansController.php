<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MemberAccountsSummaryResource;
use App\Http\Resources\Admin\MemberLoanResource;
use App\Models\AppUser;
use App\Services\Admin\MemberAccounts\MemberAccountsService;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MemberLoansController extends Controller
{
    public function show(AppUser $user, MemberAccountsService $service): Response
    {
        $memberName = $user->username;

        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
            $memberName = $user->wmaster?->bname ?? $memberName;
        }

        $summary = $service->getSummary($user);
        $paginator = $service->getPaginatedLoans($user, 10, 1);

        return Inertia::render('admin/member-loans', [
            'member' => [
                'user_id' => $user->user_id,
                'member_name' => $memberName,
                'acctno' => $user->acctno,
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
