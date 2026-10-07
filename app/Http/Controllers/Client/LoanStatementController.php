<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Admin\MemberLoans\MemberLoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanStatementController extends Controller
{
    public function __invoke(
        Request $request,
        string $loanNumber,
        MemberLoanService $service,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('userProfile', 'adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $month = (string) $request->query('month', '');

        return response()->json(
            $service->getStatementData($user, $loanNumber, $month),
        );
    }
}
