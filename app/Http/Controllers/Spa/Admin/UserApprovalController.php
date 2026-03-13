<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberStatusRequest;
use App\Http\Resources\Admin\MemberDetailResource;
use App\Models\AppUser;
use App\Services\Admin\MemberStatusService;
use Illuminate\Http\JsonResponse;

class UserApprovalController extends Controller
{
    public function approve(
        MemberStatusRequest $request,
        AppUser $user,
        MemberStatusService $service,
    ): JsonResponse {
        $member = $service->approve($user, $request->user());

        return response()->json([
            'ok' => true,
            'data' => [
                'member' => (new MemberDetailResource($member))->resolve(),
            ],
        ]);
    }
}
