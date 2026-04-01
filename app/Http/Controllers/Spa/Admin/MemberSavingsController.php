<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Domains\MemberAccounts\Resources\MemberLoanSecurityLedgerResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberAccountSavingsRequest;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;

class MemberSavingsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberAccountSavingsRequest $request,
        AppUser $user,
        MemberAccountsService $service,
    ): JsonResponse {
        $user->loadMissing('adminProfile');

        if ($user->adminProfile !== null) {
            abort(404);
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 10);

        $paginator = $service->getPaginatedLoanSecurity($user, $perPage, $page);
        $items = MemberLoanSecurityLedgerResource::collection($paginator->items())->resolve();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
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
