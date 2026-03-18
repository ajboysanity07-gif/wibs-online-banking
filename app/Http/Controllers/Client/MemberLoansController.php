<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MemberAccountsSummaryResource;
use App\Http\Resources\Admin\MemberLoanResource;
use App\Services\Admin\MemberAccounts\MemberAccountsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MemberLoansController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        MemberAccountsService $service,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('userProfile', 'adminProfile');

        if ($user->adminProfile !== null) {
            return redirect()->route('admin.dashboard');
        }

        $memberName = $user->username;

        try {
            if (Schema::hasTable('wmaster')) {
                $wmasterName = $user->wmaster()->value('bname');

                if (is_string($wmasterName) && trim($wmasterName) !== '') {
                    $memberName = $wmasterName;
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        $summary = null;
        $summaryError = null;

        try {
            $summary = $service->getSummary($user);
        } catch (\Throwable $exception) {
            report($exception);
            $summaryError = 'Unable to load summary.';
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;
        $loansPayload = null;
        $loansError = null;

        try {
            $paginator = $service->getPaginatedLoans($user, $perPage, $page);
            $items = MemberLoanResource::collection(
                $paginator->items(),
            )->resolve();
            $loansPayload = [
                'items' => $items,
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ];
        } catch (\Throwable $exception) {
            report($exception);
            $loansError = 'Unable to load loans.';
        }

        $memberPayload = $this->sanitizePayload([
            'name' => $memberName,
            'acctno' => $user->acctno,
        ]);
        $summaryPayload = $summary === null
            ? null
            : $this->sanitizePayload(
                (new MemberAccountsSummaryResource($summary))->resolve(),
            );
        $loansPayload = $loansPayload === null
            ? null
            : $this->sanitizePayload($loansPayload);

        return Inertia::render('client/loans', [
            'member' => $memberPayload,
            'summary' => $summaryPayload,
            'summaryError' => $summaryError,
            'loans' => $loansPayload,
            'loansError' => $loansError,
        ]);
    }

    private function sanitizePayload(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizePayload($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        return $value;
    }

    private function sanitizeString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding(
                $value,
                'UTF-8',
                'UTF-8,ISO-8859-1,Windows-1252',
            );

            if (is_string($converted) && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($converted === false) {
            return '';
        }

        return $converted;
    }
}
