<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;

class MemberAccountsSummaryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AppUser $user, MemberAccountsService $service): JsonResponse
    {
        $user->loadMissing('adminProfile');

        if ($user->adminProfile !== null) {
            abort(404);
        }

        $summary = $service->getSummary($user);
        $ledgerSummary = $service->getLoanSecurityLedgerSummary($user);
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
