<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberIndexRequest;
use App\Http\Resources\Admin\MemberDetailResource;
use App\Http\Resources\Admin\MemberSummaryResource;
use App\Models\AppUser;
use App\Services\Admin\MembersService;
use Illuminate\Http\JsonResponse;

class MembersController extends Controller
{
    public function index(MemberIndexRequest $request, MembersService $service): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $sort = (string) $request->query('sort', 'newest');
        $perPage = (int) $request->query('perPage', 10);

        $paginator = $service->getPaginated(
            $search,
            is_string($status) ? $status : null,
            $sort,
            $perPage,
        );

        $items = MemberSummaryResource::collection($paginator->items())->resolve();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'status' => is_string($status) ? $status : null,
                    'sort' => $sort,
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(AppUser $user, MembersService $service): JsonResponse
    {
        $user->loadMissing('adminProfile');

        if ($user->adminProfile !== null) {
            abort(404);
        }

        $member = $service->getMemberDetail($user);

        return response()->json([
            'ok' => true,
            'data' => [
                'member' => (new MemberDetailResource($member))->resolve(),
            ],
        ]);
    }
}
