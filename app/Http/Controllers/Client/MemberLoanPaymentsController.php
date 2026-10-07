<?php

namespace App\Http\Controllers\Client;

use App\Domains\MemberAccounts\Resources\MemberLoanResource;
use App\Domains\MemberAccounts\Services\MemberAccountsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\MemberLoanPaymentsRequest;
use App\Http\Resources\Admin\MemberLoanPaymentResource;
use App\Http\Resources\Admin\MemberLoanScheduleResource;
use App\Http\Resources\Admin\MemberLoanSummaryResource;
use App\Models\Wmaster;
use App\Services\Admin\MemberLoans\MemberLoanExportService;
use App\Services\Admin\MemberLoans\MemberLoanService;
use App\Services\LoanRequests\OfficialLoanManagerResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MemberLoanPaymentsController extends Controller
{
    public function __invoke(
        MemberLoanPaymentsRequest $request,
        string $loanNumber,
        MemberLoanService $service,
        MemberAccountsService $accounts,
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('userProfile', 'adminProfile', 'roles');

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
        } catch (Throwable $exception) {
            report($exception);
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('perPage', 10);
        $range = $request->query('range');
        $start = $request->query('start');
        $end = $request->query('end');

        $payload = $service->getPaymentsPageData(
            $user,
            $loanNumber,
            $range,
            $start,
            $end,
            $perPage,
            $page,
        );

        $paginator = $payload['payments'];

        $memberPayload = $this->sanitizePayload([
            'member_name' => $memberName,
            'acctno' => $user->acctno,
            'signatureUrl' => $this->memberSignatureUrl($user),
        ]);

        $officialManager = app(OfficialLoanManagerResolver::class);

        $loanManagerPayload = $this->sanitizePayload([
            'name' => $officialManager->name(),
            'role' => $officialManager->position(),
            'signatureUrl' => $this->memberSignatureUrl($user),
        ]);
        $loanPayload = $this->sanitizePayload(
            (new MemberLoanResource($payload['loan']))->resolve(),
        );
        $summaryPayload = $this->sanitizePayload(
            (new MemberLoanSummaryResource($payload['summary']))->resolve(),
        );
        $paymentsPayload = $this->sanitizePayload([
            'items' => MemberLoanPaymentResource::collection(
                $paginator->items(),
            )->resolve(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'filters' => $payload['filters'],
            'openingBalance' => $payload['openingBalance'],
            'closingBalance' => $payload['closingBalance'],
        ]);
        $schedulePayload = $this->sanitizePayload([
            'items' => MemberLoanScheduleResource::collection(
                $payload['schedule'],
            )->resolve(),
        ]);

        $soaMonths = [];
        $certificateEligible = false;

        try {
            $soaMonths = $service->getStatementMonths($user, $loanNumber);
            $certificateEligible = (float) data_get($payload['loan'], 'balance', 0) <= 0;
        } catch (Throwable $exception) {
            report($exception);
        }

        $securityBalance = 0.0;

        try {
            $securityBalance = (float) $accounts->getLoanSecurityLedgerSummary($user)['latestBalance'];
        } catch (Throwable $exception) {
            report($exception);
        }

        $documentsPayload = $this->sanitizePayload([
            'soaMonths' => $soaMonths,
            'certificateEligible' => $certificateEligible,
        ]);

        return Inertia::render('client/loan-payments', [
            'member' => $memberPayload,
            'loanManager' => $loanManagerPayload,
            'loan' => $loanPayload,
            'summary' => $summaryPayload,
            'payments' => $paymentsPayload,
            'schedule' => $schedulePayload,
            'documents' => $documentsPayload,
            'securityBalance' => $securityBalance,
        ]);
    }

    public function print(
        MemberLoanPaymentsRequest $request,
        string $loanNumber,
        MemberLoanExportService $service,
    ): View|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('userProfile', 'adminProfile');

        if ($user->isAdminOnly()) {
            return redirect()->route('admin.dashboard');
        }

        return $service->renderPaymentsPrintView(
            $user,
            $loanNumber,
            $request->query('range'),
            $request->query('start'),
            $request->query('end'),
        );
    }

    private function memberSignatureUrl(mixed $user): ?string
    {
        $acctno = trim((string) ($user->acctno ?? ''));

        if ($acctno === '') {
            return null;
        }

        try {
            if (! Schema::hasTable('wmaster') || ! Schema::hasColumn('wmaster', 'bsignature')) {
                return null;
            }

            $blob = Wmaster::query()->where('acctno', $acctno)->value('bsignature');

            if (! is_string($blob) || strlen($blob) < 8) {
                return null;
            }
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return route('client.member-signature', ['acctno' => $acctno]);
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
