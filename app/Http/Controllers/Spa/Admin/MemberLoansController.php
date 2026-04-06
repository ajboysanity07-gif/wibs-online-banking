<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Domains\MemberAccounts\Resources\MemberLoanResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberAccountLoansRequest;
use App\Services\Admin\MembersService;
use Illuminate\Http\JsonResponse;

class MemberLoansController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberAccountLoansRequest $request,
        string $user,
        MembersService $membersService,
        MemberAccountsService $service,
    ): JsonResponse {
        $context = $membersService->resolveAccountContext($user);
        $member = $context['member'];

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 10);

        $paginator = $service->getPaginatedLoans($member, $perPage, $page);
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
