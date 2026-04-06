<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberAdminAccessRequest;
use App\Http\Resources\Admin\MemberDetailResource;
use App\Models\AppUser;
use App\Services\Admin\MemberAdminAccessService;
use App\Services\Admin\MembersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MemberAdminAccessController extends Controller
{
    public function grant(
        MemberAdminAccessRequest $request,
        string $member,
        MembersService $membersService,
        MemberAdminAccessService $service,
    ): JsonResponse {
        $user = $this->resolveMember($membersService, $member);

        $member = $service->grant($user, $request->user());

        return response()->json([
            'ok' => true,
            'data' => [
                'member' => (new MemberDetailResource($member))->resolve(),
            ],
        ]);
    }

    public function revoke(
        MemberAdminAccessRequest $request,
        string $member,
        MembersService $membersService,
        MemberAdminAccessService $service,
    ): JsonResponse {
        $user = $this->resolveMember($membersService, $member);

        $member = $service->revoke($user, $request->user());

        return response()->json([
            'ok' => true,
            'data' => [
                'member' => (new MemberDetailResource($member))->resolve(),
            ],
        ]);
    }

    private function resolveMember(
        MembersService $service,
        string $member,
    ): AppUser {
        $member = $service->getMemberDetail($member);

        if (! $member instanceof AppUser) {
            throw ValidationException::withMessages([
                'member' => 'Only registered members can be granted admin access.',
            ]);
        }

        return $member;
    }
}
