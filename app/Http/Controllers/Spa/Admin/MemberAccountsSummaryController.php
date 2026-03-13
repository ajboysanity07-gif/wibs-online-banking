<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MemberAccountsSummaryResource;
use App\Models\AppUser;
use App\Services\Admin\MemberAccounts\MemberAccountsService;
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

        return response()->json([
            'ok' => true,
            'data' => [
                'summary' => (new MemberAccountsSummaryResource($summary))->resolve(),
            ],
        ]);
    }
}
