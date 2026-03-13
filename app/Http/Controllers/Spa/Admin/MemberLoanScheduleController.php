<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLoanScheduleRequest;
use App\Http\Resources\Admin\MemberLoanScheduleResource;
use App\Models\AppUser;
use App\Services\Admin\MemberLoans\MemberLoanService;
use Illuminate\Http\JsonResponse;

class MemberLoanScheduleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberLoanScheduleRequest $request,
        AppUser $user,
        string $loanNumber,
        MemberLoanService $service,
    ): JsonResponse {
        $payload = $service->getScheduleEntries($user, $loanNumber);

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
