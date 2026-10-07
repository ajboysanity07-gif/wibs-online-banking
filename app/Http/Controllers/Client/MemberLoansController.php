<?php

namespace App\Http\Controllers\Client;

use App\Domains\MemberAccounts\Resources\MemberAccountsSummaryResource;
use App\Domains\MemberAccounts\Resources\MemberLoanPaymentResource;
use App\Domains\MemberAccounts\Resources\MemberLoanResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Models\AppUser;
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

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        $memberName = $user->username;

        try {
            if (Schema::hasTable('wmaster')) {
                $wmaster = $user->wmaster()->first([
                    'acctno',
                    'fname',
                    'mname',
                    'lname',
                    'bname',
                ]);
                $wmasterName = $wmaster?->displayName();

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

        $page = 1;
        $perPage = 500;
        $loansPayload = null;
        $loansError = null;

        try {
            $paginator = $service->getPaginatedLoans($user, $perPage, $page);
            $items = MemberLoanResource::collection(
                $this->withRepaymentSummaries($user, $service, $paginator->items()),
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

        $payments = null;
        $paymentsError = null;

        try {
            $payments = $this->sanitizePayload(
                MemberLoanPaymentResource::collection(
                    $service->getRecentLoanPayments($user, 8),
                )->resolve(),
            );
        } catch (\Throwable $exception) {
            report($exception);
            $paymentsError = 'Unable to load payments.';
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
            'payments' => $payments,
            'paymentsError' => $paymentsError,
        ]);
    }

    /**
     * Attach the schedule-derived next due date and installment amount to each
     * loan row in a single batched lookup, avoiding a per-loan query.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    private function withRepaymentSummaries(
        AppUser $user,
        MemberAccountsService $service,
        array $items,
    ): array {
        $loanNumbers = array_map(
            static fn (mixed $item): string => trim((string) data_get($item, 'lnnumber', '')),
            $items,
        );

        if (array_filter($loanNumbers) === []) {
            return $items;
        }

        $balancesByLoan = [];

        foreach ($items as $item) {
            $number = trim((string) data_get($item, 'lnnumber', ''));

            if ($number === '') {
                continue;
            }

            $balance = data_get($item, 'balance');

            $balancesByLoan[$number] = $balance === null ? null : (float) $balance;
        }

        $summaries = $service->getLoanRepaymentSummaries($user, $loanNumbers, $balancesByLoan);

        if ($summaries === []) {
            return $items;
        }

        foreach ($items as $item) {
            $summary = $summaries[trim((string) data_get($item, 'lnnumber', ''))] ?? null;

            if ($summary === null) {
                continue;
            }

            data_set($item, 'dueDate', $summary['dueDate']);
            data_set($item, 'monthlyDue', $summary['monthlyDue']);
            data_set($item, 'penalty', $summary['penalty'] ?? null);
        }

        return $items;
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
