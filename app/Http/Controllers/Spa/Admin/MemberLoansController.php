<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Domains\MemberAccounts\Resources\MemberLoanResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberAccountLoansRequest;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;

class MemberLoansController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberAccountLoansRequest $request,
        AppUser $user,
        MemberAccountsService $service,
    ): JsonResponse {
        $user->loadMissing('adminProfile');

        if ($user->adminProfile !== null) {
            abort(404);
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 10);

        $paginator = $service->getPaginatedLoans($user, $perPage, $page);
        $items = MemberLoanResource::collection($paginator->items())->resolve();

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
