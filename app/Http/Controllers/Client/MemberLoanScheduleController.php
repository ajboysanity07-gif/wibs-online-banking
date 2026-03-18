<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MemberLoanResource;
use App\Http\Resources\Admin\MemberLoanScheduleResource;
use App\Http\Resources\Admin\MemberLoanSummaryResource;
use App\Services\Admin\MemberLoans\MemberLoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MemberLoanScheduleController extends Controller
{
    public function __invoke(
        Request $request,
        string $loanNumber,
        MemberLoanService $service,
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
        } catch (Throwable $exception) {
            report($exception);
        }

        $payload = $service->getSchedulePageData($user, $loanNumber);

        $memberPayload = $this->sanitizePayload([
            'member_name' => $memberName,
            'acctno' => $user->acctno,
        ]);
        $loanPayload = $this->sanitizePayload(
            (new MemberLoanResource($payload['loan']))->resolve(),
        );
        $summaryPayload = $this->sanitizePayload(
            (new MemberLoanSummaryResource($payload['summary']))->resolve(),
        );
        $schedulePayload = $this->sanitizePayload([
            'items' => MemberLoanScheduleResource::collection(
                $payload['schedule'],
            )->resolve(),
        ]);

        return Inertia::render('client/loan-schedule', [
            'member' => $memberPayload,
            'loan' => $loanPayload,
            'summary' => $summaryPayload,
            'schedule' => $schedulePayload,
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
