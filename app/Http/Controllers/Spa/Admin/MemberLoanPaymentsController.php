<?php

namespace App\Http\Controllers\Spa\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MemberLoanPaymentsRequest;
use App\Http\Resources\Admin\MemberLoanPaymentResource;
use App\Models\AppUser;
use App\Services\Admin\MemberLoans\MemberLoanService;
use Illuminate\Http\JsonResponse;

class MemberLoanPaymentsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        MemberLoanPaymentsRequest $request,
        AppUser $user,
        string $loanNumber,
        MemberLoanService $service,
    ): JsonResponse {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 10);

        $payload = $service->getPaymentsWithBalances(
            $user,
            $loanNumber,
            $request->query('range'),
            $request->query('start'),
            $request->query('end'),
            $perPage,
            $page,
        );

        $paginator = $payload['paginator'];
        $items = MemberLoanPaymentResource::collection(
            $paginator->items(),
        )->resolve();

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
                'filters' => $payload['filters'],
                'openingBalance' => $payload['openingBalance'],
                'closingBalance' => $payload['closingBalance'],
            ],
        ]);
    }
}
