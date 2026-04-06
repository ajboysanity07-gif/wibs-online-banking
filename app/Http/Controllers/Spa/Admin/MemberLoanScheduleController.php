<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLoanScheduleRequest;
use App\Http\Resources\Admin\MemberLoanScheduleResource;
use App\Services\Admin\MemberLoans\MemberLoanService;
use App\Services\Admin\MembersService;
use Illuminate\Http\JsonResponse;

class MemberLoanScheduleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberLoanScheduleRequest $request,
        string $user,
        string $loanNumber,
        MembersService $membersService,
        MemberLoanService $service,
    ): JsonResponse {
        $context = $membersService->resolveAccountContext($user);
        $payload = $service->getScheduleEntries($context['member'], $loanNumber);

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => MemberLoanScheduleResource::collection(
                    $payload['items'],
                )->resolve(),
            ],
        ]);
    }
}
