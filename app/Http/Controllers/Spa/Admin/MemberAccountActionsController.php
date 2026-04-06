<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Domains\MemberAccounts\Resources\MemberRecentAccountActionResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberAccountActionsRequest;
use App\Services\Admin\MembersService;
use Illuminate\Http\JsonResponse;

class MemberAccountActionsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberAccountActionsRequest $request,
        string $user,
        MembersService $membersService,
        MemberAccountsService $service,
    ): JsonResponse {
        $context = $membersService->resolveAccountContext($user);
        $member = $context['member'];

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 5);

        $paginator = $service->getPaginatedRecentActions($member, $perPage, $page);
        $items = MemberRecentAccountActionResource::collection($paginator->items())->resolve();

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
