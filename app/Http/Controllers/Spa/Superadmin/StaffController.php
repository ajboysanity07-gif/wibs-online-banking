<?php

namespace App\Http\Controllers\Spa\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\PromoteMemberToStaffRequest;
use App\Http\Requests\Superadmin\ReactivateStaffAccessRequest;
use App\Http\Requests\Superadmin\StaffHistoryRequest;
use App\Http\Requests\Superadmin\StaffIndexRequest;
use App\Http\Requests\Superadmin\StoreStaffRequest;
use App\Http\Requests\Superadmin\SuspendStaffAccessRequest;
use App\Http\Requests\Superadmin\UpdateStaffRolesRequest;
use App\Http\Resources\Admin\StaffAccountResource;
use App\Http\Resources\Admin\UserRoleChangeResource;
use App\Models\AppUser;
use App\Services\Admin\StaffManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(
        StaffIndexRequest $request,
        StaffManagementService $service,
    ): JsonResponse {
        $paginator = $service->getPaginated(
            (string) $request->validated('search', ''),
            $request->validated('role'),
            $request->validated('access'),
            (int) $request->validated('perPage', 10),
            (int) $request->validated('page', 1),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => StaffAccountResource::collection(
                    $paginator->getCollection(),
                )->resolve(),
                'meta' => [
                    'search' => $request->validated('search'),
                    'role' => $request->validated('role'),
                    'access' => $request->validated('access'),
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function store(
        StoreStaffRequest $request,
        StaffManagementService $service,
    ): JsonResponse {
        $staff = $service->createStaff($request->validated(), $request->user());

        return response()->json([
            'ok' => true,
            'data' => [
                'staff' => (new StaffAccountResource($staff))->resolve(),
                'message' => 'Staff account created successfully. The temporary password is not shown again.',
            ],
        ], 201);
    }

    public function updateRole(
        UpdateStaffRolesRequest $request,
        AppUser $user,
        StaffManagementService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $staff = $validated['operation'] === 'remove'
            ? $service->removeRole(
                $user,
                $request->user(),
                (string) $validated['role'],
                (string) $validated['reason'],
            )
            : $service->assignRole(
                $user,
                $request->user(),
                (string) $validated['role'],
                (string) $validated['reason'],
            );

        return response()->json([
            'ok' => true,
            'data' => [
                'staff' => (new StaffAccountResource($staff))->resolve(),
            ],
        ]);
    }

    public function suspend(
        SuspendStaffAccessRequest $request,
        AppUser $user,
        StaffManagementService $service,
    ): JsonResponse {
        $staff = $service->suspend(
            $user,
            $request->user(),
            (string) $request->validated('reason'),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'staff' => (new StaffAccountResource($staff))->resolve(),
            ],
        ]);
    }

    public function reactivate(
        ReactivateStaffAccessRequest $request,
        AppUser $user,
        StaffManagementService $service,
    ): JsonResponse {
        $staff = $service->reactivate(
            $user,
            $request->user(),
            (string) $request->validated('reason'),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'staff' => (new StaffAccountResource($staff))->resolve(),
            ],
        ]);
    }

    public function memberLookup(
        Request $request,
        StaffManagementService $service,
    ): JsonResponse {
        $request->validate(['account_number' => ['required', 'string']]);

        $member = $service->findMemberByAccountNumber(
            (string) $request->input('account_number'),
        );

        if ($member === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No member account found with this account number.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'member' => (new StaffAccountResource($member))->resolve(),
            ],
        ]);
    }

    public function searchMembers(
        Request $request,
        StaffManagementService $service,
    ): JsonResponse {
        $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $members = $service->searchMembers((string) $request->input('query'));

        return response()->json([
            'ok' => true,
            'data' => [
                'members' => StaffAccountResource::collection($members)->resolve(),
            ],
        ]);
    }

    public function promote(
        PromoteMemberToStaffRequest $request,
        StaffManagementService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $staff = $service->promoteMember(
            (string) $validated['account_number'],
            (string) $validated['role'],
            $validated['reason'] ?? null,
            $request->user(),
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'staff' => (new StaffAccountResource($staff))->resolve(),
                'message' => 'Member promoted to staff successfully.',
            ],
        ], 201);
    }

    public function history(
        StaffHistoryRequest $request,
        AppUser $user,
        StaffManagementService $service,
    ): JsonResponse {
        return response()->json([
            'ok' => true,
            'data' => [
                'items' => UserRoleChangeResource::collection(
                    $service->historyFor(
                        $user,
                        (int) $request->validated('limit', 50),
                    ),
                )->resolve(),
            ],
        ]);
    }
}
