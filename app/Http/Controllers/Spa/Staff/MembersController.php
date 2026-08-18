<?php

namespace App\Http\Controllers\Spa\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\MembersIndexRequest;
use App\Http\Resources\Admin\MemberDetailResource;
use App\Http\Resources\Admin\MemberSummaryResource;
use App\Services\Admin\MembersService;
use Illuminate\Http\JsonResponse;

class MembersController extends Controller
{
    public function index(MembersIndexRequest $request, MembersService $service): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $registration = $request->query('registration');
        $sort = (string) $request->query('sort', 'newest');
        $perPage = (int) $request->query('perPage', 10);

        $paginator = $service->getPaginated(
            $search,
            is_string($registration) ? $registration : null,
            $sort,
            $perPage,
        );

        $items = MemberSummaryResource::collection($paginator->items())->resolve();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'registration' => is_string($registration) ? $registration : null,
                    'sort' => $sort,
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(string $member, MembersIndexRequest $request, MembersService $service): JsonResponse
    {
        $member = $service->getMemberDetail($member);

        return response()->json([
            'ok' => true,
            'data' => [
                'member' => (new MemberDetailResource($member))->resolve(),
            ],
        ]);
    }
}
